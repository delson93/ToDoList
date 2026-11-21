<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrivateResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        return self::headers($next($request));
    }

    public static function headers(Response $response): Response
    {
        self::keepRedirectOnHttps($response);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        if (app()->isProduction()) {
            $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' blob: data:; connect-src 'self' wss://list.utilynk.com; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
        }

        return $response;
    }

    private static function keepRedirectOnHttps(Response $response): void
    {
        if (! $response->isRedirection() || ! $response->headers->has('Location')) {
            return;
        }

        $site = parse_url(config('app.url'));
        $target = parse_url($response->headers->get('Location'));
        $path = $target['path'] ?? '/';
        if (($site['scheme'] ?? null) !== 'https' || ($target['scheme'] ?? null) !== 'http'
            || ! isset($site['host']) || ($target['host'] ?? null) !== $site['host']
            || (isset($target['port']) && $target['port'] !== 80)
            || isset($target['user']) || ! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return;
        }

        // Preserve the browser's HTTPS origin when PHP receives a proxied HTTP request.
        $response->headers->set('Location', $path
            .(isset($target['query']) ? '?'.$target['query'] : '')
            .(isset($target['fragment']) ? '#'.$target['fragment'] : ''));
    }
}
