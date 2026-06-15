<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Loyalty-points ledger. Mirrors Customer.loyalty_points the same way
        // wallet_transactions mirrors the balance.
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type');                 // earn | redeem | adjust
            $table->integer('points');              // signed: earn +, redeem -
            $table->integer('points_balance');      // running balance after this row
            $table->string('reason')->nullable();
            $table->nullableMorphs('source');       // e.g. the Sale that earned / redeemed
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};
