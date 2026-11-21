@extends('layout', ['compact' => true])
@section('title', 'Create a list | Utilynk Lists')
@section('head')<meta name="robots" content="noindex">@endsection
@section('content')
<main id="main" class="app-wrap">
    <div class="panel">
        <h1>Create a list</h1>
        <form action="/lists" method="post" class="stack">
            @csrf
            <label>List name <input name="name" maxlength="120" value="{{ old('name') }}" placeholder="My shopping list"></label>
            <label>Category
                <select name="category">
                    @foreach(\App\Http\Controllers\ListsController::CATEGORIES as $key => $label)
                        <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <details><summary>Start from a template</summary>
                <label>Template
                    <select name="template">
                        <option value="">Blank list</option>
                        @foreach(['grocery' => 'Groceries', 'packing' => 'Travel', 'event' => 'Event', 'todo' => 'General checklist'] as $key => $label)
                            <option value="{{ $key }}" @selected(old('template') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                @auth
                @php($templates = \Illuminate\Support\Facades\DB::table('templates')->where('user_id', auth()->id())->orderBy('name')->get(['id', 'name']))
                @if($templates->isNotEmpty())
                <label>Saved template
                    <select name="saved_template">
                        <option value="">Choose a saved template…</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}" @selected(old('saved_template') == $template->id)>{{ $template->name }}</option>
                        @endforeach
                    </select>
                </label>
                @endif
                @endauth
            </details>
            @include('partials.errors')
            <button class="button primary">Create list →</button>
        </form>
    </div>
</main>
@endsection
