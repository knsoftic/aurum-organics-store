<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visit extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'last_seen_at' => 'datetime', 'is_bot' => 'boolean', 'revenue' => 'decimal:2'];
    }

    public function pages(): HasMany
    {
        return $this->hasMany(PageView::class)->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(VisitEvent::class)->orderBy('id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getDurationAttribute(): int
    {
        return max(0, $this->last_seen_at->diffInSeconds($this->started_at, true));
    }

    public function getFlagAttribute(): string
    {
        return country_flag($this->country);
    }
}
