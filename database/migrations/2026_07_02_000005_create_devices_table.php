<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registered devices for the "only approved devices may sign in" control. Each
 * browser gets a long-lived signed cookie holding a random token; a matching
 * approved device row grants access. Pending rows appear for admins to approve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64);
            $table->string('label')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->boolean('approved')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
