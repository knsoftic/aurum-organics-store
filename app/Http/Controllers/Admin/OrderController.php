<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.orders.index', [
            'orders' => $this->filtered($request)->with('items')->latest()->paginate(20)->withQueryString(),
            'counts' => Order::selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'pendingVerification' => Order::where('payment_status', 'pending_verification')->count(),
        ]);
    }

    public function show(Order $order)
    {
        $order->load(['items.product', 'payments.verifier', 'payments.paymentMethod', 'histories.user', 'paymentMethod']);

        return view('admin.orders.show', [
            'order' => $order,
            'methods' => PaymentMethod::orderBy('sort_order')->get(),
        ]);
    }

    public function updateStatus(Request $request, Order $order, OrderService $orders)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::STATUSES))],
            'note' => 'nullable|string|max:1000',
        ]);

        $orders->updateStatus($order, $data['status'], $data['note'] ?? null, $request->boolean('notify'), $request->user()->id);

        return back()->with('success', 'Order status updated to '.Order::STATUSES[$data['status']].'.');
    }

    public function updateDetails(Request $request, Order $order)
    {
        $data = $request->validate([
            'customer_name' => 'required|string|max:120',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:150',
            'city' => 'required|string|max:80',
            'address' => 'required|string|max:500',
            'courier' => 'nullable|string|max:80',
            'tracking_number' => 'nullable|string|max:120',
            'admin_note' => 'nullable|string|max:2000',
        ]);

        $trackingChanged = $order->tracking_number !== ($data['tracking_number'] ?? null) && ! empty($data['tracking_number']);
        $order->update($data);

        if ($trackingChanged) {
            $order->addHistory(null, 'Shipment booked'.($order->courier ? ' with '.$order->courier : '').' — tracking #'.$order->tracking_number.'.', true, $request->user()->id);
        }

        return back()->with('success', 'Order details saved.');
    }

    public function approvePayment(Request $request, Payment $payment, PaymentService $payments)
    {
        abort_unless($payment->status === Payment::STATUS_PENDING, 422);
        $payments->approve($payment, $request->user()->id, $request->input('note'));

        return back()->with('success', 'Payment approved.');
    }

    public function rejectPayment(Request $request, Payment $payment, PaymentService $payments)
    {
        abort_unless($payment->status === Payment::STATUS_PENDING, 422);
        $data = $request->validate(['note' => 'nullable|string|max:500']);
        $payments->reject($payment, $request->user()->id, $data['note'] ?? null);

        return back()->with('success', 'Payment rejected.');
    }

    public function recordPayment(Request $request, Order $order, PaymentService $payments)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:'.max(1, $order->due_amount),
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'purpose' => ['required', Rule::in(array_keys(Payment::PURPOSES))],
            'reference' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:500',
        ]);

        $payments->record($order, $data, $request->user()->id);

        return back()->with('success', 'Payment recorded.');
    }

    public function markRefunded(Request $request, Order $order)
    {
        $order->update(['payment_status' => 'refunded']);
        $order->addHistory(null, 'Payment marked as refunded.', false, $request->user()->id);

        return back()->with('success', 'Order marked as refunded.');
    }

    public function proof(Payment $payment)
    {
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);

        return response()->file(Storage::disk('local')->path($payment->proof_path));
    }

    public function invoice(Order $order)
    {
        return view('admin.orders.invoice', ['order' => $order->load('items', 'payments')]);
    }

    public function export(Request $request)
    {
        $orders = $this->filtered($request)->with('items')->latest()->get();
        $filename = 'orders-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Order #', 'Date', 'Customer', 'Phone', 'Email', 'City', 'Address', 'Items', 'Qty', 'Subtotal',
                'Shipping', 'Discount', 'Total', 'Paid', 'COD Amount', 'Payment Method', 'Payment Status', 'Status', 'Courier', 'Tracking']);

            foreach ($orders as $o) {
                fputcsv($out, [
                    $o->order_number, $o->created_at->format('Y-m-d H:i'), $o->customer_name, $o->phone, $o->email, $o->city,
                    $o->address, $o->items->map(fn ($i) => $i->product_name.' x'.$i->quantity)->implode(', '), $o->items->sum('quantity'),
                    $o->subtotal, $o->shipping, (float) $o->discount + (float) $o->payment_discount, $o->total, $o->paid_amount,
                    $o->payment_type === 'cod' ? $o->due_amount : 0, $o->payment_method_name, $o->payment_status_label,
                    $o->status_label, $o->courier, $o->tracking_number,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroy(Order $order)
    {
        abort_unless(in_array($order->status, Order::STOCK_RELEASED, true), 422, 'Only cancelled or returned orders can be deleted.');

        foreach ($order->payments as $payment) {
            if ($payment->proof_path) {
                Storage::disk('local')->delete($payment->proof_path);
            }
        }
        $order->delete();

        return redirect()->route('admin.orders.index')->with('success', 'Order deleted.');
    }

    protected function filtered(Request $request): Builder
    {
        return Order::query()
            ->search($request->query('q'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->query('payment_status')))
            ->when($request->filled('method'), fn ($q) => $q->where('payment_method_id', $request->query('method')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->query('to')));
    }
}
