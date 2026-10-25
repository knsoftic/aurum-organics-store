<?php

namespace App\Payments\Gateways;

use App\Models\PaymentMethod;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\GatewayResult;
use Illuminate\Http\Request;

abstract class AbstractGateway implements PaymentGateway
{
    public function __construct(protected PaymentMethod $method)
    {
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->method->configValue($key, $default);
    }

    /** URL the gateway sends the customer back to. */
    protected function callbackUrl(): string
    {
        return route('payment.callback', $this->method->code);
    }

    /** URL for server-to-server notifications (CSRF-exempt). */
    protected function webhookUrl(): string
    {
        return route('payment.webhook', $this->method->code);
    }

    public function handleWebhook(Request $request): GatewayResult
    {
        return GatewayResult::failed(null, 'Webhooks are not supported by this gateway.');
    }
}
