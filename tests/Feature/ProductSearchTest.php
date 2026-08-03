<?php

namespace Tests\Feature;

use App\Models\Inventory\Product;
use App\Models\Tenant;
use App\Services\Search\ProductSearch;
use App\Support\Tenancy;
use Database\Factories\ProductFactory;
use Database\Factories\TenantFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Deterministic offline embeddings; 256 dims keeps hash collisions rare.
        config([
            'services.embeddings.provider' => 'stub',
            'services.embeddings.dims' => 256,
        ]);

        $this->tenant = TenantFactory::new()->create();
        $this->setTenant();
    }

    protected function setTenant(): void
    {
        app(Tenancy::class)->set($this->tenant);
    }

    protected function search(string $term, string $semantic): Collection
    {
        return app(ProductSearch::class)->search($term, 20, ['semantic' => $semantic]);
    }

    public function test_exact_barcode_ranks_first(): void
    {
        $scanned = ProductFactory::new()->create(['name' => 'Blue Ballpoint Pen', 'barcode' => '6001234']);
        // A different product whose *name* merely contains the code.
        ProductFactory::new()->create(['name' => '6001234 Spiral Notebook']);

        $ids = $this->search('6001234', 'off');

        $this->assertSame($scanned->id, $ids->first());
    }

    public function test_fuzzy_search_tolerates_typos(): void
    {
        $pampers = ProductFactory::new()->create(['name' => 'Pampers Baby Diapers']);
        ProductFactory::new()->create(['name' => 'Office Stapler']);

        $ids = $this->search('pamprs', 'off'); // misspelled

        $this->assertTrue($ids->contains($pampers->id), 'Typo should still match Pampers.');
        $this->assertSame($pampers->id, $ids->first());
    }

    public function test_semantic_search_finds_matches_beyond_lexical(): void
    {
        // 'bottle' appears only in the description, which the lexical tier ignores.
        $flask = ProductFactory::new()->create([
            'name' => 'Infant Milk Flask',
            'sku' => 'FLASK1',
            'description' => 'feeding bottle',
        ]);
        ProductFactory::new()->create([
            'name' => 'Garden Hose',
            'sku' => 'HOSE1',
            'description' => 'rubber watering hose',
        ]);

        Artisan::call('search:backfill', ['--sync' => true, '--tenant' => $this->tenant->id]);
        $this->setTenant(); // the command clears tenancy in its finally block

        // Lexical-only: the flask is not found because 'bottle' isn't in name/SKU.
        $lexical = $this->search('bottle', 'off');
        $this->assertFalse($lexical->contains($flask->id));

        // Semantic: the description embedding pulls the flask in, and the
        // unrelated hose stays out.
        $semantic = $this->search('bottle', 'always');
        $this->assertTrue($semantic->contains($flask->id), 'Semantic tier should surface the flask.');
        $this->assertSame($flask->id, $semantic->first());
    }

    public function test_embedding_is_stored_for_products(): void
    {
        ProductFactory::new()->create(['name' => 'Test Widget']);

        Artisan::call('search:backfill', ['--sync' => true, '--tenant' => $this->tenant->id]);
        $this->setTenant();

        $this->assertDatabaseCount('product_embeddings', 1);
    }

    public function test_search_degrades_to_fuzzy_when_embeddings_provider_fails(): void
    {
        // A real provider is configured, but the network call fails/times out.
        config(['services.embeddings.provider' => 'gemini', 'services.embeddings.key' => 'test-key']);
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $pampers = ProductFactory::new()->create(['name' => 'Pampers Baby Diapers']);

        // 'augment' would trigger a semantic call (thin lexical for the typo);
        // the failed embedding must not bubble up — fuzzy results still serve.
        $ids = $this->search('pamprs', 'augment');

        $this->assertTrue($ids->contains($pampers->id));
    }
}
