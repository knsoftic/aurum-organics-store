<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $valid = fn ($q) => $q->whereNotIn('status', Order::STOCK_RELEASED);

        $days = collect(range(13, 0))->map(fn ($i) => Carbon::today()->subDays($i));
        $daily = Order::query()->tap($valid)
            ->where('created_at', '>=', $days->first())
            ->selectRaw('DATE(created_at) as day, SUM(total) as revenue, COUNT(*) as orders')
            ->groupBy('day')->get()->keyBy('day');

        $chart = $days->map(fn (Carbon $d) => [
            'label' => $d->format('d M'),
            'revenue' => (float) ($daily[$d->toDateString()]->revenue ?? 0),
            'orders' => (int) ($daily[$d->toDateString()]->orders ?? 0),
        ]);

        return view('admin.dashboard', [
            'stats' => [
                'revenue_month' => Order::query()->tap($valid)->where('created_at', '>=', now()->startOfMonth())->sum('total'),
                'revenue_today' => Order::query()->tap($valid)->whereDate('created_at', today())->sum('total'),
                'orders_today' => Order::whereDate('created_at', today())->count(),
                'orders_pending' => Order::where('status', 'pending')->count(),
                'payments_pending' => Payment::where('status', Payment::STATUS_PENDING)->count(),
                'collected' => Payment::where('status', Payment::STATUS_APPROVED)->where('created_at', '>=', now()->startOfMonth())->sum('amount'),
                'orders_total' => Order::count(),
                'low_stock' => Product::where('track_stock', true)->where('stock', '<=', (int) setting('low_stock_threshold', 5))->count(),
                'reviews_pending' => Review::where('is_approved', false)->count(),
                'messages_unread' => ContactMessage::where('is_read', false)->count(),
            ],
            'chart' => $chart,
            'statusBreakdown' => Order::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'recentOrders' => Order::latest()->take(8)->get(),
            'pendingPayments' => Payment::with('order')->where('status', Payment::STATUS_PENDING)->latest()->take(5)->get(),
            'topProducts' => Product::query()
                ->withSum(['orderItems as sold' => fn ($q) => $q->whereHas('order', $valid)], 'quantity')
                ->orderByDesc('sold')->take(5)->get(),
            'lowStock' => Product::where('track_stock', true)->where('stock', '<=', (int) setting('low_stock_threshold', 5))->orderBy('stock')->take(5)->get(),
        ]);
    }
}
