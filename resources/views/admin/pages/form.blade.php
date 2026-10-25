@extends('layouts.admin')

@section('title', $page->exists ? 'Edit page' : 'New page')
@section('crumb')<a href="{{ route('admin.pages.index') }}">Pages</a>@endsection

@section('content')
<form method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="card" style="max-width:900px">
    @csrf
    @if ($page->exists) @method('PUT') @endif
    <div class="card-body form-grid">
        <div class="field"><label>Title</label><input class="input" name="title" value="{{ old('title', $page->title) }}" required></div>
        <div class="field"><label>URL slug</label><input class="input" name="slug" value="{{ old('slug', $page->slug) }}" placeholder="auto"></div>
        <div class="field full"><label>Content</label><textarea class="textarea" name="content" style="min-height:360px;font-family:ui-monospace,monospace;font-size:13px">{{ old('content', $page->content) }}</textarea><div class="hint">Plain text or simple HTML: &lt;p&gt;, &lt;h3&gt;, &lt;ul&gt;&lt;li&gt;, &lt;strong&gt;, &lt;a href=""&gt;.</div></div>
        <div class="full"><h4 style="margin:6px 0 0">SEO</h4></div>
        @include('admin.partials.seo-fields', ['model' => $page, 'url' => $page->exists ? route('page.show', $page) : url('/pages/new'), 'fallbackTitle' => ($page->title ?: 'Page title').' — '.setting('store_name'), 'fallbackDescription' => 'First lines of the page content…'])
        <div class="flex full" style="gap:24px">
            <label class="switch"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $page->is_active))><span class="track"></span> Published</label>
            <label class="switch"><input type="checkbox" name="show_in_footer" value="1" @checked(old('show_in_footer', $page->show_in_footer))><span class="track"></span> Link in footer</label>
        </div>
    </div>
    <div class="form-actions">
        <a href="{{ route('admin.pages.index') }}" class="btn btn-light">Cancel</a>
        <button class="btn btn-primary">{{ $page->exists ? 'Save changes' : 'Create page' }}</button>
    </div>
</form>
@endsection
