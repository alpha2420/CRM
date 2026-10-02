<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers for every response. Scripts run only from our
 * own files or inline blocks carrying this request's nonce, so injected
 * markup cannot execute JavaScript. The hosted lead form (/f/...) is the
 * only page other sites may embed in an iframe.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);
        $embeddable = $request->is('f/*');

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self' https://rzp.io https://*.razorpay.com",
            'frame-ancestors '.($embeddable ? '*' : "'self'"),
        ]);

        $headers = [
            'Content-Security-Policy' => $csp,
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        ];

        if (! $embeddable) {
            $headers['X-Frame-Options'] = 'SAMEORIGIN';
        }

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value, replace: false);
        }

        // PHP announces its version (X-Powered-By) unless expose_php is off: never tell attackers.
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
