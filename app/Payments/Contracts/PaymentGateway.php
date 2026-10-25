<?php

namespace App\Payments\Contracts;

use App\Models\Order;
use App\Models\Payment;
use App\Payments\GatewayResult;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface PaymentGateway
{
    /** Name shown in the admin driver dropdown. */
    public static function label(): string;

    /**
     * Credentials/options the admin fills in for this driver.
     * Format: ['key' => ['label' => 'Merchant ID', 'type' => 'text|password|textarea|select', 'options' => [], 'help' => '']]
     */
    public static function configFields(): array;

    /**
     * Start a payment for the given pending Payment record. Return a redirect to the
     * gateway's hosted page, or a view that auto-posts a form to the gateway.
     */
    public function initiate(Order $order, Payment $payment): Response;

    /** The customer was sent back from the gateway (return/callback URL). */
    public function handleCallback(Request $request): GatewayResult;

    /** Server-to-server notification (IPN / webhook). */
    public function handleWebhook(Request $request): GatewayResult;
}
