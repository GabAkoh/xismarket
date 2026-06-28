<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a nullable variant_id alongside the existing product_id on every table
     * that references a product. Additive for now — product_id stays valid so
     * the app keeps working while the variant layer is built out stage by stage.
     */
    public function up(): void
    {
        foreach (['product_stocks', 'stock_movements', 'sale_items', 'order_items', 'purchase_order_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('variant_id')->nullable()->after('product_id')
                    ->constrained('product_variants')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['product_stocks', 'stock_movements', 'sale_items', 'order_items', 'purchase_order_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('variant_id');
            });
        }
    }
};
