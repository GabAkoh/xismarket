<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allowed sign-in windows. A schedule lives on the role (default for its
 * members) and optionally on the user (overrides the role). Null = unrestricted.
 * Shape: {"enabled":true,"start":"07:00","end":"20:00","days":[1,2,3,4,5,6]}
 * (days: 0=Sun … 6=Sat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->json('access_hours')->nullable()->after('description');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->json('access_hours')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('roles', fn (Blueprint $t) => $t->dropColumn('access_hours'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('access_hours'));
    }
};
