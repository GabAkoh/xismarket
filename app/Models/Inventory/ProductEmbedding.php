<?php

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product's semantic-search vector. One row per product (per tenant); the raw
 * vector is stored as packed little-endian float32 bytes in the `vector` column.
 */
class ProductEmbedding extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'product_id', 'model', 'dims', 'vector', 'source_hash',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Encode a float vector for storage: packed little-endian float32, base64'd
     * (text-safe so NUL bytes survive every DB driver, including SQLite).
     *
     * @param  array<int, float>  $vector
     */
    public static function pack(array $vector): string
    {
        return base64_encode(pack('g*', ...array_map('floatval', $vector)));
    }

    /**
     * Decode stored text back into a float vector (0-indexed).
     *
     * @return array<int, float>
     */
    public static function unpack(string $stored): array
    {
        if ($stored === '') {
            return [];
        }

        $binary = base64_decode($stored, true);
        if ($binary === false || $binary === '') {
            return [];
        }

        return array_values(unpack('g*', $binary) ?: []);
    }

    /** This row's vector as a float array. */
    public function toVector(): array
    {
        return static::unpack((string) $this->vector);
    }
}
