@extends('layouts.admin')

@section('title', 'Categories')

@section('actions')
    <a href="{{ route('admin.categories.create') }}" class="btn btn-gold btn-sm"><x-icon name="plus" /> Add category</a>
@endsection

@section('content')
<div class="alert alert-info">Your store is designed around a single category. You can still add more later — they appear as filters on the Shop page.</div>
<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th></th><th>Name</th><th class="num">Products</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td style="width:60px"><img src="{{ $category->image_url }}" class="thumb" alt=""></td>
                        <td><div class="cell-main">{{ $category->name }}</div><div class="cell-sub">/shop?category={{ $category->slug }}</div></td>
                        <td class="num">{{ $category->products_count }}</td>
                        <td>@if ($category->is_active)<span class="badge b-green">Active</span>@else<span class="badge b-gray">Hidden</span>@endif</td>
                        <td class="actions">
                            <a href="{{ route('admin.categories.edit', $category) }}" class="icon-action" title="Edit"><x-icon name="edit" /></a>
                            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="inline-form" data-confirm="Delete this category?">
                                @csrf @method('DELETE')
                                <button class="icon-action danger" title="Delete"><x-icon name="trash" /></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No categories yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
