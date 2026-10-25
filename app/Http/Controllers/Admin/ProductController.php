<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use HandlesUploads;

    public function index(Request $request)
    {
        $products = Product::with('category')
            ->withSum('orderItems as sold', 'quantity')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$request->q.'%')->orWhere('sku', 'like', '%'.$request->q.'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->orderBy('sort_order')->latest('id')
            ->paginate(20)->withQueryString();

        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        return view('admin.products.form', [
            'product' => new Product(['is_active' => true, 'track_stock' => true, 'stock' => 100, 'category_id' => Category::value('id')]),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $product = Product::create($this->validated($request));
        $this->syncImages($request, $product);

        return redirect()->route('admin.products.edit', $product)->with('success', 'Product created.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.form', [
            'product' => $product->load('images'),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $product->update($this->validated($request, $product));
        $this->syncImages($request, $product);

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        if ($product->orderItems()->exists()) {
            $product->update(['is_active' => false]);

            return back()->with('success', 'This product has orders, so it was hidden from the store instead of deleted.');
        }

        $this->deleteUpload($product->image);
        foreach ($product->images as $image) {
            $this->deleteUpload($image->path);
        }
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    public function destroyImage(Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);
        $this->deleteUpload($image->path);
        $image->delete();

        return back()->with('success', 'Image removed.');
    }

    protected function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'slug' => ['nullable', 'string', 'max:160', Rule::unique('products', 'slug')->ignore($product)],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('products', 'sku')->ignore($product)],
            'category_id' => 'nullable|exists:categories,id',
            'size_label' => 'nullable|string|max:60',
            'badge' => 'nullable|string|max:40',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:20000',
            'highlights' => 'nullable|string|max:3000',
            'price' => 'required|numeric|min:0|max:9999999',
            'compare_price' => 'nullable|numeric|min:0|max:9999999|gte:price',
            'stock' => 'required|integer|min:0|max:1000000',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'meta_title' => 'nullable|string|max:160',
            'meta_description' => 'nullable|string|max:500',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'gallery.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ], ['compare_price.gte' => 'The compare-at price must be higher than the selling price.']);

        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $data['highlights'] = collect(preg_split('/\r\n|\n/', (string) ($data['highlights'] ?? '')))
            ->map(fn ($l) => trim($l))->filter()->values()->all();
        $data['track_stock'] = $request->boolean('track_stock');
        $data['is_active'] = $request->boolean('is_active');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['image'] = $this->replaceUpload($request->file('image'), $product?->image, 'products', $request->boolean('remove_image'));
        unset($data['gallery']);

        return $data;
    }

    protected function syncImages(Request $request, Product $product): void
    {
        $next = (int) $product->images()->max('sort_order');
        foreach ((array) $request->file('gallery', []) as $file) {
            $product->images()->create(['path' => $this->storeUpload($file, 'products'), 'sort_order' => ++$next]);
        }
    }
}
