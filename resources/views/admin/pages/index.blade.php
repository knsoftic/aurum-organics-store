@extends('layouts.admin')

@section('title', 'Pages')

@section('actions')
    <a href="{{ route('admin.pages.create') }}" class="btn btn-gold btn-sm"><x-icon name="plus" /> Add page</a>
@endsection

@section('content')
<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Title</th><th>URL</th><th>Footer</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($pages as $page)
                    <tr>
                        <td class="cell-main">{{ $page->title }}</td>
                        <td class="cell-sub"><a href="{{ route('page.show', $page) }}" target="_blank">/pages/{{ $page->slug }}</a></td>
                        <td>{{ $page->show_in_footer ? 'Yes' : 'No' }}</td>
                        <td>@if ($page->is_active)<span class="badge b-green">Published</span>@else<span class="badge b-gray">Draft</span>@endif</td>
                        <td class="actions">
                            <a href="{{ route('admin.pages.edit', $page) }}" class="icon-action" title="Edit"><x-icon name="edit" /></a>
                            <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" class="inline-form" data-confirm="Delete this page?">
                                @csrf @method('DELETE')
                                <button class="icon-action danger" title="Delete"><x-icon name="trash" /></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No pages yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
