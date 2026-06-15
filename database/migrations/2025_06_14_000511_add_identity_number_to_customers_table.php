<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Government / tax / national ID used to identify the customer.
            $table->string('identity_number')->nullable()->after('email');

            // Unique per tenant when present. MySQL treats NULLs as distinct,
            // so many customers may have no ID while non-null values stay unique.
            $table->unique(['tenant_id', 'identity_number']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'identity_number']);
            $table->dropColumn('identity_number');
        });
    }
};
