@extends('layouts.admin')

@section('title', $product->exists ? 'Edit product' : 'New product')
@section('crumb')<a href="{{ route('admin.products.index') }}">Products</a>@endsection

@section('actions')
    @if ($product->exists)
        <a href="{{ route('product.show', $product) }}" target="_blank" class="btn btn-light btn-sm"><x-icon name="eye" /> View in store</a>
    @endif
@endsection

@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
    @csrf
    @if ($product->exists) @method('PUT') @endif

    <div class="grid grid-main">
        <div>
            <div class="card">
                <div class="card-head"><h3>Basic information</h3></div>
                <div class="card-body form-grid">
                    <div class="field full"><label>Product name</label><input class="input" name="name" value="{{ old('name', $product->name) }}" required maxlength="150"></div>
                    <div class="field"><label>URL slug</label><input class="input" name="slug" value="{{ old('slug', $product->slug) }}" placeholder="auto-generated from name"></div>
                    <div class="field"><label>SKU <span class="muted">(optional)</span></label><input class="input" name="sku" value="{{ old('sku', $product->sku) }}"></div>
                    <div class="field"><label>Size / weight label</label><input class="input" name="size_label" value="{{ old('size_label', $product->size_label) }}" placeholder="e.g. 500 g, 250 ml"></div>
                    <div class="field"><label>Badge</label><input class="input" name="badge" value="{{ old('badge', $product->badge) }}" placeholder="e.g. Bestseller, New, Limited"></div>
                    <div class="field full"><label>Short description</label><textarea class="textarea" name="short_description" style="min-height:70px" maxlength="500">{{ old('short_description', $product->short_description) }}</textarea><div class="hint">Shown under the price on the product page and in search results.</div></div>
                    <div class="field full"><label>Key highlights</label><textarea class="textarea" name="highlights" style="min-height:100px" placeholder="One per line">{{ old('highlights', implode("\n", $product->highlights ?? [])) }}</textarea><div class="hint">One benefit per line — shown with gold check marks.</div></div>
                    <div class="field full"><label>Full description</label><textarea class="textarea" name="description" style="min-height:220px">{{ old('description', $product->description) }}</textarea><div class="hint">Plain text, or simple HTML: &lt;p&gt; &lt;h3&gt; &lt;ul&gt;&lt;li&gt; &lt;strong&gt;.</div></div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>Images</h3></div>
                <div class="card-body">
                    <div class="field">
                        <label>Main image</label>
                        <div class="upload-box">
                            <img id="mainPreview" src="{{ $product->image_url }}" alt="">
                            <div>
                                <input type="file" name="image" accept="image/*" data-preview="#mainPreview">
                                <div class="hint">Square image recommended (1200×1200). JPG, PNG or WebP, max 4 MB.</div>
                                @if ($product->image)<label class="switch" style="margin-top:8px;font-weight:400"><input type="checkbox" name="remove_image" value="1"><span class="track"></span> Remove main image</label>@endif
                            </div>
                        </div>
                    </div>
                    <div class="field" style="margin-top:18px">
                        <label>Gallery images</label>
                        @if ($product->exists && $product->images->isNotEmpty())
                            <div class="gallery-grid" style="margin-bottom:12px">
                                @foreach ($product->images as $image)
                                    <div class="g">
                                        <img src="{{ $image->url }}" alt="">
                                        <button type="submit" form="del-img-{{ $image->id }}" title="Remove">×</button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <input type="file" name="gallery[]" accept="image/*" multiple>
                        <div class="hint">You can select several images at once. They appear as thumbnails on the product page.</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>Videos</h3></div>
                <div class="card-body">
                    @if ($product->videos)
                        <div class="gallery-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));margin-bottom:12px">
                            @foreach ($product->videos as $video)
                                <div class="g">
                                    <video src="{{ media_url($video) }}#t=0.5" preload="metadata" muted style="width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:8px;border:1px solid var(--line);background:#000"></video>
                                    <label class="switch" style="font-weight:400;margin-top:6px"><input type="checkbox" name="remove_videos[]" value="{{ $video }}"><span class="track"></span> Remove</label>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <input type="file" name="new_videos[]" accept="video/mp4,video/webm,video/quicktime" multiple>
                    <div class="hint">MP4 recommended, up to 50 MB each (your server's PHP upload limit must allow it). Shown in a "See it in action" section on the product and home pages.</div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>SEO</h3></div>
                <div class="card-body form-grid">
                    <div class="field full"><label>Meta title</label><input class="input" name="meta_title" value="{{ old('meta_title', $product->meta_title) }}" maxlength="160"></div>
                    <div class="field full"><label>Meta description</label><textarea class="textarea" name="meta_description" style="min-height:70px" maxlength="500">{{ old('meta_description', $product->meta_description) }}</textarea></div>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-head"><h3>Visibility</h3></div>
                <div class="card-body" style="display:grid;gap:14px">
                    <label class="switch"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))><span class="track"></span> Visible in store</label>
                    <label class="switch"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))><span class="track"></span> Featured product</label>
                    <div class="field"><label>Category</label>
                        <select class="select" name="category_id">
                            <option value="">— None —</option>
                            @foreach ($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field"><label>Sort order</label><input class="input" type="number" name="sort_order" value="{{ old('sort_order', $product->sort_order ?? 0) }}" min="0"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>Pricing</h3></div>
                <div class="card-body" style="display:grid;gap:14px">
                    <div class="field"><label>Selling price</label><div class="input-group"><span class="addon">{{ setting('currency_symbol', 'Rs.') }}</span><input class="input" type="number" step="0.01" min="0" name="price" value="{{ old('price', $product->price) }}" required></div></div>
                    <div class="field"><label>Compare-at price <span class="muted">(optional)</span></label><div class="input-group"><span class="addon">{{ setting('currency_symbol', 'Rs.') }}</span><input class="input" type="number" step="0.01" min="0" name="compare_price" value="{{ old('compare_price', $product->compare_price) }}"></div><div class="hint">Original price shown crossed out, e.g. for a sale.</div></div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>Inventory</h3></div>
                <div class="card-body" style="display:grid;gap:14px">
                    <label class="switch"><input type="checkbox" name="track_stock" value="1" @checked(old('track_stock', $product->track_stock))><span class="track"></span> Track stock quantity</label>
                    <div class="field"><label>Stock</label><input class="input" type="number" name="stock" min="0" value="{{ old('stock', $product->stock ?? 0) }}" required></div>
                </div>
            </div>

            <div class="card sticky-actions">
                <div class="card-body" style="display:grid;gap:10px">
                    <button type="submit" class="btn btn-primary btn-block">{{ $product->exists ? 'Save changes' : 'Create product' }}</button>
                    <a href="{{ route('admin.products.index') }}" class="btn btn-light btn-block">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

@if ($product->exists)
    @foreach ($product->images as $image)
        <form id="del-img-{{ $image->id }}" method="POST" action="{{ route('admin.products.images.destroy', [$product, $image]) }}" data-confirm="Remove this image?">
            @csrf @method('DELETE')
        </form>
    @endforeach
@endif
@endsection
