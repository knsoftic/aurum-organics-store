@extends('layouts.admin')

@section('title', 'Reviews')

@section('content')
<div class="tabs">
    <a href="{{ route('admin.reviews.index') }}" class="{{ ! request('status') ? 'active' : '' }}">All</a>
    <a href="{{ route('admin.reviews.index', ['status' => 'pending']) }}" class="{{ request('status') === 'pending' ? 'active' : '' }}">Pending approval</a>
    <a href="{{ route('admin.reviews.index', ['status' => 'approved']) }}" class="{{ request('status') === 'approved' ? 'active' : '' }}">Published</a>
</div>

<div class="grid grid-main">
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Review</th><th>Product</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($reviews as $review)
                        <tr>
                            <td style="max-width:420px">
                                <div class="cell-main">{{ $review->name }} <span class="muted" style="font-weight:400">{{ $review->city ? '· '.$review->city : '' }}</span></div>
                                <div style="color:var(--gold);letter-spacing:2px">{{ str_repeat('★', $review->rating) }}<span style="color:#ddd">{{ str_repeat('★', 5 - $review->rating) }}</span></div>
                                <div class="cell-sub" style="color:var(--text)">{{ $review->comment }}</div>
                                <div class="cell-sub">{{ $review->created_at->format('d M Y') }}</div>
                            </td>
                            <td>{{ $review->product?->name }}</td>
                            <td>@if ($review->is_approved)<span class="badge b-green">Published</span>@else<span class="badge b-amber">Pending</span>@endif</td>
                            <td class="actions">
                                <form method="POST" action="{{ route('admin.reviews.toggle', $review) }}" class="inline-form">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm {{ $review->is_approved ? 'btn-light' : 'btn-success' }}">{{ $review->is_approved ? 'Hide' : 'Approve' }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" class="inline-form" data-confirm="Delete this review?">
                                    @csrf @method('DELETE')
                                    <button class="icon-action danger" title="Delete"><x-icon name="trash" /></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">No reviews here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $reviews->links() }}
    </div>

    <form method="POST" action="{{ route('admin.reviews.store') }}" class="card">
        @csrf
        <div class="card-head"><h3>Add a review</h3></div>
        <div class="card-body" style="display:grid;gap:12px">
            <p class="muted" style="margin:0;font-size:12.5px">Add genuine feedback you received from customers on WhatsApp or by phone.</p>
            <select class="select" name="product_id" required>
                @foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
            </select>
            <input class="input" name="name" placeholder="Customer name" required>
            <input class="input" name="city" placeholder="City (optional)">
            <select class="select" name="rating">@for ($r = 5; $r >= 1; $r--)<option value="{{ $r }}">{{ $r }} stars</option>@endfor</select>
            <textarea class="textarea" name="comment" placeholder="Review text" required style="min-height:90px"></textarea>
            <button class="btn btn-primary">Add review</button>
        </div>
    </form>
</div>
@endsection
