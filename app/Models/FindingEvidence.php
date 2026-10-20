<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FindingEvidence extends Model
{
    protected $table = 'finding_evidence';

    protected $guarded = ['*'];

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
