@extends('layout', ['compact' => true])
@section('title', 'Account | Utilynk Lists')
@section('head')<meta name="robots" content="noindex">@endsection
@section('content')
<main id="main" class="app-wrap">
    <div class="panel">
        <h1>Account</h1>
        @include('partials.errors')
        @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
        @auth
        <p>Signed in as {{ auth()->user()->email }}</p>
        <form action="/account/claim" method="post">@csrf <button class="button primary">Add guest lists to my account</button></form>
        @php($theme = \Illuminate\Support\Facades\DB::table('user_preferences')->where('user_id', auth()->id())->value('theme'))
        <form class="stack" action="/account/preferences" method="post">
            @csrf
            <label>Appearance
                <select name="theme">
                    @foreach(['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'] as $value => $label)<option value="{{ $value }}" @selected($theme === $value)>{{ $label }}</option>@endforeach
                </select>
            </label>
            <input type="hidden" name="locale" value="en">
            <button class="button secondary">Save preferences</button>
        </form>
        <form id="logout" action="/account/logout" method="post">@csrf <button class="button secondary">Sign out</button></form>
        <p id="logout-notice" role="alert"></p>
        @else
        <p>Sign in to access your lists across devices.</p>
        <form class="stack" action="/account" method="post">
            @csrf
            <input type="hidden" name="mode" value="login">
            <label>Email <input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required></label>
            <label>Password <input name="password" type="password" autocomplete="current-password" required></label>
            <button class="button primary">Sign in</button>
        </form>
        <p><a href="/account/forgot-password" class="text-link">Forgot your password?</a></p>
        @if(config('services.google.client_id'))<p><a class="button secondary" href="/account/google">Sign in with Google</a></p>@endif
        <details>
            <summary>Create an account</summary>
            <form class="stack" action="/account" method="post">
                @csrf
                <input type="hidden" name="mode" value="register">
                <label>Name <input name="name" autocomplete="name" maxlength="60" required></label>
                <label>Email <input name="email" type="email" autocomplete="email" required></label>
                <label>Password (12 characters or more) <input name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
                <button class="button secondary">Create account</button>
            </form>
        </details>
        @endauth
    </div>
</main>
@endsection
@section('scripts')@vite('resources/js/account.js')@endsection
