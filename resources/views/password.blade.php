@extends('layout', ['compact' => true])
@section('title', 'Reset password | Utilynk Lists')
@section('head')<meta name="robots" content="noindex">@endsection
@section('content')
<main id="main" class="app-wrap">
    <div class="panel">
        <h1>{{ isset($token) ? 'Choose a new password' : 'Reset your password' }}</h1>
        @include('partials.errors')
        @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
        <form class="stack" action="{{ isset($token) ? '/account/reset-password' : '/account/forgot-password' }}" method="post">
            @csrf
            <label>Email <input name="email" type="email" required autocomplete="email" value="{{ old('email', request('email')) }}"></label>
            @isset($token)
                <input type="hidden" name="token" value="{{ $token }}">
                <label>New password <input name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label>
                <label>Confirm new password <input name="password_confirmation" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label>
            @endisset
            <button class="button primary">{{ isset($token) ? 'Save new password' : 'Send reset link' }}</button>
        </form>
    </div>
</main>
@endsection
