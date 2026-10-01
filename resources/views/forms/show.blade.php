@extends('layouts.form')
@php($settings = $integration->settings ?? [])
@section('title', $settings['title'] ?? 'Get in touch')

@section('content')
    <h1>{{ $settings['title'] ?? 'Get in touch' }}</h1>
    <p class="from">{{ $integration->organization->name }}</p>

    @if ($errors)
        <div class="alert error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="post" action="{{ route('web-form.submit', $integration->webhook_key) }}" class="stack">
        <label>Name <input name="name" value="{{ $old['name'] ?? '' }}" required maxlength="150" autocomplete="name"></label>
        <label>Phone <input type="tel" name="phone" value="{{ $old['phone'] ?? '' }}" required maxlength="25" autocomplete="tel" placeholder="+91 98765 43210"></label>
        @if ($settings['ask_email'] ?? true)
            <label>Email <span class="hint">optional</span><input type="email" name="email" value="{{ $old['email'] ?? '' }}" maxlength="150" autocomplete="email"></label>
        @endif
        @if ($settings['ask_city'] ?? false)
            <label>City <input name="city" value="{{ $old['city'] ?? '' }}" maxlength="100" autocomplete="address-level2"></label>
        @endif
        @if ($settings['ask_message'] ?? true)
            <label>Message <textarea name="message" rows="3" maxlength="2000" placeholder="How can we help?">{{ $old['message'] ?? '' }}</textarea></label>
        @endif
        <div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
        <button type="submit" class="btn primary large block">{{ $settings['button'] ?? 'Send' }}</button>
    </form>
    <p class="powered">We'll only use your details to contact you about your enquiry.</p>
@endsection
