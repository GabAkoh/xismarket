<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Store-credit ledger. Every change to Customer.balance writes a row here
        // so the wallet has a full, auditable history.
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type');                 // credit | debit
            $table->decimal('amount', 12, 2);       // always positive; type gives direction
            $table->decimal('balance_after', 12, 2);
            $table->string('reason')->nullable();
            $table->nullableMorphs('source');       // e.g. a Sale that spent / refunded credit
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
