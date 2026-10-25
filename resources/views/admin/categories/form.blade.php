@extends('layouts.admin')

@section('title', $category->exists ? 'Edit category' : 'New category')
@section('crumb')<a href="{{ route('admin.categories.index') }}">Categories</a>@endsection

@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="card" style="max-width:760px">
    @csrf
    @if ($category->exists) @method('PUT') @endif
    <div class="card-body form-grid">
        <div class="field"><label>Name</label><input class="input" name="name" value="{{ old('name', $category->name) }}" required></div>
        <div class="field"><label>URL slug</label><input class="input" name="slug" value="{{ old('slug', $category->slug) }}" placeholder="auto"></div>
        <div class="field full"><label>Description</label><textarea class="textarea" name="description">{{ old('description', $category->description) }}</textarea><div class="hint">Shown at the top of the Shop page.</div></div>
        <div class="field full">
            <label>Image</label>
            <div class="upload-box">
                <img id="catPreview" src="{{ $category->image_url }}" alt="">
                <div>
                    <input type="file" name="image" accept="image/*" data-preview="#catPreview">
                    @if ($category->image)<label class="switch" style="margin-top:8px;font-weight:400"><input type="checkbox" name="remove_image" value="1"><span class="track"></span> Remove image</label>@endif
                </div>
            </div>
        </div>
        <div class="field"><label>Sort order</label><input class="input" type="number" name="sort_order" min="0" value="{{ old('sort_order', $category->sort_order ?? 0) }}"></div>
        <div class="field" style="align-self:end"><label class="switch"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active))><span class="track"></span> Active</label></div>
    </div>
    <div class="form-actions">
        <a href="{{ route('admin.categories.index') }}" class="btn btn-light">Cancel</a>
        <button class="btn btn-primary">{{ $category->exists ? 'Save changes' : 'Create category' }}</button>
    </div>
</form>
@endsection
