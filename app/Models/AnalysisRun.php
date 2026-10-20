<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalysisRun extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'provenance' => 'array',
            'stats' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function previousRun(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_run_id');
    }

    public function analysisReviews(): HasMany
    {
        return $this->hasMany(AnalysisReview::class);
    }

    public function themes(): HasMany
    {
        return $this->hasMany(Theme::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
