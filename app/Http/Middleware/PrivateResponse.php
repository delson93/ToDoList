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
}
