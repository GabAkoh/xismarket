<?php

namespace App\Services\Search;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductEmbedding;
use App\Support\Tenancy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tiered product search shared by the storefront and POS surfaces.
 *
 * Three tiers, merged by best-of score into a single ranked list of product IDs:
 *
 *   1. Exact    — barcode/SKU exact match (preserves the POS scan fast-path).
 *   2. Fuzzy    — typo-tolerant lexical scoring in PHP (substring + word-token
 *                 trigram/Levenshtein) over a cached lightweight text index.
 *   3. Semantic — cosine similarity between a Gemini query embedding and the
 *                 stored per-product embeddings (see EmbeddingClient / EmbedProductJob).
 *
 * Callers get an ordered Collection of product IDs and apply their own
 * filtering (category, warehouse stock), ordering (FIELD) and paging.
 *
 * Degrades gracefully: with no embeddings provider configured the semantic tier
 * is skipped; fuzzy + exact still serve results.
 */
class ProductSearch
{
    /** Hard cap on returned IDs — bounds the whereIn / FIELD() the caller builds. */
    protected const MAX_RESULTS = 300;

    /** Minimum lexical score for a product to be considered a fuzzy match. */
    protected const LEXICAL_FLOOR = 0.30;

    /** Minimum cosine for a product to be considered a semantic match. */
    protected const SEMANTIC_FLOOR = 0.30;

    /** Fewer than this many strong lexical hits triggers semantic in 'augment' mode. */
    protected const AUGMENT_THRESHOLD = 5;

    /** Skip the semantic tier for search terms shorter than this (avoids per-keystroke calls). */
    protected const MIN_SEMANTIC_LEN = 3;

    protected const INDEX_TTL = 1800;      // lightweight text index cache (30 min)

    protected const QUERY_TTL = 86400;     // query-embedding cache (1 day)

    /** In-request memo of loaded (normalised) vector matrices, keyed by tenant:version. */
    protected static array $matrixMemo = [];

    protected bool $fuzzy = true;

    public function __construct(
        protected EmbeddingClient $embeddings,
        protected Tenancy $tenancy,
    ) {}

    /**
     * Rank active products for a search term, best match first.
     *
     * @param  array{semantic?: 'always'|'augment'|'off'}  $opts
     * @return Collection<int, int> ordered product IDs
     */
    public function search(string $term, int $limit = self::MAX_RESULTS, array $opts = []): Collection
    {
        $term = trim($term);
        if ($term === '') {
            return collect();
        }

        $this->fuzzy = $this->fuzzyEnabled();
        $ql = mb_strtolower($term);
        $index = $this->lightweightIndex();

        $exact = [];              // exact barcode/SKU, in index order
        $scores = [];             // id => best score across tiers
        $strongLexical = 0;

        foreach ($index as $row) {
            if ($row['barcode_lc'] !== '' && $row['barcode_lc'] === $ql) {
                $exact[] = $row['id'];

                continue;
            }
            if ($row['sku_lc'] !== '' && $row['sku_lc'] === $ql) {
                $exact[] = $row['id'];

                continue;
            }

            $score = $this->lexicalScore($ql, $row);
            if ($score >= self::LEXICAL_FLOOR) {
                $scores[$row['id']] = $score;
                if ($score >= 0.60) {
                    $strongLexical++;
                }
            }
        }

        if ($this->shouldRunSemantic($opts['semantic'] ?? 'augment', $strongLexical, mb_strlen($ql))) {
            foreach ($this->semanticScores($term) as $id => $cosine) {
                // Weighted so a strong lexical (near-exact) hit still edges a
                // merely-related semantic one, but meaning matches surface.
                $scores[$id] = max($scores[$id] ?? 0.0, $cosine * 0.90);
            }
        }

        arsort($scores);

        // Exact matches first, then score-ranked, de-duplicated.
        $ordered = [];
        $seen = [];
        foreach ([...$exact, ...array_keys($scores)] as $id) {
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $ordered[] = $id;
            if (count($ordered) >= min($limit, self::MAX_RESULTS)) {
                break;
            }
        }

        return collect($ordered);
    }

    // ---- Tier 2: fuzzy lexical ------------------------------------------------

    /** Best lexical similarity of the query against a product's name/SKU. */
    protected function lexicalScore(string $ql, array $row): float
    {
        $best = 0.0;
        foreach ([['t' => $row['name_lc'], 'w' => 1.0], ['t' => $row['sku_lc'], 'w' => 0.92]] as $field) {
            if ($field['t'] === '') {
                continue;
            }
            $best = max($best, $field['w'] * $this->similarity($ql, $field['t']));
            if ($best >= 0.999) {
                break;
            }
        }

        return $best;
    }

