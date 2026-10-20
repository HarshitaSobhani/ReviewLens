<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Finding extends Model
{
    protected $guarded = ['*'];

    public function run(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class, 'analysis_run_id');
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(FindingEvidence::class);
    }
}
