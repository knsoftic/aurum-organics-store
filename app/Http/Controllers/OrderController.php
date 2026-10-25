<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function show(Request $request, Order $order)
    {
        $this->authorizeAccess($request, $order);

        $order->load(['items', 'payments', 'histories', 'paymentMethod']);

        return view('store.order', [
            'order' => $order,
            'payMethods' => $this->payMethodsFor($order),
            'justPlaced' => session('order_placed', false),
        ]);
    }

    public function submitPayment(Request $request, Order $order, PaymentService $payments)
    {
        $this->authorizeAccess($request, $order);

        if ($order->payable_now <= 0 || $order->hasPendingPayment()) {
            return redirect()->to($order->publicUrl())->with('error', 'There is no payment due on this order right now.');
        }

        $method = $this->payMethodsFor($order)->firstWhere('id', $request->integer('payment_method_id'));
        if (! $method) {
            return back()->withErrors(['payment_method_id' => 'Please choose the account you paid to.']);
        }

        $data = $request->validate([
            'reference' => 'required|string|max:120',
            'sender_account' => 'nullable|string|max:120',
            'proof' => ($method->require_proof ? 'required' : 'nullable').'|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ], [
            'reference.required' => 'Please enter the transaction ID / reference.',
            'proof.required' => 'Please upload a screenshot of your payment.',
        ]);

        $purpose = match (true) {
            $order->payment_type === PaymentMethod::TYPE_COD => 'advance',
            (float) $order->paid_amount > 0 => 'balance',
            default => 'full',
        };

        $payments->submitManual($order, $method, $order->payable_now, $purpose, $data, $request->file('proof'));

        return redirect()->to($order->publicUrl())->with('success', 'Thank you! Your payment details were received and will be verified shortly.');
    }

    public function track()
    {
        return view('store.track');
    }

    public function lookup(Request $request)
    {
        $data = $request->validate([
            'order_number' => 'required|string|max:40',
            'phone' => 'required|string|max:30',
        ]);

        $order = Order::where('order_number', strtoupper(trim($data['order_number'])))->first();
        $digits = fn ($v) => substr(preg_replace('/\D+/', '', (string) $v), -10);

        if (! $order || $digits($order->phone) !== $digits($data['phone'])) {
            return back()->withInput()->withErrors(['order_number' => 'We could not find an order with these details.']);
        }

        return redirect()->to($order->publicUrl());
    }

    protected function authorizeAccess(Request $request, Order $order): void
    {
        abort_unless(hash_equals($order->access_token, (string) $request->query('key')), 404);
    }

    /** Manual accounts the customer can pay the outstanding amount to. */
    protected function payMethodsFor(Order $order)
    {
        $manual = PaymentMethod::active()->where('type', PaymentMethod::TYPE_MANUAL)->get();

        if ($order->paymentMethod?->isManual()) {
            return $manual->sortByDesc(fn ($m) => $m->id === $order->payment_method_id)->values();
        }

        return $manual;
    }
}
