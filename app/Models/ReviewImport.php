<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewImport extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['stored_path'];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'mapping' => 'array',
            'row_errors' => 'array',
            'is_demo' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
