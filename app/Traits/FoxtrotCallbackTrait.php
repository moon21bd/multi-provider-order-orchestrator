<?php

/**
 * Shared helpers for wiring Foxtrot callbacks into outgoing transaction
 * requests.
 *
 * Foxtrot accepts a `callback_url` field on POST /async/transactions and posts a
 * JSON transaction object back to it when the transaction reaches a final
 * status. Each product flow (topup / datapack / utility) registers its own
 * callback URL via the `flow` query parameter so the inbound handler knows
 * which repository owns the transaction.
 */

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait FoxtrotCallbackTrait
{
    /**
     * Build the callback URL for a given Foxtrot product flow.
     *
     * @param string $flow One of: topup, datapack, utility
     * @return string|null Null when no callback base URL is configured.
     */
    protected function buildFoxtrotCallbackUrl($flow)
    {
        // $baseUrl = $this->resolveFoxtrotCallbackBaseUrl();

        $baseUrl = env('APP_ENV') === 'production' ? 'https://api.example.com' : 'https://api.example.com';

        if (empty($baseUrl)) {
            return null;
        }

        $query = [
            'provider' => 'FOXTROT',
            'flow' => $flow,
        ];

        return rtrim($baseUrl, '/') . '/webhook?' . http_build_query($query);
    }

    /**
     * Resolve the public base URL that Foxtrot should call back on.
     *
     * Foxtrot requires an absolute, publicly reachable URL that differs per
     * environment. `foxtrot-api.CALLBACK_BASE_URL` carries the environment-based
     * default (prod vs stage) and is overridable via FOXTROT_CALLBACK_BASE_URL.
     * If that is ever blanked, fall back to the current request's scheme+host so
     * the callback still resolves — a spoofed Host header would only misdirect
     * our own callback, and the inbound handler independently re-verifies every
     * callback against the Foxtrot API before settling a transaction.
     *
     * @return string|null
     */
    protected function resolveFoxtrotCallbackBaseUrl()
    {
        $configured = config('foxtrot-api.CALLBACK_BASE_URL');
        
        if (!empty($configured)) {
            return $configured;
        }

        // Fallback: derive from the inbound request. Lumen has no global
        // request() helper (that is a full-Laravel helper), so resolve it from
        // the container. Guard against it being unavailable in any context.
        try {
            $request = app('request');
            if ($request) {
                $derived = $request->getSchemeAndHttpHost();
                if (!empty($derived)) {
                    Log::info('FOXTROT-CALLBACK-BASE-URL: derived from incoming request | ' . $derived);
                    return $derived;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('FOXTROT-CALLBACK-BASE-URL: could not derive from request | ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Attach `callback_url` to an outgoing Foxtrot transaction payload.
     *
     * The payload is the pre-built provider integration code decoded from
     * `product_id`. Only `callback_url` is added — every other configured field
     * is left exactly as it was.
     *
     * @param mixed  $payload Decoded provider integration code (stdClass expected).
     * @param string $flow    One of: topup, datapack, utility
     * @return mixed The same payload, with callback_url set when available.
     */
    protected function attachFoxtrotCallbackUrl($payload, $flow)
    {
        if (!is_object($payload)) {
            // Malformed/unexpected product configuration: leave it untouched so
            // the request body is never corrupted by this helper.
            Log::warning('FOXTROT-CALLBACK-URL-SKIPPED: payload is not an object for flow ' . $flow);
            return $payload;
        }

        $callbackUrl = $this->buildFoxtrotCallbackUrl($flow);

        if (empty($callbackUrl)) {
            // Without a callback URL Foxtrot cannot push updates; the existing
            // status-polling path remains the resolution mechanism.
            Log::info('FOXTROT-CALLBACK-URL-NOT-CONFIGURED: falling back to status-check polling for flow ' . $flow);
            return $payload;
        }

        $payload->callback_url = $callbackUrl;

        Log::info('FOXTROT-CALLBACK-URL-ATTACHED: flow=' . $flow . ' | callback_url=' . $callbackUrl);

        return $payload;
    }
}