    /** Similarity of query $q against a single text: substring first, else fuzzy. */
    protected function similarity(string $q, string $text): float
    {
        $pos = mb_strpos($text, $q);
        if ($pos !== false) {
            $wordStart = $pos === 0 || ! $this->isAlnum(mb_substr($text, $pos - 1, 1));
            $coverage = mb_strlen($q) / max(1, mb_strlen($text));

            return min(1.0, ($wordStart ? 0.90 : 0.80) + 0.10 * $coverage);
        }

        return $this->fuzzy ? $this->tokenFuzzy($q, $text) : 0.0;
    }

    /** Average best-token match between query and text (typo-tolerant). */
    protected function tokenFuzzy(string $q, string $text): float
    {
        $qTokens = $this->tokens($q);
        $tTokens = $this->tokens($text);
        if ($qTokens === [] || $tTokens === []) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($qTokens as $qt) {
            $best = 0.0;
            foreach ($tTokens as $tt) {
                $best = max($best, $this->tokenSim($qt, $tt));
                if ($best >= 0.999) {
                    break;
                }
            }
            $sum += $best;
        }

        return $sum / count($qTokens);
    }

    /** Similarity of two single tokens: substring, Levenshtein and trigram. */
    protected function tokenSim(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }
        if (str_contains($b, $a) || str_contains($a, $b)) {
            return 0.85 * (min(strlen($a), strlen($b)) / max(strlen($a), strlen($b)));
        }

        $maxLen = max(strlen($a), strlen($b));
        $levSim = $maxLen > 0 ? 1 - levenshtein($a, $b) / $maxLen : 0.0;

