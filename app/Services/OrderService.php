<?php

namespace App\Services;

use App\Mail\OrderPlacedMail;
use App\Mail\OrderStatusMail;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(protected CartService $cart, protected PaymentService $payments)
    {
    }

    /**
     * Turn the current cart into an order.
     *
     * $payment = ['method' => PaymentMethod, 'advance_method' => ?PaymentMethod,
     *             'reference' => ?string, 'sender_account' => ?string, 'proof' => ?UploadedFile]
     */
    public function place(array $customer, array $payment): Order
    {
        /** @var PaymentMethod $method */
        $method = $payment['method'];

        $order = DB::transaction(function () use ($customer, $payment, $method) {
            $items = $this->cart->items();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your bag is empty.']);
            }

            // Lock the rows so two customers cannot buy the last unit at the same time.
            $products = Product::whereIn('id', $items->pluck('product.id'))->lockForUpdate()->get()->keyBy('id');
            foreach ($items as $item) {
                $product = $products->get($item->product->id);
                if (! $product || ! $product->is_active || ! $product->isInStock($item->qty)) {
                    throw ValidationException::withMessages([
                        'cart' => "Sorry, {$item->product->name} is no longer available in that quantity.",
                    ]);
                }
            }

            $totals = $this->cart->totals($method);

            $order = Order::create([
                'order_number' => 'TMP-'.Str::uuid(),
                'access_token' => Str::random(40),
                'customer_name' => $customer['customer_name'],
                'phone' => $customer['phone'],
                'email' => $customer['email'] ?? null,
                'city' => $customer['city'],
                'address' => $customer['address'],
                'postal_code' => $customer['postal_code'] ?? null,
                'notes' => $customer['notes'] ?? null,
                'subtotal' => $totals['subtotal'],
                'shipping' => $totals['shipping'],
                'coupon_code' => $totals['coupon']?->code,
                'discount' => $totals['discount'],
                'payment_discount' => $totals['payment_discount'],
                'payment_fee' => $totals['payment_fee'],
                'total' => $totals['total'],
                'advance_amount' => $totals['advance'],
                'payment_method_id' => $method->id,
                'payment_method_name' => $method->name,
                'payment_type' => $method->type,
                'payment_status' => 'unpaid',
                'status' => 'pending',
                'ip_address' => request()->ip(),
            ]);

            $order->update(['order_number' => strtoupper(setting('order_prefix', 'AO')).'-'.(10000 + $order->id)]);

            foreach ($items as $item) {
                $product = $products->get($item->product->id);
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'size_label' => $product->size_label,
                    'image' => $product->image,
                    'price' => $product->price,
                    'quantity' => $item->qty,
                    'total' => $item->total,
                ]);

                if ($product->track_stock) {
                    $product->decrement('stock', $item->qty);
                }
            }

            if ($totals['coupon']) {
                Coupon::whereKey($totals['coupon']->id)->increment('used_count');
            }

            $order->addHistory('pending', 'Order placed by customer with '.$method->name.'.');

            $this->recordCheckoutPayment($order, $method, $payment);

            return $order;
        });

        $this->cart->clear();
        $this->sendPlacedMails($order);

        return $order->fresh();
    }

    protected function recordCheckoutPayment(Order $order, PaymentMethod $method, array $payment): void
    {
        $hasEvidence = ! empty($payment['reference']) || ! empty($payment['proof']);

        if ($method->isManual() && $hasEvidence) {
            $this->payments->submitManual($order, $method, (float) $order->total, 'full', $payment, $payment['proof'] ?? null);
        }

        if ($method->isCod() && $order->advance_amount > 0 && ! empty($payment['advance_method']) && $hasEvidence) {
            $this->payments->submitManual($order, $payment['advance_method'], (float) $order->advance_amount, 'advance', $payment, $payment['proof'] ?? null);
        }
    }

    /** Pending payment record for an online gateway attempt. */
    public function createGatewayPayment(Order $order): Payment
    {
        $method = $order->paymentMethod;

        return $order->payments()->create([
            'payment_method_id' => $method->id,
            'method_name' => $method->name,
            'purpose' => 'full',
            'amount' => $order->payable_now,
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    /**
     * Change the fulfilment status, keeping stock and COD cash in sync.
     */
    public function updateStatus(Order $order, string $status, ?string $note, bool $notify, ?int $userId): void
    {
        $previous = $order->status;

        DB::transaction(function () use ($order, $status, $note, $notify, $userId, $previous) {
            $wasReleased = in_array($previous, Order::STOCK_RELEASED, true);
            $isReleased = in_array($status, Order::STOCK_RELEASED, true);

            if (! $wasReleased && $isReleased) {
                $this->adjustStock($order, +1);
            } elseif ($wasReleased && ! $isReleased) {
                $this->adjustStock($order, -1);
            }

            $order->update(['status' => $status]);

            if ($previous !== $status || $note) {
                $order->addHistory($status, $note, $notify, $userId);
            }

            // Delivered COD order: the courier collected the remaining cash.
            if ($status === 'delivered' && $previous !== 'delivered' && $order->payment_type === PaymentMethod::TYPE_COD && $order->due_amount > 0) {
                $this->payments->record($order, [
                    'payment_method_id' => $order->payment_method_id,
                    'amount' => $order->due_amount,
                    'purpose' => 'cod',
                    'note' => 'Cash collected on delivery.',
                ], $userId);
            }
        });

        if ($notify && $order->email && $previous !== $status) {
            $this->safeMail(fn () => Mail::to($order->email)->send(new OrderStatusMail($order->fresh(), $note)));
        }
    }

    protected function adjustStock(Order $order, int $direction): void
    {
        foreach ($order->items as $item) {
            $product = $item->product;
            if ($product && $product->track_stock) {
                $direction > 0
                    ? $product->increment('stock', $item->quantity)
                    : $product->decrement('stock', min($item->quantity, $product->stock));
            }
        }
    }

    protected function sendPlacedMails(Order $order): void
    {
        $order->loadMissing('items');

        if ($order->email) {
            $this->safeMail(fn () => Mail::to($order->email)->send(new OrderPlacedMail($order)));
        }

        if ($adminEmail = setting('notify_email')) {
            $this->safeMail(fn () => Mail::to($adminEmail)->send(new OrderPlacedMail($order, true)));
        }
    }

    protected function safeMail(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            Log::warning('Order email failed: '.$e->getMessage());
        }
    }
}
