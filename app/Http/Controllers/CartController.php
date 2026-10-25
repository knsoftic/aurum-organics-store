<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(protected CartService $cart)
    {
    }

    public function index()
    {
        return view('store.cart', [
            'items' => $this->cart->items(),
            'totals' => $this->cart->totals(),
            'freeShippingRemaining' => $this->cart->freeShippingRemaining(),
        ]);
    }

    public function add(Request $request, Product $product)
    {
        $data = $request->validate(['qty' => 'nullable|integer|min:1|max:99']);
        $qty = (int) ($data['qty'] ?? 1);

        if (! $product->is_active || ! $product->isInStock(1)) {
            return $this->respond($request, 'Sorry, this product is currently out of stock.', false);
        }

        $this->cart->add($product, $qty);

        if ($request->boolean('buy_now')) {
            return $request->wantsJson()
                ? response()->json(['redirect' => route('checkout')])
                : redirect()->route('checkout');
        }

        return $this->respond($request, $product->name.' added to your bag.');
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate(['qty' => 'required|integer|min:0|max:99']);
        $this->cart->update($product->id, min((int) $data['qty'], $product->maxPurchasable()));

        return $this->respond($request, 'Your bag has been updated.');
    }

    public function remove(Request $request, Product $product)
    {
        $this->cart->remove($product->id);

        return $this->respond($request, $product->name.' removed from your bag.');
    }

    public function applyCoupon(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:50']);

        if ($error = $this->cart->applyCoupon($data['code'])) {
            return back()->withErrors(['code' => $error])->withInput();
        }

        return back()->with('success', 'Coupon applied successfully.');
    }

    public function removeCoupon()
    {
        $this->cart->removeCoupon();

        return back()->with('success', 'Coupon removed.');
    }

    protected function respond(Request $request, string $message, bool $ok = true)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'ok' => $ok,
                'message' => $message,
                'count' => $this->cart->count(),
                'drawer' => view('store.partials.cart-drawer-body', [
                    'items' => $this->cart->items(),
                    'totals' => $this->cart->totals(),
                    'freeShippingRemaining' => $this->cart->freeShippingRemaining(),
                ])->render(),
            ], $ok ? 200 : 422);
        }

        return back()->with($ok ? 'success' : 'error', $message);
    }
}
