<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'category', 'brand', 'price', 'description', 'image_url'])]
class Product extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_demo' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(ReviewImport::class);
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(AnalysisRun::class);
    }
}
