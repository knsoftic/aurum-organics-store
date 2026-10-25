<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Payments\GatewayManager;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(protected CartService $cart)
    {
    }

    public function index()
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your bag is empty.');
        }

        $methods = $this->availableMethods();

        return view('store.checkout', [
            'items' => $this->cart->items(),
            'totals' => $this->cart->totals($methods->first()),
            'methods' => $methods,
            'advanceMethods' => PaymentMethod::active()->where('type', PaymentMethod::TYPE_MANUAL)->get(),
            // Server-calculated breakdown per method so the summary updates instantly when the customer switches.
            'methodTotals' => $methods->mapWithKeys(fn ($m) => [$m->id => collect($this->cart->totals($m))->except('coupon')]),
        ]);
    }

    public function store(Request $request, OrderService $orders, GatewayManager $gateways)
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your bag is empty.');
        }

        $customer = $request->validate([
            'customer_name' => 'required|string|max:120',
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s()]{10,18}$/'],
            'email' => 'nullable|email|max:150',
            'city' => 'required|string|max:80',
            'address' => 'required|string|max:500',
            'postal_code' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:1000',
        ], ['phone.regex' => 'Please enter a valid mobile number, e.g. 0300 1234567.']);

        $request->validate([
            'payment_method_id' => 'required|integer',
            'reference' => 'nullable|string|max:120',
            'sender_account' => 'nullable|string|max:120',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'advance_method_id' => 'nullable|integer',
        ]);

        $subtotal = $this->cart->subtotal();
        $method = $this->availableMethods()->firstWhere('id', $request->integer('payment_method_id'));
        if (! $method) {
            throw ValidationException::withMessages(['payment_method_id' => 'Please choose an available payment method.']);
        }

        $minOrder = (float) setting('min_order_amount', 0);
        if ($minOrder > 0 && $subtotal < $minOrder) {
            throw ValidationException::withMessages(['cart' => 'The minimum order amount is '.money($minOrder).'.']);
        }

        $advanceMethod = null;
        if ($method->isManual()) {
            $request->validate([
                'reference' => $method->require_reference ? 'required|string|max:120' : 'nullable',
                'proof' => $method->require_proof ? 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120' : 'nullable',
            ], [
                'reference.required' => 'Please enter the transaction ID / reference of your payment.',
                'proof.required' => 'Please upload a screenshot of your payment.',
            ]);
        }

        if ($method->requiresAdvance()) {
            $advanceMethod = PaymentMethod::active()->where('type', PaymentMethod::TYPE_MANUAL)
                ->find($request->integer('advance_method_id'));

            if (! $advanceMethod) {
                throw ValidationException::withMessages(['advance_method_id' => 'Please choose the account you paid the advance to.']);
            }

            $request->validate([
                'reference' => 'required|string|max:120',
                'proof' => $advanceMethod->require_proof ? 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120' : 'nullable',
            ], [
                'reference.required' => 'Please enter the transaction ID of your advance payment.',
                'proof.required' => 'Please upload a screenshot of your advance payment.',
            ]);
        }

        $order = $orders->place($customer, [
            'method' => $method,
            'advance_method' => $advanceMethod,
            'reference' => $request->input('reference'),
            'sender_account' => $request->input('sender_account'),
            'proof' => $request->file('proof'),
        ]);

        session()->flash('order_placed', true);

        if ($method->isGateway() && $order->payable_now > 0) {
            $payment = $orders->createGatewayPayment($order);

            return $gateways->make($method)->initiate($order, $payment);
        }

        return redirect()->to($order->publicUrl());
    }

    protected function availableMethods()
    {
        $subtotal = $this->cart->subtotal();

        return PaymentMethod::active()->get()->filter(fn (PaymentMethod $m) => $m->isAvailableFor($subtotal))->values();
    }
}
