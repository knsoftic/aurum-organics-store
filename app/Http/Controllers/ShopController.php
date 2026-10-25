<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $activeCount = Product::active()->count();
        if ($activeCount === 1 && ! $request->filled('q')) {
            return redirect()->route('product.show', Product::active()->first());
        }

        $categories = Category::active()->withCount(['products' => fn ($q) => $q->active()])->get();
        $category = $request->filled('category') ? $categories->firstWhere('slug', $request->query('category')) : null;

        $query = Product::active()->withRating()->with('category')
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->query('q');
                $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('short_description', 'like', "%{$term}%"));
            });

        match ($request->query('sort')) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'newest' => $query->latest(),
            default => $query->sorted(),
        };

        return view('store.shop', [
            'products' => $query->paginate(12)->withQueryString(),
            'categories' => $categories,
            'category' => $category ?? ($categories->count() === 1 ? $categories->first() : null),
        ]);
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);

        $product->load(['images', 'category'])->loadAvg('approvedReviews', 'rating')->loadCount('approvedReviews');

        return view('store.product', [
            'product' => $product,
            'reviews' => $product->approvedReviews()->take(12)->get(),
            'ratingBreakdown' => $product->approvedReviews()->reorder()->selectRaw('rating, count(*) as total')->groupBy('rating')->pluck('total', 'rating'),
            'related' => Product::active()->withRating()->whereKeyNot($product->id)
                ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
                ->sorted()->take(4)->get(),
        ]);
    }
}