        return max($levSim, $this->trigramJaccard($a, $b));
    }

    protected function trigramJaccard(string $a, string $b): float
    {
        $sa = $this->trigrams($a);
        $sb = $this->trigrams($b);
        if ($sa === [] || $sb === []) {
            return 0.0;
        }

        $intersection = count(array_intersect_key($sa, $sb));
        $union = count($sa + $sb);

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /** Set (shingle => true) of padded character trigrams. */
    protected function trigrams(string $s): array
    {
        $s = '  '.$s.'  ';
        $grams = [];
        $len = strlen($s);
        for ($i = 0; $i + 3 <= $len; $i++) {
            $grams[substr($s, $i, 3)] = true;
        }

        return $grams;
    }

    /** Lowercased alnum tokens. */
    protected function tokens(string $s): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    protected function isAlnum(string $ch): bool
    {
        return $ch !== '' && preg_match('/[\p{L}\p{N}]/u', $ch) === 1;
    }

    // ---- Tier 3: semantic -----------------------------------------------------

    protected function shouldRunSemantic(string $mode, int $strongLexical, int $termLen): bool
    {
        // Don't embed tiny as-you-type fragments — low value, and it avoids a
        // network call on every keystroke at the POS.
        if ($mode === 'off' || $termLen < self::MIN_SEMANTIC_LEN || ! $this->semanticEnabled()) {
            return false;
        }
        if ($mode === 'always') {
            return true;
        }

        // 'augment': only spend an embedding call when lexical results are thin.
        return $strongLexical < self::AUGMENT_THRESHOLD;
    }

    /**
     * Cosine similarity of the query embedding against every product vector.
     *
     * @return array<int, float> id => cosine, above the floor, best first
     */
    protected function semanticScores(string $term): array
    {
        $query = $this->queryEmbedding($term);
        if ($query === null) {
            return [];
        }
        $query = $this->normalize($query);

        $out = [];
        foreach ($this->vectorMatrix() as $row) {
            // Vectors are cached as raw packed float32 bytes; unpack to floats
            // (0-indexed) for the dot product. Already unit-normalised at build.
            $cosine = $this->dot($query, array_values(unpack('g*', $row['vec'])));
            if ($cosine >= self::SEMANTIC_FLOOR) {
                $out[$row['id']] = $cosine;
            }
        }

        arsort($out);

        return array_slice($out, 0, self::MAX_RESULTS, true);
    }

    /** Embed the query, cached by provider/model/dims/term to avoid repeat calls. */
    protected function queryEmbedding(string $term): ?array
    {
        try {
            $model = (string) config('services.embeddings.model');
            $signature = $this->embeddings->provider().'|'.$model.'|'.$this->embeddings->dims().'|'.mb_strtolower(trim($term));
            $key = 'product_search:q:'.hash('sha256', $signature);

            $timeout = (float) config('services.embeddings.query_timeout', 2);

            return Cache::remember($key, self::QUERY_TTL, fn () => $this->embeddings->embed($term, $timeout));
        } catch (\Throwable $e) {
            // Benign, expected degradation (slow/offline/misconfigured provider):
            // fall back to fuzzy results. Debug-level so it doesn't spam the log.
            Log::debug('Query embedding skipped: '.$e->getMessage());

            return null;
        }
    }

    /**
     * The tenant's product vectors, unit-normalised, as raw packed float32 bytes.
     *
     * Memoised per request and cached across requests (keyed by the search-index
     * version, so any product write rebuilds it). Without the cross-request cache
     * every semantic search would reload and re-normalise the whole catalogue
     * from the DB — ~2.6s at 8k products. Packed bytes are stored rather than
     * nested float arrays: far cheaper to (un)serialise and a third the size.
     *
     * @return array<int, array{id: int, vec: string}>
     */
    protected function vectorMatrix(): array
    {
        $tenantId = $this->tenancy->id();
        $memoKey = $tenantId.':'.self::version($tenantId);
        if (isset(self::$matrixMemo[$memoKey])) {
            return self::$matrixMemo[$memoKey];
        }

        $cacheKey = "product_search:matrix:{$tenantId}:".self::version($tenantId);
        $rows = Cache::remember($cacheKey, self::INDEX_TTL, function () {
            $rows = [];
            ProductEmbedding::query()
                ->join('products', 'products.id', '=', 'product_embeddings.product_id')
                ->where('products.is_active', true)
                ->select('product_embeddings.product_id as id', 'product_embeddings.vector')
                ->orderBy('product_embeddings.product_id')
                ->chunk(1000, function ($chunk) use (&$rows) {
                    foreach ($chunk as $r) {
                        $vec = ProductEmbedding::unpack((string) $r->vector);
                        if ($vec !== []) {
                            $rows[] = ['id' => (int) $r->id, 'vec' => pack('g*', ...$this->normalize($vec))];
                        }
                    }
                });

            return $rows;
        });

        return self::$matrixMemo[$memoKey] = $rows;
    }

    protected function normalize(array $vec): array
    {
        $norm = 0.0;
        foreach ($vec as $v) {
            $norm += $v * $v;
        }
        $norm = sqrt($norm);
        if ($norm <= 0) {
            return $vec;
        }
        foreach ($vec as $i => $v) {
            $vec[$i] = $v / $norm;
        }

        return $vec;
    }

    /** Dot product; both inputs are expected to be unit-normalised. */
    protected function dot(array $a, array $b): float
    {
        $sum = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $sum += $a[$i] * $b[$i];
        }

        return $sum;
    }

    // ---- Index + toggles ------------------------------------------------------

    /**
     * Lightweight per-tenant text index (id + lowercased name/sku/barcode),
     * cached and versioned so product writes rebuild it lazily.
     *
     * @return array<int, array{id: int, name_lc: string, sku_lc: string, barcode_lc: string}>
     */
    protected function lightweightIndex(): array
    {
        $tenantId = $this->tenancy->id();
        $key = "product_search:index:{$tenantId}:".self::version($tenantId);

        return Cache::remember($key, self::INDEX_TTL, function () {
            return Product::query()
                ->where('is_active', true)
                ->get(['id', 'name', 'sku', 'barcode'])
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name_lc' => mb_strtolower((string) $p->name),
                    'sku_lc' => mb_strtolower((string) $p->sku),
                    'barcode_lc' => mb_strtolower((string) $p->barcode),
                ])->all();
        });
    }

    protected function semanticEnabled(): bool
    {
        if (! $this->embeddings->configured()) {
            return false;
        }
        $tenant = $this->tenancy->current();

        return $tenant ? (bool) $tenant->setting('search.semantic_enabled', true) : true;
    }

    protected function fuzzyEnabled(): bool
    {
        $tenant = $this->tenancy->current();

        return $tenant ? (bool) $tenant->setting('search.fuzzy_enabled', true) : true;
    }

    /**
     * A portable ORDER BY expression that reproduces a given ID ordering.
     * Used instead of MySQL-only FIELD() so it also works under SQLite (tests).
     *
     * @param  array<int, int>  $ids
     */
    public static function orderByIds(array $ids): string
    {
        $ids = array_values(array_map('intval', $ids));
        if ($ids === []) {
            return '1';
        }

        $cases = '';
        foreach ($ids as $position => $id) {
            $cases .= " WHEN {$id} THEN {$position}";
        }

        return "CASE id{$cases} ELSE ".count($ids).' END';
    }

    // ---- Cache versioning (called by ProductObserver / EmbedProductJob) -------

    public static function version(?int $tenantId): int
    {
        if ($tenantId === null) {
            return 0;
        }

        return (int) Cache::get(self::versionKey($tenantId), 1);
    }

    /** Bump the per-tenant index version so caches rebuild on the next search. */
    public static function bumpVersion(?int $tenantId): void
    {
        if ($tenantId === null) {
            return;
        }

        $key = self::versionKey($tenantId);
        if (Cache::get($key) === null) {
            Cache::forever($key, 2);
        } else {
            Cache::increment($key);
        }

        self::$matrixMemo = [];
    }

    protected static function versionKey(int $tenantId): string
    {
        return "product_search:version:{$tenantId}";
    }
}
