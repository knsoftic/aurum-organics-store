<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Payments\GatewayResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * A fake hosted payment page for testing the full online-payment flow
 * (redirect → pay/cancel → callback → order marked paid) without a real
 * merchant account. Use it as the template for real gateway drivers.
 */
class SandboxGateway extends AbstractGateway
{
    public static function label(): string
    {
        return 'Sandbox (test gateway)';
    }

    public static function configFields(): array
    {
        return [
            'merchant_name' => [
                'label' => 'Merchant name shown on the test page',
                'type' => 'text',
                'help' => 'Real drivers would ask for Merchant ID, API key, secret, etc.',
            ],
        ];
    }

    public function initiate(Order $order, Payment $payment): Response
    {
        return redirect()->to(URL::temporarySignedRoute('payment.sandbox', now()->addMinutes(30), [
            'payment' => $payment->id,
            'method' => $this->method->code,
        ]));
    }

    public function handleCallback(Request $request): GatewayResult
    {
        $payment = Payment::find($request->integer('payment_id'));

        $expected = $payment ? self::signature($payment, (string) $request->input('result')) : null;
        if (! $payment || ! hash_equals((string) $expected, (string) $request->input('signature'))) {
            return GatewayResult::failed($payment, 'Invalid payment signature.');
        }

        if ($request->input('result') !== 'success') {
            return GatewayResult::failed($payment, 'Payment was cancelled.', $request->only('result'));
        }

        return GatewayResult::success($payment, 'SBX-'.strtoupper(bin2hex(random_bytes(4))), $request->only('result'));
    }

    /** Real gateways sign their callbacks; the sandbox mimics that with an HMAC. */
    public static function signature(Payment $payment, string $result): string
    {
        return hash_hmac('sha256', $payment->id.'|'.$payment->amount.'|'.$result, (string) config('app.key'));
    }

    public function merchantName(): string
    {
        return $this->config('merchant_name') ?: setting('store_name', config('app.name'));
    }
}
