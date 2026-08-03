<?php

namespace Database\Factories;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 *
 * tenant_id is left unset so the BelongsToTenant trait fills it from the active
 * tenant — set one via app(Tenancy::class)->set($tenant) before creating.
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => ucwords(fake()->unique()->words(3, true)),
            'sku' => strtoupper(Str::random(8)),
            'barcode' => null,
            'description' => null,
            'cost_price' => fake()->randomFloat(2, 1, 500),
            'sale_price' => fake()->randomFloat(2, 1, 900),
            'tax_rate' => 0,
            'track_stock' => true,
            'is_active' => true,
        ];
    }
}
