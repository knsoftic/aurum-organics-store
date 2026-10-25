@extends('layouts.admin')

@section('title', 'Coupons')

@section('actions')
    <a href="{{ route('admin.coupons.create') }}" class="btn btn-gold btn-sm"><x-icon name="plus" /> Add coupon</a>
@endsection

@section('content')
<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Code</th><th>Discount</th><th>Min. order</th><th class="num">Used</th><th>Valid</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($coupons as $coupon)
                    <tr>
                        <td class="cell-main" style="letter-spacing:.06em">{{ $coupon->code }}</td>
                        <td>{{ $coupon->label }}</td>
                        <td>{{ $coupon->min_order ? money($coupon->min_order) : '—' }}</td>
                        <td class="num">{{ $coupon->used_count }}{{ $coupon->max_uses ? ' / '.$coupon->max_uses : '' }}</td>
                        <td class="cell-sub">{{ $coupon->starts_at?->format('d M Y') ?? 'Now' }} → {{ $coupon->expires_at?->format('d M Y') ?? 'No expiry' }}</td>
                        <td>
                            @if (! $coupon->is_active)<span class="badge b-gray">Disabled</span>
                            @elseif ($coupon->expires_at && $coupon->expires_at->isPast())<span class="badge b-red">Expired</span>
                            @else<span class="badge b-green">Active</span>@endif
                        </td>
                        <td class="actions">
                            <a href="{{ route('admin.coupons.edit', $coupon) }}" class="icon-action" title="Edit"><x-icon name="edit" /></a>
                            <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" class="inline-form" data-confirm="Delete coupon {{ $coupon->code }}?">
                                @csrf @method('DELETE')
                                <button class="icon-action danger" title="Delete"><x-icon name="trash" /></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No coupons yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $coupons->links() }}
</div>
@endsection
