<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Payments\GatewayResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaymentService
{
    /** Record a payment the customer made to a manual account (EasyPaisa, bank, USDT ...). */
    public function submitManual(Order $order, PaymentMethod $method, float $amount, string $purpose, array $data, ?UploadedFile $proof): Payment
    {
        $payment = $order->payments()->create([
            'payment_method_id' => $method->id,
            'method_name' => $method->name,
            'purpose' => $purpose,
            'amount' => $amount,
            'reference' => $data['reference'] ?? null,
            'sender_account' => $data['sender_account'] ?? null,
            'proof_path' => $proof ? $this->storeProof($proof) : null,
            'status' => Payment::STATUS_PENDING,
        ]);

        $order->addHistory(null, sprintf('%s of %s submitted via %s%s — awaiting verification.',
            $payment->purpose_label, money($amount), $method->name,
            $payment->reference ? ' (Ref: '.$payment->reference.')' : ''));

        $order->syncPaymentStatus();

        return $payment;
    }

    public function storeProof(UploadedFile $file): string
    {
        return Storage::disk('local')->putFile('payment-proofs/'.now()->format('Y/m'), $file);
    }

    public function approve(Payment $payment, ?int $userId = null, ?string $note = null): void
    {
        DB::transaction(function () use ($payment, $userId, $note) {
            $payment->update([
                'status' => Payment::STATUS_APPROVED,
                'verified_by' => $userId,
                'verified_at' => now(),
                'note' => $note ?: $payment->note,
            ]);

            $order = $payment->order;
            $order->syncPaymentStatus();
            $order->addHistory(null, 'Payment of '.money($payment->amount).' via '.$payment->method_name.' verified.'.($note ? ' '.$note : ''), false, $userId);

            if ($order->status === 'pending' && $order->payable_now <= 0) {
                $order->update(['status' => 'confirmed']);
                $order->addHistory('confirmed', 'Order confirmed after payment verification.', false, $userId);
            }
        });
    }

    public function reject(Payment $payment, ?int $userId = null, ?string $note = null): void
    {
        DB::transaction(function () use ($payment, $userId, $note) {
            $payment->update([
                'status' => Payment::STATUS_REJECTED,
                'verified_by' => $userId,
                'verified_at' => now(),
                'note' => $note,
            ]);

            $order = $payment->order;
            $order->syncPaymentStatus();
            $order->addHistory(null, 'Payment of '.money($payment->amount).' via '.$payment->method_name.' was rejected.'.($note ? ' Reason: '.$note : ''), true, $userId);
        });
    }

    /** Admin records a payment directly (cash collected, bank deposit seen in statement, ...). */
    public function record(Order $order, array $data, ?int $userId = null): Payment
    {
        $method = isset($data['payment_method_id']) ? PaymentMethod::find($data['payment_method_id']) : null;

        $payment = $order->payments()->create([
            'payment_method_id' => $method?->id,
            'method_name' => $method?->name ?? 'Cash',
            'purpose' => $data['purpose'] ?? 'balance',
            'amount' => $data['amount'],
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'status' => Payment::STATUS_APPROVED,
            'verified_by' => $userId,
            'verified_at' => now(),
        ]);

        $order->syncPaymentStatus();
        $order->addHistory(null, 'Payment of '.money($payment->amount).' recorded ('.$payment->method_name.').', false, $userId);

        return $payment;
    }

    /** Apply the outcome of an online gateway callback / webhook. Idempotent. */
    public function applyGatewayResult(GatewayResult $result): void
    {
        $payment = $result->payment;
        if (! $payment || $payment->status !== Payment::STATUS_PENDING) {
            return;
        }

        $payment->update([
            'reference' => $result->reference ?? $payment->reference,
            'gateway_payload' => $result->payload ?: null,
        ]);

        if ($result->success) {
            $this->approve($payment, null, 'Confirmed automatically by '.$payment->method_name.'.');
        } else {
            $this->reject($payment, null, $result->message);
        }
    }
}
