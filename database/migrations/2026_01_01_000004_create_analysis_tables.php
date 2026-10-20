<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('previous_run_id')->nullable()->constrained('analysis_runs')->nullOnDelete();
            $table->string('status', 20)->default('pending'); // pending, running, completed, failed
            $table->string('engine', 40)->default('baseline');
            $table->string('pipeline_version', 20);
            $table->json('config')->nullable();
            $table->json('provenance')->nullable();
            $table->json('stats')->nullable();
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->string('dataset_hash', 64)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['product_id', 'created_at']);
        });

        Schema::create('analysis_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('sentiment', 10); // positive, negative, neutral, mixed
            $table->decimal('sentiment_score', 5, 3)->default(0);
            $table->string('sentiment_method', 30);
            $table->string('confidence', 10); // low, medium, high
            $table->boolean('rating_text_conflict')->default(false);
            $table->timestamps();

            $table->unique(['analysis_run_id', 'review_id']);
            $table->index(['analysis_run_id', 'sentiment']);
        });

        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('label');
            $table->boolean('is_discovered')->default(false);
            $table->unsignedInteger('mention_count')->default(0);
            $table->unsignedInteger('positive_count')->default(0);
            $table->unsignedInteger('negative_count')->default(0);
            $table->unsignedInteger('neutral_count')->default(0);
            $table->string('method', 30)->default('lexicon');
            $table->json('top_terms')->nullable();
            $table->json('limitations')->nullable();
            $table->timestamps();

            $table->unique(['analysis_run_id', 'key']);
        });

        Schema::create('review_themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theme_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analysis_review_id')->constrained()->cascadeOnDelete();
            $table->string('polarity', 10);
            $table->json('matched_terms')->nullable();
            $table->string('snippet', 400)->nullable();
            $table->timestamps();

            $table->unique(['theme_id', 'analysis_review_id']);
        });

        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('theme_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20); // overview, advantage, complaint, caveat, mixed
            $table->text('statement');
            $table->string('strength', 10)->default('weak'); // weak, moderate, strong
            $table->string('origin', 20)->default('heuristic'); // heuristic, model
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['analysis_run_id', 'type']);
        });

        Schema::create('finding_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('excerpt', 500)->nullable();
            $table->timestamps();

            $table->unique(['finding_id', 'review_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finding_evidence');
        Schema::dropIfExists('findings');
        Schema::dropIfExists('review_themes');
        Schema::dropIfExists('themes');
        Schema::dropIfExists('analysis_reviews');
        Schema::dropIfExists('analysis_runs');
    }
};
