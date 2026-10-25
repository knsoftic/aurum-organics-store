<?php

/*
|--------------------------------------------------------------------------
| Online payment gateway drivers
|--------------------------------------------------------------------------
|
| Cash on Delivery and manual methods (EasyPaisa, JazzCash, bank transfer,
| USDT TRC20 ...) are created from Admin → Payment Methods and need no code.
|
| Automatic gateways are code drivers. To add one (e.g. JazzCash/EasyPaisa
| merchant API, Safepay, PayFast, Stripe, a crypto processor):
|   1. Create a class in app/Payments/Gateways that extends AbstractGateway.
|   2. Register it below with a short key.
|   3. Admin → Payment Methods → Add → type "Online Gateway", pick the driver
|      and fill in the credentials the driver asks for (stored encrypted).
|
| See docs/PAYMENT_GATEWAYS.md for a full walkthrough.
|
*/

return [
    'drivers' => [
        'sandbox' => App\Payments\Gateways\SandboxGateway::class,
        // 'jazzcash' => App\Payments\Gateways\JazzCashGateway::class,
        // 'easypaisa' => App\Payments\Gateways\EasyPaisaGateway::class,
        // 'stripe' => App\Payments\Gateways\StripeGateway::class,
    ],
];
