<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // The embedding model that produced this vector, and its dimensionality.
            $table->string('model');
            $table->unsignedSmallInteger('dims');
            // Base64 of packed little-endian float32 values (see ProductEmbedding).
            // Text (not binary) so NUL bytes survive every driver, incl. SQLite.
            $table->text('vector');
            // Hash of the embeddable source text, so unchanged products are not
            // re-embedded (avoids needless provider calls / cost).
            $table->string('source_hash', 64);
            $table->timestamps();

            $table->unique('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_embeddings');
    }
};
