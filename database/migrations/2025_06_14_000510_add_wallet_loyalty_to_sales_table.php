<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Record wallet/loyalty activity on each sale so the receipt and history
        // can show it without recomputing from the ledgers.
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('wallet_used', 12, 2)->default(0)->after('change_due');
            $table->decimal('loyalty_discount', 12, 2)->default(0)->after('wallet_used');
            $table->unsignedInteger('points_earned')->default(0)->after('loyalty_discount');
            $table->unsignedInteger('points_redeemed')->default(0)->after('points_earned');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['wallet_used', 'loyalty_discount', 'points_earned', 'points_redeemed']);
        });
    }
};
