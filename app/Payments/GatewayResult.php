<?php

namespace App\Payments;

use App\Models\Payment;

/**
 * Outcome of a gateway callback/webhook. The PaymentService applies it to the
 * Payment record and the order (approve / reject + payment status sync).
 */
class GatewayResult
{
    public function __construct(
        public readonly ?Payment $payment,
        public readonly bool $success,
        public readonly ?string $reference = null,
        public readonly ?string $message = null,
        public readonly array $payload = [],
    ) {
    }

    public static function success(Payment $payment, ?string $reference = null, array $payload = []): self
    {
        return new self($payment, true, $reference, null, $payload);
    }

    public static function failed(?Payment $payment, string $message, array $payload = []): self
    {
        return new self($payment, false, null, $message, $payload);
    }
}
