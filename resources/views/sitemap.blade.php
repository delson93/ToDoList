<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($paths as $path)
    <url><loc>{{ rtrim(config('app.url'), '/').$path }}</loc></url>
@endforeach
</urlset>
