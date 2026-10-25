# Payment methods & gateways

The store supports three kinds of payment method, all managed from
**Admin → Payment Methods**.

| Type | Example | How it works |
|------|---------|--------------|
| `cod` | Cash on Delivery | Customer pays the rider. Optional **advance** (fixed amount or %) is paid upfront to one of your manual accounts. Marking the order *Delivered* records the remaining cash automatically. |
| `manual` | EasyPaisa, JazzCash, Bank transfer, USDT TRC20 | Customer sends money to the account details you enter, then submits a Transaction ID and a screenshot. You **Approve / Reject** it on the order page. No code needed — add as many accounts as you like. |
| `gateway` | Card, JazzCash / EasyPaisa merchant API, Safepay, Stripe, crypto processors | Fully automatic. Handled by a *driver* class. |

Each method can also have: a % discount (e.g. 5% off for paying in advance),
an extra fee, and a minimum / maximum order amount.

## Payment flow (data model)

* `orders.payment_status` is derived from the `payments` table by
  `Order::syncPaymentStatus()`: `unpaid → pending_verification → advance_paid / partially_paid → paid`.
* Every money movement is a row in `payments` (`pending`, `approved`, `rejected`)
  with amount, reference/TID, sender, screenshot (stored privately in
  `storage/app/private/payment-proofs`) and gateway payload.
* `App\Services\PaymentService` approves / rejects / records payments and applies
  gateway results. Approving the last outstanding payment of a *pending* order
  moves it to *confirmed*.

## Adding an online gateway

1. Create a driver in `app/Payments/Gateways`, extending `AbstractGateway`:

```php
namespace App\Payments\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Payments\GatewayResult;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JazzCashGateway extends AbstractGateway
{
    public static function label(): string
    {
        return 'JazzCash Merchant (Hosted Checkout)';
    }

    // Fields the admin fills in. "password" fields are stored encrypted and
    // never shown again; leaving them blank on edit keeps the saved value.
    public static function configFields(): array
    {
        return [
            'merchant_id' => ['label' => 'Merchant ID', 'type' => 'text'],
            'password' => ['label' => 'Password', 'type' => 'password'],
            'integrity_salt' => ['label' => 'Integrity salt', 'type' => 'password'],
            'mode' => ['label' => 'Mode', 'type' => 'select', 'options' => ['sandbox' => 'Sandbox', 'live' => 'Live']],
        ];
    }

    public function initiate(Order $order, Payment $payment): Response
    {
        // Build the signed request the gateway expects and either
        //  - return redirect()->away($hostedPageUrl), or
        //  - return response()->view('payments.autopost', [...]) with an auto-submitting form.
        // Use $this->config('merchant_id'), $this->callbackUrl(), $payment->id as your reference.
    }

    public function handleCallback(Request $request): GatewayResult
    {
        // 1. Verify the gateway's signature / hash with $this->config('integrity_salt').
        // 2. Find the Payment by the reference you sent (e.g. Payment::find($request->pp_BillReference)).
        // 3. return GatewayResult::success($payment, $request->pp_TxnRefNo, $request->all())
        //    or GatewayResult::failed($payment, 'Declined', $request->all());
    }

    // Optional: server-to-server notification (IPN). CSRF is already disabled for it.
    public function handleWebhook(Request $request): GatewayResult
    {
        return $this->handleCallback($request);
    }
}
```

2. Register it in `config/payments.php`:

```php
'drivers' => [
    'sandbox' => App\Payments\Gateways\SandboxGateway::class,
    'jazzcash' => App\Payments\Gateways\JazzCashGateway::class,
],
```

3. **Admin → Payment Methods → Add online gateway**, choose the driver, enter the
   credentials, and give the gateway these URLs (shown on the edit screen):
   * Return / callback URL: `/payment/{code}/callback` (GET or POST)
   * Webhook / IPN URL: `/payment/{code}/webhook` (POST)

Applying results is idempotent: only *pending* payments are updated, so a
callback and a webhook for the same transaction will not double-count.

`SandboxGateway` is a complete working example (fake hosted page → signed
callback → order marked paid). Enable the **Debit / Credit Card** method in the
admin to try it, and disable it again before going live.
