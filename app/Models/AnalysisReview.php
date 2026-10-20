<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalysisReview extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['sentiment_score' => 'float', 'rating_text_conflict' => 'boolean'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class, 'analysis_run_id');
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function themeLinks(): HasMany
    {
        return $this->hasMany(ReviewTheme::class);
    }
}
