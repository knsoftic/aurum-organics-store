<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function show(Page $page)
    {
        abort_unless($page->is_active, 404);

        return view('store.page', ['page' => $page]);
    }

    public function contact()
    {
        return view('store.contact');
    }

    public function sendContact(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:30',
            'subject' => 'nullable|string|max:150',
            'message' => 'required|string|max:3000',
            'website' => 'prohibited', // honeypot
        ]);

        ContactMessage::create($data);

        return back()->with('success', 'Thank you for reaching out — we will get back to you shortly.');
    }

    public function storeReview(Request $request, Product $product)
    {
        abort_unless($product->is_active, 404);

        $data = $request->validate([
            'name' => 'required|string|max:80',
            'city' => 'nullable|string|max:60',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:10|max:1500',
            'website' => 'prohibited',
        ]);

        $autoApprove = (bool) setting('auto_approve_reviews', false);
        $product->reviews()->create($data + ['is_approved' => $autoApprove]);

        return redirect()->to(route('product.show', $product).'#reviews')->with('success', $autoApprove
            ? 'Thank you! Your review has been published.'
            : 'Thank you! Your review will appear once it has been approved.');
    }
}
