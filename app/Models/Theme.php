<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Theme extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['is_discovered' => 'boolean', 'top_terms' => 'array', 'limitations' => 'array'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class, 'analysis_run_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(ReviewTheme::class);
    }
}
