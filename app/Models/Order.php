<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'returned' => 'Returned',
    ];

    /** Statuses in which ordered stock is considered released back to inventory. */
    public const STOCK_RELEASED = ['cancelled', 'returned'];

    public const PAYMENT_STATUSES = [
        'unpaid' => 'Unpaid',
        'pending_verification' => 'Awaiting Verification',
        'advance_paid' => 'Advance Paid',
        'partially_paid' => 'Partially Paid',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
    ];

    protected $fillable = [
        'order_number', 'access_token', 'customer_name', 'phone', 'email', 'city', 'address', 'postal_code', 'notes',
        'subtotal', 'shipping', 'coupon_code', 'discount', 'payment_discount', 'payment_fee', 'total',
        'advance_amount', 'paid_amount', 'payment_method_id', 'payment_method_name', 'payment_type',
        'payment_status', 'status', 'courier', 'tracking_number', 'admin_note', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping' => 'decimal:2',
            'discount' => 'decimal:2',
            'payment_discount' => 'decimal:2',
            'payment_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'advance_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderHistory::class)->latest();
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('order_number', 'like', "%{$term}%")
                ->orWhere('customer_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('city', 'like', "%{$term}%");
        });
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status] ?? ucfirst($this->payment_status);
    }

    public function getDueAmountAttribute(): float
    {
        return max(0, round((float) $this->total - (float) $this->paid_amount, 2));
    }

    /**
     * What the customer still has to pay online before the order ships:
     * the advance for COD orders, the whole balance for prepaid orders.
     */
    public function getPayableNowAttribute(): float
    {
        if (in_array($this->status, self::STOCK_RELEASED, true)) {
            return 0;
        }

        $outstanding = $this->payment_type === PaymentMethod::TYPE_COD
            ? (float) $this->advance_amount - (float) $this->paid_amount
            : (float) $this->total - (float) $this->paid_amount;

        return max(0, round($outstanding, 2));
    }

    public function hasPendingPayment(): bool
    {
        return $this->payments()->where('status', Payment::STATUS_PENDING)->exists();
    }

    /** Recalculate paid_amount and payment_status from the payment records. */
    public function syncPaymentStatus(): void
    {
        $paid = (float) $this->payments()->where('status', Payment::STATUS_APPROVED)->sum('amount');
        $this->paid_amount = $paid;

        if ($this->payment_status !== 'refunded') {
            $this->payment_status = match (true) {
                $paid > 0 && $paid >= (float) $this->total - 0.01 => 'paid',
                $paid > 0 && (float) $this->advance_amount > 0 && $paid >= (float) $this->advance_amount - 0.01 => 'advance_paid',
                $this->hasPendingPayment() => 'pending_verification',
                $paid > 0 => 'partially_paid',
                default => $this->payments()->where('status', Payment::STATUS_REJECTED)->exists() && $this->payment_type !== PaymentMethod::TYPE_COD
                    ? 'failed' : 'unpaid',
            };
        }

        $this->save();
    }

    public function publicUrl(): string
    {
        return route('order.show', ['order' => $this->order_number, 'key' => $this->access_token]);
    }

    public function addHistory(?string $status, ?string $note = null, bool $notify = false, ?int $userId = null): OrderHistory
    {
        return $this->histories()->create([
            'status' => $status,
            'note' => $note,
            'notify_customer' => $notify,
            'user_id' => $userId,
        ]);
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'pending', 'unpaid', 'pending_verification' => 'amber',
            'confirmed', 'processing', 'advance_paid', 'partially_paid' => 'blue',
            'shipped' => 'violet',
            'delivered', 'paid', 'approved' => 'green',
            'cancelled', 'returned', 'failed', 'rejected', 'refunded' => 'red',
            default => 'gray',
        };
    }
}
