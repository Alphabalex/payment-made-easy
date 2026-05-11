<?php

namespace NexusPay\PaymentMadeEasy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limits incoming payment webhook requests per IP (and optionally per gateway route segment).
 *
 * Enable in config with `webhooks.throttle.enabled` and register the middleware in
 * `payment-gateways.webhooks.middleware`, for example:
 *
 *   'middleware' => [
 *       \NexusPay\PaymentMadeEasy\Http\Middleware\ThrottlePaymentWebhooks::class,
 *   ],
 */
class ThrottlePaymentWebhooks
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('payment-gateways.webhooks.throttle.enabled', false)) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        $maxAttempts = (int) config('payment-gateways.webhooks.throttle.max_attempts', 60);
        $decaySeconds = (int) config('payment-gateways.webhooks.throttle.decay_seconds', 60);

        $gateway = $request->route('gateway') ?? 'all';
        $key = sprintf(
            '%s:%s:%s',
            config('payment-gateways.webhooks.throttle.cache_prefix', 'payment-webhook-throttle'),
            $gateway,
            sha1($request->ip())
        );

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response('Too Many Requests', 429)->withHeaders([
                'Retry-After' => (string) max(1, $retryAfter),
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
