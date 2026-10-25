<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Page;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Review;
use App\Payments\GatewayManager;
use App\Services\CartService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CartService::class);
        $this->app->singleton(GatewayManager::class);
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Paginator::defaultView('partials.pagination');

        // Behind SSL-terminating proxies (Cloudflare, load balancers) the request looks like
        // plain HTTP; keep generated links on HTTPS when the site URL is HTTPS.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        View::composer('layouts.store', function ($view) {
            $cart = app(CartService::class);

            $view->with([
                'cartCount' => $cart->count(),
                'cartItems' => $cart->items(),
                'cartTotals' => $cart->totals(),
                'cartFreeShippingRemaining' => $cart->freeShippingRemaining(),
                'navCategories' => Category::active()->get(),
                'footerPages' => Page::where('is_active', true)->where('show_in_footer', true)->orderBy('title')->get(['title', 'slug']),
                'footerPayments' => PaymentMethod::active()->get(),
            ]);
        });

        View::composer('layouts.admin', function ($view) {
            $view->with('adminBadges', [
                'orders' => Order::where('status', 'pending')->count(),
                'payments' => Payment::where('status', Payment::STATUS_PENDING)->count(),
                'reviews' => Review::where('is_approved', false)->count(),
                'messages' => ContactMessage::where('is_read', false)->count(),
            ]);
        });
    }
}
