<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.reviews.index', [
            'reviews' => Review::with('product')
                ->when($request->query('status') === 'pending', fn ($q) => $q->where('is_approved', false))
                ->when($request->query('status') === 'approved', fn ($q) => $q->where('is_approved', true))
                ->latest()->paginate(20)->withQueryString(),
            'products' => Product::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Admin-added review (e.g. feedback received on WhatsApp). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'name' => 'required|string|max:80',
            'city' => 'nullable|string|max:60',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:1500',
        ]);

        Review::create($data + ['is_approved' => true]);

        return back()->with('success', 'Review added.');
    }

    public function toggle(Review $review)
    {
        $review->update(['is_approved' => ! $review->is_approved]);

        return back()->with('success', $review->is_approved ? 'Review published.' : 'Review hidden.');
    }

    public function destroy(Review $review)
    {
        $review->delete();

        return back()->with('success', 'Review deleted.');
    }
}
