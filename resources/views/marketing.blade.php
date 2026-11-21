@extends('layout')
@php
    [$heading, $description, $sections] = config('marketing.pages')[$path];
    $home = $path === '/';
    $canonical = rtrim(config('app.url'), '/').$path;
    $title = $heading.' | Utilynk Lists';
    $faq = [
        'Do I need an account?' => 'No. You can create, share, and join lists as a guest. An account lets you access your lists across devices.',
        'Can I use a list without internet?' => 'Yes. Open it online first and wait for Available offline. Changes made without a connection sync when you reconnect.',
        'Who can see my list?' => 'Only authorized participants can open it. You can choose what others can do when you create an invitation.',
        'Can I add it to my home screen?' => 'Yes, in supported browsers. Use Install app or your browser’s Add to Home Screen option.',
    ];
    $schema = $home
        ? ['@context' => 'https://schema.org', '@type' => 'WebApplication', 'name' => 'Utilynk Lists', 'url' => $canonical, 'description' => $description, 'applicationCategory' => 'LifestyleApplication', 'operatingSystem' => 'Web', 'offers' => ['@type' => 'Offer', 'price' => 0, 'priceCurrency' => 'USD']]
        : ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => rtrim(config('app.url'), '/').'/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $heading, 'item' => $canonical],
        ]];
@endphp
@section('title', $title)
@section('head')
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Utilynk Lists">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ rtrim(config('app.url'), '/') }}/icons/social.png">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ rtrim(config('app.url'), '/') }}/icons/social.png">
    @if(config('marketing.verification'))<meta name="google-site-verification" content="{{ config('marketing.verification') }}">@endif
    <script type="application/ld+json">{!! json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) !!}</script>
    @if(config('marketing.analytics_script'))<script defer src="{{ config('marketing.analytics_script') }}" referrerpolicy="no-referrer"></script>@endif
@endsection
@section('content')
<main id="main">
    @if($home)
    <section class="hero">
        <div class="wrap hero-grid">
            <div>
                <h1>Create and share <em>shopping lists</em> for free.</h1>
                <p class="lead">{{ $description }}</p>
                <div class="actions">
                    <a class="button primary" href="/create">Create Free List <span aria-hidden="true">→</span></a>
                    <a class="button secondary" href="#features">Explore Features</a>
                </div>
                <p class="quiet"><span aria-hidden="true">✓</span> No account needed</p>
            </div>
            <div class="preview" aria-label="Example grocery list">
                <div class="preview-head">
                    <div class="preview-icon" aria-hidden="true">✓</div>
                    <div><p class="preview-name">Weekend groceries</p><p class="muted tiny">2 of 5 items complete</p></div>
                </div>
                <div class="progress"><span></span></div>
                <ul class="sample">
                    <li><span class="fake-checkbox"></span> Avocados <small>3 pcs</small></li>
                    <li><span class="fake-checkbox"></span> Oat milk <small>1 carton</small></li>
                    <li><span class="fake-checkbox"></span> Sourdough bread <small>1 loaf</small></li>
                </ul>
                <p class="muted tiny preview-completed">Completed</p>
                <ul class="sample completed">
                    <li><span class="fake-checkbox">✓</span> Strawberries</li>
                    <li><span class="fake-checkbox">✓</span> Greek yogurt</li>
                </ul>
            </div>
        </div>
    </section>
    @else
    <div class="wrap article-header">
        <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="/">Home</a> <span aria-hidden="true">/</span> {{ $heading }}</nav>
        <h1>{{ $heading }}</h1>
        <p class="lead">{{ $description }}</p>
        @unless(in_array($path, ['/about','/contact','/privacy-policy','/terms']))<a class="button primary" href="/create">Create Free List →</a>@endunless
    </div>
    @endif
    <div id="features" class="wrap {{ $home ? 'feature-grid' : 'article-body' }}">
        @foreach($sections as $label => $copy)
        <section class="{{ $home ? 'feature-card' : '' }}">
            @if($home)<span class="feature-icon" aria-hidden="true">{{ ['✓', '↗', '↓'][$loop->index] }}</span>@endif
            <h2>{{ $label }}</h2>
            <p>{{ $copy }}</p>
        </section>
        @endforeach
        @if($path === '/contact')<p><a class="text-link" href="mailto:{{ config('marketing.contact') }}">{{ config('marketing.contact') }}</a></p>@endif
    </div>
    @if($home)
    <section class="wrap uses">
        <h2>A list for whatever you’re planning</h2>
        <div class="use-links">
            @foreach(['/shopping-list' => 'Shopping', '/grocery-list' => 'Groceries', '/todo-list' => 'To-do', '/packing-list' => 'Packing', '/shared-shopping-list' => 'Shared shopping', '/online-checklist' => 'Checklists'] as $link => $label)
                <a href="{{ $link }}">{{ $label }} <span aria-hidden="true">↗</span></a>
            @endforeach
        </div>
    </section>
    <section class="wrap faq">
        <h2>Frequently asked questions</h2>
        @foreach($faq as $question => $answer)
            <details><summary>{{ $question }}</summary><p>{{ $answer }}</p></details>
        @endforeach
    </section>
    @endif
</main>
<footer class="site-footer">
    <div class="wrap footer-row">
        <a class="brand" href="/"><span class="brand-icon" aria-hidden="true">✓</span> Utilynk<span class="brand-accent"> Lists</span></a>
        <nav aria-label="Footer">
            @foreach(['/shopping-list' => 'Shopping list', '/grocery-list' => 'Grocery list', '/features' => 'Features', '/about' => 'About', '/contact' => 'Contact', '/privacy-policy' => 'Privacy', '/terms' => 'Terms'] as $link => $label)
                <a href="{{ $link }}">{{ $label }}</a>
            @endforeach
        </nav>
    </div>
</footer>
@endsection
