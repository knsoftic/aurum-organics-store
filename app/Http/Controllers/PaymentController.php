<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Payments\GatewayManager;
use App\Payments\Gateways\SandboxGateway;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(protected GatewayManager $gateways, protected PaymentService $payments)
    {
    }

    /** (Re)start an online payment for an order placed with a gateway method. */
    public function pay(Request $request, Order $order, OrderService $orders)
    {
        abort_unless(hash_equals($order->access_token, (string) $request->query('key')), 404);

        $method = $order->paymentMethod;
        if (! $method || ! $method->is_active || ! $method->isGateway() || $order->payable_now <= 0) {
            return redirect()->to($order->publicUrl())->with('error', 'Online payment is not available for this order.');
        }

        // Expire previous unfinished attempts so only one is pending at a time.
        $order->payments()->where('status', Payment::STATUS_PENDING)->where('payment_method_id', $method->id)
            ->update(['status' => Payment::STATUS_REJECTED, 'note' => 'Superseded by a new payment attempt.']);

        $payment = $orders->createGatewayPayment($order);

        return $this->gateways->make($method)->initiate($order, $payment);
    }

    public function callback(Request $request, PaymentMethod $method)
    {
        $result = $this->gateways->make($method)->handleCallback($request);
        $this->payments->applyGatewayResult($result);

        $order = $result->payment?->order;
        if (! $order) {
            return redirect()->route('home')->with('error', $result->message ?? 'Payment could not be verified.');
        }

        session()->flash('order_placed', true);

        return redirect()->to($order->publicUrl())->with(
            $result->success ? 'success' : 'error',
            $result->success ? 'Payment received — thank you!' : ($result->message ?? 'Payment was not completed.')
        );
    }

    public function webhook(Request $request, PaymentMethod $method)
    {
        $result = $this->gateways->make($method)->handleWebhook($request);
        $this->payments->applyGatewayResult($result);

        return response()->json(['received' => true]);
    }

    /** Fake hosted payment page used by the sandbox driver. */
    public function sandbox(Request $request, Payment $payment)
    {
        $method = $payment->paymentMethod;
        abort_unless($method && $method->driver === 'sandbox' && $payment->status === Payment::STATUS_PENDING, 404);

        return view('payments.sandbox', [
            'payment' => $payment,
            'order' => $payment->order,
            'merchant' => (new SandboxGateway($method))->merchantName(),
            'callback' => route('payment.callback', $method->code),
            'signatures' => [
                'success' => SandboxGateway::signature($payment, 'success'),
                'cancel' => SandboxGateway::signature($payment, 'cancel'),
            ],
        ]);
    }
}
