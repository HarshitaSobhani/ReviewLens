<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewTheme extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['matched_terms' => 'array'];
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function analysisReview(): BelongsTo
    {
        return $this->belongsTo(AnalysisReview::class);
    }
}
