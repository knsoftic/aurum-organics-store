<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\PaymentMethod;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Session cart: ['cart' => [product_id => qty], 'cart_coupon' => CODE].
 */
class CartService
{
    protected ?Collection $itemsCache = null;

    protected function raw(): array
    {
        return session('cart', []);
    }

    protected function store(array $cart): void
    {
        session(['cart' => $cart]);
        $this->itemsCache = null;
    }

    /** @return Collection<int, object{product: Product, qty: int, total: float}> */
    public function items(): Collection
    {
        if ($this->itemsCache !== null) {
            return $this->itemsCache;
        }

        $raw = $this->raw();
        if (! $raw) {
            return $this->itemsCache = collect();
        }

        $products = Product::active()->whereIn('id', array_keys($raw))->get()->keyBy('id');
        $clean = [];
        $items = collect();

        foreach ($raw as $id => $qty) {
            $product = $products->get($id);
            if (! $product || $product->maxPurchasable() < 1) {
                continue;
            }
            $qty = max(1, min((int) $qty, $product->maxPurchasable()));
            $clean[$id] = $qty;
            $items->push((object) [
                'product' => $product,
                'qty' => $qty,
                'total' => round((float) $product->price * $qty, 2),
            ]);
        }

        if ($clean !== $raw) {
            session(['cart' => $clean]);
        }

        return $this->itemsCache = $items;
    }

    public function add(Product $product, int $qty = 1): void
    {
        $cart = $this->raw();
        $cart[$product->id] = min(($cart[$product->id] ?? 0) + max(1, $qty), $product->maxPurchasable());
        $this->store($cart);
    }

    public function update(int $productId, int $qty): void
    {
        $cart = $this->raw();
        if ($qty < 1) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = $qty;
        }
        $this->store($cart);
    }

    public function remove(int $productId): void
    {
        $this->update($productId, 0);
    }

    public function clear(): void
    {
        session()->forget(['cart', 'cart_coupon']);
        $this->itemsCache = null;
    }

    public function count(): int
    {
        return (int) $this->items()->sum('qty');
    }

    public function isEmpty(): bool
    {
        return $this->items()->isEmpty();
    }

    public function subtotal(): float
    {
        return round((float) $this->items()->sum('total'), 2);
    }

    public function coupon(): ?Coupon
    {
        $code = session('cart_coupon');
        if (! $code) {
            return null;
        }

        $coupon = Coupon::where('code', $code)->first();
        if (! $coupon || $coupon->validationError($this->subtotal())) {
            session()->forget('cart_coupon');

            return null;
        }

        return $coupon;
    }

    /** Returns an error message or null on success. */
    public function applyCoupon(string $code): ?string
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();
        if (! $coupon) {
            return 'This coupon code is not valid.';
        }

        if ($error = $coupon->validationError($this->subtotal())) {
            return $error;
        }

        session(['cart_coupon' => $coupon->code]);

        return null;
    }

    public function removeCoupon(): void
    {
        session()->forget('cart_coupon');
    }

    public static function shippingFor(float $amount): float
    {
        $freeAbove = (float) setting('free_shipping_min', 0);
        if ($freeAbove > 0 && $amount >= $freeAbove) {
            return 0;
        }

        return (float) setting('shipping_fee', 0);
    }

    /** Full price breakdown, optionally for a specific payment method. */
    public function totals(?PaymentMethod $method = null): array
    {
        $subtotal = $this->subtotal();
        $coupon = $this->coupon();
        $discount = $coupon ? $coupon->discountFor($subtotal) : 0;
        $afterDiscount = max(0, $subtotal - $discount);
        $shipping = $subtotal > 0 ? self::shippingFor($afterDiscount) : 0;

        $paymentDiscount = $method ? $method->discountFor($afterDiscount) : 0;
        $paymentFee = $method ? $method->feeFor() : 0;
        $total = round(max(0, $afterDiscount + $shipping - $paymentDiscount + $paymentFee), 2);

        return [
            'subtotal' => $subtotal,
            'coupon' => $coupon,
            'discount' => $discount,
            'shipping' => $shipping,
            'payment_discount' => $paymentDiscount,
            'payment_fee' => $paymentFee,
            'total' => $total,
            'advance' => $method ? $method->advanceFor($total) : 0,
        ];
    }

    public function freeShippingRemaining(): ?float
    {
        $freeAbove = (float) setting('free_shipping_min', 0);
        if ($freeAbove <= 0 || (float) setting('shipping_fee', 0) <= 0) {
            return null;
        }

        $coupon = $this->coupon();
        $amount = $this->subtotal() - ($coupon ? $coupon->discountFor($this->subtotal()) : 0);

        return max(0, $freeAbove - $amount);
    }
}
