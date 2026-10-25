<?php

namespace App\Models;

use App\Payments\GatewayManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    public const TYPE_COD = 'cod';
    public const TYPE_MANUAL = 'manual';
    public const TYPE_GATEWAY = 'gateway';

    protected $fillable = [
        'name', 'code', 'type', 'driver', 'description', 'instructions', 'details', 'logo', 'qr_image',
        'require_reference', 'require_proof', 'discount_percent', 'fee_amount', 'advance_type', 'advance_value',
        'min_order', 'max_order', 'config', 'is_active', 'sort_order',
    ];

    protected $hidden = ['config'];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'config' => 'encrypted:array',
            'require_reference' => 'boolean',
            'require_proof' => 'boolean',
            'is_active' => 'boolean',
            'discount_percent' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'advance_value' => 'decimal:2',
            'min_order' => 'decimal:2',
            'max_order' => 'decimal:2',
        ];
    }

    public static function types(): array
    {
        return [
            self::TYPE_COD => 'Cash on Delivery',
            self::TYPE_MANUAL => 'Manual / Bank / Wallet / Crypto',
            self::TYPE_GATEWAY => 'Online Gateway (automatic)',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function isCod(): bool
    {
        return $this->type === self::TYPE_COD;
    }

    public function isManual(): bool
    {
        return $this->type === self::TYPE_MANUAL;
    }

    public function isGateway(): bool
    {
        return $this->type === self::TYPE_GATEWAY;
    }

    public function requiresAdvance(): bool
    {
        return $this->isCod() && $this->advance_type !== 'none' && (float) $this->advance_value > 0;
    }

    /** Can a customer pick this method for the given cart subtotal? */
    public function isAvailableFor(float $subtotal): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->min_order !== null && $subtotal < (float) $this->min_order) {
            return false;
        }
        if ($this->max_order !== null && (float) $this->max_order > 0 && $subtotal > (float) $this->max_order) {
            return false;
        }
        if ($this->isGateway() && ! app(GatewayManager::class)->has($this->driver)) {
            return false;
        }

        return true;
    }

    public function discountFor(float $amount): float
    {
        return round($amount * ((float) $this->discount_percent / 100));
    }

    public function feeFor(): float
    {
        return (float) $this->fee_amount;
    }

    /** Upfront amount for COD orders (e.g. delivery charges paid in advance). */
    public function advanceFor(float $total): float
    {
        if (! $this->requiresAdvance()) {
            return 0;
        }

        $advance = $this->advance_type === 'percent'
            ? round($total * ((float) $this->advance_value / 100))
            : (float) $this->advance_value;

        return min($advance, $total);
    }

    public function getLogoUrlAttribute(): string
    {
        if ($this->logo) {
            return media_url($this->logo);
        }

        $icon = match (true) {
            $this->isCod() => 'cod',
            str_contains($this->code, 'easypaisa') => 'easypaisa',
            str_contains($this->code, 'jazz') => 'jazzcash',
            str_contains($this->code, 'usdt') || str_contains($this->code, 'crypto') || str_contains($this->code, 'trc') => 'usdt',
            str_contains($this->code, 'bank') => 'bank',
            $this->isGateway() => 'card',
            default => 'wallet',
        };

        return asset("images/payments/{$icon}.svg");
    }

    public function getQrUrlAttribute(): ?string
    {
        return $this->qr_image ? media_url($this->qr_image) : null;
    }

    public function configValue(string $key, mixed $default = null): mixed
    {
        return ($this->config ?? [])[$key] ?? $default;
    }
}
