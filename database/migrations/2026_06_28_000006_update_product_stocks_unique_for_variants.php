<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_stocks', function (Blueprint $table) {
            // Was unique per (product, warehouse) — now stock is per variant, so
            // a product can have several rows in one warehouse (one per variant).
            // The product_id FK leans on that unique index, so drop it first.
            $table->dropForeign(['product_id']);
            $table->dropUnique('product_stocks_product_id_warehouse_id_unique');
            $table->unique(['product_id', 'variant_id', 'warehouse_id']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'variant_id', 'warehouse_id']);
            $table->unique(['product_id', 'warehouse_id']);
        });
    }
};
