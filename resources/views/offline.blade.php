@extends('layout', ['compact' => true])
@section('title', 'Offline lists | Utilynk Lists')
@section('head')<meta name="robots" content="noindex">@endsection
@section('content')
    <main id="main" class="app-wrap">
        <div id="app" data-screen="offline"></div>
        <noscript>Enable JavaScript to open lists saved on this device.</noscript>
    </main>
@endsection
@section('scripts')@vite('resources/js/app.js')@endsection
