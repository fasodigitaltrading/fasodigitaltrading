<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $secureProductionRequest = config('app.env') === 'production' && $request->isSecure();
        $cspNonce = null;

        if ($secureProductionRequest) {
            $cspNonce = base64_encode(random_bytes(24));
            $request->attributes->set('csp_nonce', $cspNonce);
            Vite::useCspNonce($cspNonce);
        }

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');

        if ($secureProductionRequest) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
                "object-src 'none'",
                "script-src 'self' 'nonce-{$cspNonce}' 'strict-dynamic' https://widgets.tradingview-widget.com https://*.tradingview.com https://*.tradingview-widget.com",
                "script-src-attr 'none'",
                "style-src 'self' 'unsafe-inline' https://*.tradingview.com https://*.tradingview-widget.com",
                "img-src 'self' data: blob: https://*.tradingview.com https://*.tradingview-widget.com",
                "font-src 'self' data: https://*.tradingview.com https://*.tradingview-widget.com",
                "connect-src 'self' https://*.tradingview.com wss://*.tradingview.com https://*.tradingview-widget.com wss://*.tradingview-widget.com",
                "frame-src 'self' https://*.tradingview.com https://*.tradingview-widget.com",
                "media-src 'self' blob: https://*.tradingview.com https://*.tradingview-widget.com",
                "worker-src 'self' blob:",
                'upgrade-insecure-requests',
            ]));
        }

        return $response;
    }
}
