<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->after('name');
            $table->timestamp('unsubscribed_at')->nullable()->after('token');
            $table->index('token');
        });

        // Backfill a token for existing subscribers so their unsubscribe links work.
        foreach (DB::table('subscribers')->whereNull('token')->pluck('id') as $id) {
            DB::table('subscribers')->where('id', $id)->update(['token' => Str::random(40)]);
        }
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropIndex(['token']);
            $table->dropColumn(['token', 'unsubscribed_at']);
        });
    }
};
