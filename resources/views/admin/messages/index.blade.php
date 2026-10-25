@extends('layouts.admin')

@section('title', 'Messages')

@section('content')
<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>From</th><th>Subject</th><th>Received</th><th></th></tr></thead>
            <tbody>
                @forelse ($messages as $message)
                    <tr style="{{ $message->is_read ? '' : 'background:#fffcf4' }}">
                        <td><div class="cell-main">{{ $message->name }} @unless ($message->is_read)<span class="badge b-amber" style="margin-left:6px">New</span>@endunless</div><div class="cell-sub">{{ $message->phone }} {{ $message->email }}</div></td>
                        <td><div>{{ $message->subject ?: '—' }}</div><div class="cell-sub">{{ \Illuminate\Support\Str::limit($message->message, 90) }}</div></td>
                        <td class="cell-sub">{{ $message->created_at->diffForHumans() }}</td>
                        <td class="actions"><a href="{{ route('admin.messages.show', $message) }}" class="btn btn-light btn-sm">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No messages yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $messages->links() }}
</div>
@endsection
