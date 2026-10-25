<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\TrackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/shop', [ShopController::class, 'index'])->name('shop');
Route::get('/product/{product:slug}', [ShopController::class, 'show'])->name('product.show');
Route::post('/product/{product:slug}/reviews', [PageController::class, 'storeReview'])->name('reviews.store')->middleware('throttle:5,10');

Route::controller(CartController::class)->prefix('cart')->name('cart.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/coupon', 'applyCoupon')->name('coupon');
    Route::delete('/coupon', 'removeCoupon')->name('coupon.remove');
    Route::post('/{product}', 'add')->name('add');
    Route::patch('/{product}', 'update')->name('update');
    Route::delete('/{product}', 'remove')->name('remove');
});

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store')->middleware('throttle:10,1');

Route::get('/track-order', [OrderController::class, 'track'])->name('track');
Route::post('/track-order', [OrderController::class, 'lookup'])->name('track.lookup')->middleware('throttle:10,1');
Route::get('/order/{order:order_number}', [OrderController::class, 'show'])->name('order.show');
Route::post('/order/{order:order_number}/payment', [OrderController::class, 'submitPayment'])->name('order.payment')->middleware('throttle:10,1');
Route::get('/order/{order:order_number}/pay', [PaymentController::class, 'pay'])->name('order.pay');

// Online gateways
Route::get('/payment/sandbox/{payment}', [PaymentController::class, 'sandbox'])->name('payment.sandbox')->middleware('signed');
Route::match(['get', 'post'], '/payment/{method:code}/callback', [PaymentController::class, 'callback'])->name('payment.callback');
Route::post('/payment/{method:code}/webhook', [PaymentController::class, 'webhook'])->name('payment.webhook');

Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'sendContact'])->name('contact.send')->middleware('throttle:5,10');
Route::get('/pages/{page:slug}', [PageController::class, 'show'])->name('page.show');

// SEO files and the visitor counter beacon
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/ads.txt', [SeoController::class, 'adsTxt'])->name('seo.ads');
Route::post('/t', [TrackController::class, 'collect'])->name('track.collect')->middleware('throttle:120,1');

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [Admin\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [Admin\AuthController::class, 'login'])->name('login.attempt');

    Route::middleware('admin')->group(function () {
        Route::post('/logout', [Admin\AuthController::class, 'logout'])->name('logout');
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::get('/profile', [Admin\AuthController::class, 'profile'])->name('profile');
        Route::put('/profile', [Admin\AuthController::class, 'updateProfile'])->name('profile.update');

        Route::resource('products', Admin\ProductController::class)->except('show');
        Route::delete('products/{product}/images/{image}', [Admin\ProductController::class, 'destroyImage'])->name('products.images.destroy');
        Route::resource('categories', Admin\CategoryController::class)->except('show');

        Route::get('orders/export', [Admin\OrderController::class, 'export'])->name('orders.export');
        Route::resource('orders', Admin\OrderController::class)->only(['index', 'show', 'destroy']);
        Route::controller(Admin\OrderController::class)->group(function () {
            Route::patch('orders/{order}/status', 'updateStatus')->name('orders.status');
            Route::patch('orders/{order}/details', 'updateDetails')->name('orders.details');
            Route::post('orders/{order}/payments', 'recordPayment')->name('orders.payments.record');
            Route::post('orders/{order}/refunded', 'markRefunded')->name('orders.refunded');
            Route::get('orders/{order}/invoice', 'invoice')->name('orders.invoice');
            Route::post('payments/{payment}/approve', 'approvePayment')->name('payments.approve');
            Route::post('payments/{payment}/reject', 'rejectPayment')->name('payments.reject');
            Route::get('payments/{payment}/proof', 'proof')->name('payments.proof');
        });

        Route::resource('payment-methods', Admin\PaymentMethodController::class)->except('show');
        Route::patch('payment-methods/{payment_method}/toggle', [Admin\PaymentMethodController::class, 'toggle'])->name('payment-methods.toggle');
        Route::resource('coupons', Admin\CouponController::class)->except('show');

        Route::get('reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
        Route::post('reviews', [Admin\ReviewController::class, 'store'])->name('reviews.store');
        Route::patch('reviews/{review}/toggle', [Admin\ReviewController::class, 'toggle'])->name('reviews.toggle');
        Route::delete('reviews/{review}', [Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');

        Route::resource('pages', Admin\PageController::class)->except('show');
        Route::resource('messages', Admin\MessageController::class)->only(['index', 'show', 'destroy']);

        Route::controller(Admin\AdStudioController::class)->prefix('ad-studio')->name('ad-studio.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('custom', 'storeCustom')->name('store-custom');
            Route::get('{adProject}', 'show')->name('show');
            Route::put('{adProject}', 'update')->name('update');
            Route::delete('{adProject}', 'destroy')->name('destroy');
            Route::post('{adProject}/regenerate', 'regenerate')->name('regenerate');
            Route::post('{adProject}/ai-script', 'aiScript')->name('ai-script');
            Route::post('{adProject}/media', 'uploadMedia')->name('media');
            Route::post('{adProject}/tts', 'tts')->name('tts');
            Route::post('{adProject}/voice', 'voiceUpload')->name('voice');
            Route::delete('{adProject}/voice', 'voiceDelete')->name('voice.delete');
            Route::post('{adProject}/renders/chunk', 'renderChunk')->name('renders.chunk');
            Route::delete('renders/{adRender}', 'destroyRender')->name('renders.destroy');
        });

        Route::prefix('marketing')->name('marketing.')->group(function () {
            Route::get('traffic', [Admin\TrafficController::class, 'index'])->name('traffic');
            Route::get('traffic/log', [Admin\TrafficController::class, 'log'])->name('traffic.log');
            Route::get('traffic/log/{visit}', [Admin\TrafficController::class, 'visit'])->name('traffic.visit');
            Route::get('system-log', [Admin\TrafficController::class, 'systemLog'])->name('system-log');

            Route::controller(Admin\KeywordController::class)->prefix('keywords')->name('keywords.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::post('sync', 'sync')->name('sync');
                Route::post('suggestions', 'addSuggestions')->name('suggestions');
                Route::put('{keyword}', 'update')->name('update');
                Route::delete('{keyword}', 'destroy')->name('destroy');
                Route::post('{keyword}/rank', 'rank')->name('rank');
            });

            Route::get('seo', [Admin\SeoController::class, 'index'])->name('seo');
            Route::post('seo/checklist', [Admin\SeoController::class, 'checklist'])->name('seo.checklist');

            Route::get('tracking', [Admin\TrackingController::class, 'edit'])->name('tracking');
            Route::put('tracking', [Admin\TrackingController::class, 'update'])->name('tracking.update');
            Route::post('tracking/geoip', [Admin\TrackingController::class, 'downloadGeoip'])->name('tracking.geoip');
        });

        Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
    });
});
