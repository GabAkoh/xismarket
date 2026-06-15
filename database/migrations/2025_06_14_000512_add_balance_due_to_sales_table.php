<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Outstanding balance on a sale. A sale stays 'partially_paid' until this
        // reaches zero, at which point it becomes 'completed'.
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('balance_due', 12, 2)->default(0)->after('change_due');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('balance_due');
        });
    }
};
