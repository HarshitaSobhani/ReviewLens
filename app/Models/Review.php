<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Guarded entirely: reviews are only created by the import pipeline,
 * never from request input.
 */
class Review extends Model
{
    use HasFactory;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'date:Y-m-d',
            'verified_purchase' => 'boolean',
            'is_demo' => 'boolean',
            'rating' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(ReviewImport::class, 'review_import_id');
    }
}
