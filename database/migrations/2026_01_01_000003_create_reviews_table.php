<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_import_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id')->nullable();
            $table->char('fingerprint', 64);
            $table->string('title')->nullable();
            $table->text('body');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->date('reviewed_at')->nullable();
            $table->boolean('verified_purchase')->nullable();
            $table->string('source')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'fingerprint']);
            $table->unique(['product_id', 'external_id']);
            $table->index(['product_id', 'rating']);
            $table->index(['product_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
