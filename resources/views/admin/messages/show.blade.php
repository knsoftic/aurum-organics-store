@extends('layouts.admin')

@section('title', $message->subject ?: 'Message from '.$message->name)
@section('crumb')<a href="{{ route('admin.messages.index') }}">Messages</a>@endsection

@section('content')
<div class="card" style="max-width:820px">
    <div class="card-head">
        <div><h3>{{ $message->name }}</h3><span class="muted" style="font-size:12.5px">{{ $message->created_at->format('d M Y, h:i A') }}</span></div>
        <div class="flex">
            @if ($message->phone)
                @php $digits = preg_replace('/\D+/', '', $message->phone); @endphp
                <a href="https://wa.me/{{ str_starts_with($digits, '0') ? '92'.substr($digits, 1) : $digits }}" target="_blank" rel="noopener" class="btn btn-light btn-sm"><x-icon name="whatsapp" /> WhatsApp</a>
            @endif
            @if ($message->email)<a href="mailto:{{ $message->email }}?subject=Re: {{ rawurlencode($message->subject ?? '') }}" class="btn btn-light btn-sm"><x-icon name="mail" /> Reply</a>@endif
            <form method="POST" action="{{ route('admin.messages.destroy', $message) }}" class="inline-form" data-confirm="Delete this message?">
                @csrf @method('DELETE')
                <button class="btn btn-danger btn-sm"><x-icon name="trash" /></button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <dl class="dl" style="margin-bottom:18px">
            <dt>Phone</dt><dd>{{ $message->phone ?: '—' }}</dd>
            <dt>Email</dt><dd>{{ $message->email ?: '—' }}</dd>
        </dl>
        <p style="white-space:pre-line;font-size:15px;margin:0">{{ $message->message }}</p>
    </div>
</div>
@endsection
