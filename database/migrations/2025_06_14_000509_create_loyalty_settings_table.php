<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per tenant (singleton) holding the loyalty-program rules.
        Schema::create('loyalty_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(false);
            // Points awarded per 1 unit of net revenue spent (e.g. 1.0 => 1 pt / $1).
            $table->decimal('earn_rate', 8, 4)->default(1);
            // Currency value of redeeming 1 point (e.g. 0.05 => 100 pts = $5).
            $table->decimal('redeem_value', 8, 4)->default(0.05);
            // Minimum points a customer must hold before they can redeem.
            $table->unsignedInteger('min_redeem_points')->default(0);
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_settings');
    }
};
