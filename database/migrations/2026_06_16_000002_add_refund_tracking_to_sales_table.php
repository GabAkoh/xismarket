<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Cumulative amounts/points reversed across one or more returns, so
            // apportioned reversals stay exact and are never applied twice.
            $table->decimal('refunded_total', 12, 2)->default(0)->after('paid_total');
            $table->decimal('wallet_refunded', 12, 2)->default(0)->after('wallet_used');
            $table->integer('points_earned_reversed')->default(0)->after('points_earned');
            $table->integer('points_redeemed_refunded')->default(0)->after('points_redeemed');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['refunded_total', 'wallet_refunded', 'points_earned_reversed', 'points_redeemed_refunded']);
        });
    }
};
