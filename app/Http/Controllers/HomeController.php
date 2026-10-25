<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Review;

class HomeController extends Controller
{
    public function index()
    {
        $products = Product::active()->withRating()->with('category')->sorted()->get();

        $hero = $products->firstWhere('id', (int) setting('featured_product_id'))
            ?? $products->firstWhere('is_featured', true)
            ?? $products->first();

        return view('store.home', [
            'products' => $products,
            'hero' => $hero,
            'category' => Category::active()->first(),
            'reviews' => Review::with('product')->where('is_approved', true)->latest()->take(6)->get(),
            'paymentMethods' => PaymentMethod::active()->get(),
            'singleMode' => setting('store_mode', 'multi') === 'single' || $products->count() === 1,
        ]);
    }
}
