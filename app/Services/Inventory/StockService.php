<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Apply a stock change and record the movement.
     *
     * Upserts the ProductStock row (quantity += $signedQty) and writes a
     * StockMovement row describing the change. Wrapped in a transaction.
     */
    public function recordMovement(
        Product $p,
        Warehouse $w,
        string $type,
        float $signedQty,
        ?float $unitCost = null,
        ?Model $reference = null,
        ?string $note = null,
    ): StockMovement {
        return DB::transaction(function () use ($p, $w, $type, $signedQty, $unitCost, $reference, $note) {
            $stock = ProductStock::firstOrCreate(
                ['product_id' => $p->id, 'warehouse_id' => $w->id],
                ['quantity' => 0, 'reorder_level' => 0],
            );

            $stock->increment('quantity', $signedQty);

            return StockMovement::create([
                'product_id' => $p->id,
                'warehouse_id' => $w->id,
                'type' => $type,
                'quantity' => $signedQty,
                'unit_cost' => $unitCost ?? (float) $p->cost_price,
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'user_id' => auth()->id(),
            ]);
        });
    }

    /** Quantity on hand for a product in a warehouse. */
    public function quantityFor(Product $p, Warehouse $w): float
    {
        return (float) (ProductStock::query()
            ->where('product_id', $p->id)
            ->where('warehouse_id', $w->id)
            ->value('quantity') ?? 0);
    }
}
