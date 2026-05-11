<?php

namespace NexusPay\PaymentMadeEasy\Support;

use Illuminate\Http\Request;

/**
 * Rebuilds an HTTP request with a preserved raw body (needed for HMAC signature verification after queuing).
 */
final class WebhookRequestFactory
{
    /**
     * @param  array<string, string>  $headers  Header name => single value
     */
    public static function fromRawContent(string $rawContent, array $headers = []): Request
    {
        $contentType = 'application/json';
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'content-type') {
                $contentType = $value;
                break;
            }
        }

        $server = [
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE'   => $contentType,
        ];

        $request = Request::create('/', 'POST', [], [], [], $server, $rawContent);

        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'content-type') {
                continue;
            }
            $request->headers->set($name, $value);
        }

        return $request;
    }
}
