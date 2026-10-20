<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('stored_path')->nullable();
            $table->string('source_name')->nullable();
            $table->string('status', 20)->default('previewed'); // previewed, importing, completed, failed
            $table->json('headers')->nullable();
            $table->json('mapping')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('accepted_rows')->default(0);
            $table->unsignedInteger('rejected_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->json('row_errors')->nullable();
            $table->text('error')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_imports');
    }
};
