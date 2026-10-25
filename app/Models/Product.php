<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'name', 'slug', 'sku', 'size_label', 'badge', 'short_description', 'description',
        'highlights', 'price', 'compare_price', 'stock', 'track_stock', 'image', 'videos', 'is_active', 'is_featured',
        'sort_order', 'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'highlights' => 'array',
            'videos' => 'array',
            'price' => 'decimal:2',
            'compare_price' => 'decimal:2',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('is_approved', true)->latest();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSorted(Builder $query): Builder
    {
        return $query->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id');
    }

    /** Adds approved_reviews_avg_rating / approved_reviews_count for listings. */
    public function scopeWithRating(Builder $query): Builder
    {
        return $query->withAvg('approvedReviews', 'rating')->withCount('approvedReviews');
    }

    public function getImageUrlAttribute(): string
    {
        return media_url($this->image);
    }

    /** Main image followed by gallery images. */
    public function getGalleryAttribute(): array
    {
        $urls = $this->image ? [media_url($this->image)] : [];
        foreach ($this->images as $image) {
            $urls[] = media_url($image->path);
        }

        return $urls ?: [media_url(null)];
    }

    /** @return list<string> */
    public function getVideoUrlsAttribute(): array
    {
        return array_map(fn ($path) => media_url($path), $this->videos ?? []);
    }

    public function getDiscountPercentAttribute(): int
    {
        if (! $this->compare_price || (float) $this->compare_price <= (float) $this->price) {
            return 0;
        }

        return (int) round((($this->compare_price - $this->price) / $this->compare_price) * 100);
    }

    public function isInStock(int $quantity = 1): bool
    {
        return ! $this->track_stock || $this->stock >= $quantity;
    }

    public function maxPurchasable(): int
    {
        return $this->track_stock ? max(0, min($this->stock, 99)) : 99;
    }

    public function getRatingAttribute(): float
    {
        $avg = $this->approved_reviews_avg_rating ?? $this->approvedReviews()->avg('rating');

        return round((float) $avg, 1);
    }

    public function getRatingCountAttribute(): int
    {
        return (int) ($this->approved_reviews_count ?? $this->approvedReviews()->count());
    }
}
