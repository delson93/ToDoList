@extends('layout', ['compact' => true])
@section('title', 'My lists | Utilynk Lists')
@section('head')<meta name="robots" content="noindex"><meta name="referrer" content="no-referrer">@endsection
@section('content')
    <main id="main" class="app-wrap">
        @if($screen === 'library')
            <div class="section-head"><h1>My lists</h1><a class="text-link" href="/account">Account</a></div>
            @if($lists->isEmpty())<p class="muted">Create a list or open a shared invitation to get started.</p>@endif
            <ul class="library">
                @foreach($lists as $list)<li><a href="{{ route('lists.show', $list) }}">{{ $list->name }} <span aria-hidden="true">→</span></a></li>@endforeach
            </ul>
        @endif
        <div id="app" data-screen="{{ $screen }}" data-list-id="{{ $listId ?? '' }}" data-reverb="{{ config('broadcasting.default') === 'reverb' ? config('broadcasting.connections.reverb.key') : '' }}" data-labels='@json(__('lists'))' data-theme="{{ auth()->check() ? \Illuminate\Support\Facades\DB::table('user_preferences')->where('user_id', auth()->id())->value('theme') : '' }}"></div>
        <noscript>Enable JavaScript to use lists and offline features.</noscript>
    </main>
@endsection
@section('scripts')@vite('resources/js/app.js')@endsection
