<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('brand')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'name']);
            $table->index(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
