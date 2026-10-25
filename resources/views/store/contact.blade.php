@extends('layouts.store')

@section('title', 'Contact Us')

@section('content')
<section class="page-hero">
    <div class="container">
        <span class="eyebrow center">We'd love to hear from you</span>
        <h1>Contact us</h1>
        <p class="lead" style="margin:0 auto">Questions about an order, wholesale or gifting? Our team replies within a few hours.</p>
    </div>
</section>

<section class="section" style="padding-top:30px">
    <div class="container layout-2" style="grid-template-columns:1fr 1.3fr">
        <div class="contact-cards">
            @if ($wa = whatsapp_link('Hi! I have a question.'))
                <a href="{{ $wa }}" target="_blank" rel="noopener" class="contact-card"><span class="ico" style="color:#25d366"><x-icon name="whatsapp" /></span><span><strong>WhatsApp</strong><span class="muted">{{ setting('whatsapp_number') }} · fastest response</span></span></a>
            @endif
            @if (setting('store_phone'))
                <a href="tel:{{ preg_replace('/[^0-9+]/', '', setting('store_phone')) }}" class="contact-card"><span class="ico"><x-icon name="phone" /></span><span><strong>Call us</strong><span class="muted">{{ setting('store_phone') }}</span></span></a>
            @endif
            @if (setting('store_email'))
                <a href="mailto:{{ setting('store_email') }}" class="contact-card"><span class="ico"><x-icon name="mail" /></span><span><strong>Email</strong><span class="muted">{{ setting('store_email') }}</span></span></a>
            @endif
            @if (setting('store_address'))
                <div class="contact-card"><span class="ico"><x-icon name="pin" /></span><span><strong>Visit</strong><span class="muted">{{ setting('store_address') }}</span></span></div>
            @endif
        </div>

        <div class="panel">
            <h2 style="font-size:32px">Send a message</h2>
            @if ($errors->any())
                <div class="alert alert-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ route('contact.send') }}" class="form-grid">
                @csrf
                <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-label="Leave this field empty">
                <div class="field"><label for="c-name">Name</label><input id="c-name" class="input" name="name" value="{{ old('name') }}" required maxlength="120"></div>
                <div class="field"><label for="c-phone">Phone <span class="opt">(optional)</span></label><input id="c-phone" class="input" name="phone" value="{{ old('phone') }}" maxlength="30"></div>
                <div class="field full"><label for="c-email">Email <span class="opt">(optional)</span></label><input id="c-email" type="email" class="input" name="email" value="{{ old('email') }}"></div>
                <div class="field full"><label for="c-subject">Subject <span class="opt">(optional)</span></label><input id="c-subject" class="input" name="subject" value="{{ old('subject') }}" maxlength="150"></div>
                <div class="field full"><label for="c-message">Message</label><textarea id="c-message" class="textarea" name="message" required maxlength="3000">{{ old('message') }}</textarea></div>
                <div class="full"><button type="submit" class="btn btn-primary">Send Message <x-icon name="arrow-right" /></button></div>
            </form>
        </div>
    </div>
</section>
@endsection
