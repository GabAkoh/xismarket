<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // sale (taken at checkout) | settlement (later credit payment) | refund
            $table->string('kind')->default('sale')->after('method');
        });

        // Backfill existing rows: negative amounts are refunds; positive payments
        // recorded after the sale's completion are settlements.
        DB::table('payments')->where('amount', '<', 0)->update(['kind' => 'refund']);

        DB::table('payments')
            ->join('sales', 'sales.id', '=', 'payments.sale_id')
            ->where('payments.amount', '>', 0)
            ->whereColumn('payments.paid_at', '>', 'sales.completed_at')
            ->update(['payments.kind' => 'settlement']);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
