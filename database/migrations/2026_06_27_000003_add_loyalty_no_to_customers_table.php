<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Store-assigned loyalty / membership number — the primary lookup at
            // the register. Manually entered; unique per tenant (NULLs allowed).
            $table->string('loyalty_no')->nullable()->after('identity_number');
            $table->unique(['tenant_id', 'loyalty_no']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'loyalty_no']);
            $table->dropColumn('loyalty_no');
        });
    }
};
