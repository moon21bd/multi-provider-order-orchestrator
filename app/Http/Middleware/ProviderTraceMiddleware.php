<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Traits\ProviderLifecycleLogTrait;

/**
 * @author Raqibul Hasan Moon <rhmoon21@gmail.com> (01710455990)
 *
 * Central, human-readable lifecycle trace for EVERY order provider.
 *
 * All provider endpoints share the URL shape "<provider>/<type>/.../<action>"
 * (e.g. charlie/order/request, hotel/order/status, eloadecho/
 * order/request). This middleware sits on the provider route group and logs
 * two greppable lines per call - the inbound request and the outbound response
 * - to the dedicated `provider` channel, WITHOUT touching any controller's
 * business logic. A whole transaction can be followed by grepping its ref_id;
 * a single provider by its name.
 *
 * It is strictly best-effort: any failure here is swallowed so it can never
 * affect the order response.
 */
class ProviderTraceMiddleware
{
    use ProviderLifecycleLogTrait;

    /**
     * First path segments that are NOT providers (orchestrator / meta / infra
     * endpoints that happen to live in the same route group).
     *
     * NOTE: 'order', 'datapack' and 'get' are the CLIENT-facing endpoints
     * (order/request, datapack/request, get/utility/payment/request). They
     * are traced too, but as the client-facing hop rather than as a provider -
     * see resolveClientContext().
     */
    private static $nonProviderPrefixes = [
        'get', 'order', 'datapack', 'message', 'access', 'webhook',
        'appkey', 'logs', 'send', 'process', 'fail', 'check', 'clear',
        'requeue', 'resend', 'update', 'worker', 'test', 'ip',
    ];

    /**
     * Client-facing entry points (first segment => flow type). These are the
     * requests our clients make to us; tracing them gives the timeline its
     * "request_received" / "response_sent" bookends, so a transaction can be
     * followed from the client call all the way through to the provider.
     */
    private static $clientPrefixes = [
        'order' => 'ORDER',
        'datapack' => 'DATAPACK',
        'get'      => 'UTILITY',
    ];

    /** Request-body keys that may carry the transaction reference. */
    private static $refIdKeys = [
        'ref_id', 'orderNumber', 'order_number', 'refId', 'reference_id',
        'transaction_id', 'transactionId', 'trxId', 'requestId',
    ];

    /**
     * Request-body keys that may carry the subscriber number. Utility / voucher
     * flows use account-style keys (account_no, bill_number, customer_id)
     * instead of a mobile number, so those are included as well.
     */
    private static $msisdnKeys = [
        'mobile_no', 'msisdn', 'mobileNo', 'number', 'phone', 'phone_no',
        'account_no', 'account_number', 'bill_number', 'customer_id',
        'subscriber_no',
    ];

    public function handle(Request $request, Closure $next)
    {
        $ctx = null;
        $startedAt = microtime(true);

        try {
            $ctx = $this->resolveProviderContext($request);
            if ($ctx) {
                $ctx['request_log_id'] = $this->logProviderStep($ctx['provider'], $ctx['ref_id'], $ctx['step'] . '-REQUEST-IN', [
                    'stage'       => $ctx['stage_in'],
                    'path'        => $ctx['path'],
                    'request_url' => $ctx['path'],
                    'msisdn'      => $ctx['msisdn'],
                    'trans_type'  => $ctx['trans_type'],
                    'payload'     => $this->sanitize($request->all()),
                ]);
            }
        } catch (\Throwable $e) {
            // never break the flow on a trace failure
        }

        $response = $next($request);

        try {
            if ($ctx) {
                $body = $this->decodeResponse($response);
                $httpCode = (is_object($response) && method_exists($response, 'getStatusCode'))
                    ? $response->getStatusCode() : null;

                // A transport-level failure (5xx / non-2xx) is surfaced as an
                // error step so it shows up red in the timeline instead of
                // looking like a normal response.
                $level = ($httpCode !== null && $httpCode >= 400) ? 'error' : 'info';

                // Client-facing endpoints do not receive a ref_id - the API
                // generates one and returns it (as transaction_id / ref_id in
                // the response body). Without this fallback those steps would
                // be stored with an empty ref_id and never appear in the admin
                // (the trace summary view filters blank ref_ids out).
                if (empty($ctx['ref_id'])) {
                    $ctx['ref_id'] = $this->refIdFrom($body);

                    // The request step for this call was already written with a
                    // blank ref_id. Backfill it so both legs share one ref_id
                    // and the timeline stays a single transaction.
                    if (!empty($ctx['ref_id']) && !empty($ctx['request_log_id'])) {
                        $this->backfillRefId($ctx['request_log_id'], $ctx['ref_id']);
                    }
                }

                $this->logProviderStep($ctx['provider'], $ctx['ref_id'], $ctx['step'] . '-RESPONSE-OUT', [
                    'stage'       => ($level === 'error') ? 'provider_error' : $ctx['stage_out'],
                    'path'        => $ctx['path'],
                    'request_url' => $ctx['path'],
                    'msisdn'      => $ctx['msisdn'],
                    'trans_type'  => $ctx['trans_type'],
                    'http_code'   => $httpCode,
                    'status_code' => $this->extractProviderStatus($body),
                    'status'      => $this->extractInternalStatus($body),
                    'message'     => $this->extractMessage($body),
                    'response'    => $this->sanitize(is_array($body) ? $body : []),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ], $level);
            }
        } catch (\Throwable $e) {
            // never break the flow on a trace failure
        }

        return $response;
    }

    /**
     * Decide whether this request targets a provider endpoint and, if so, build
     * the trace context (provider name, step label, ref_id). Returns null for
     * non-provider routes so nothing is logged for them.
     */
    private function resolveProviderContext(Request $request)
    {
        $path = trim($request->path(), '/');           // e.g. "charlie/order/request"
        $segments = explode('/', $path);

        if (count($segments) < 2) {
            return null;
        }

        $provider = strtolower($segments[0]);

        // Client-facing endpoints are traced as our own inbound hop so the
        // timeline has proper bookends. These can be 2 segments long
        // ("order/request"), so they are matched before the >= 3 segment
        // rule that downstream provider endpoints follow.
        if (isset(self::$clientPrefixes[$provider])) {
            return $this->resolveClientContext($request, $path, $segments, $provider);
        }

        if (in_array($provider, self::$nonProviderPrefixes, true)) {
            return null;
        }

        if (count($segments) < 3) {
            return null; // downstream provider endpoints always have >= 3 segments
        }

        // Some endpoints carry a trailing API version, e.g.
        // "charlie/order/request/v3" or "charlie/order/status/v2". Drop the
        // trailing version so the real action segment (request/status) is
        // evaluated - otherwise these versioned routes would not be traced.
        // Versions can also sit mid-path ("sierra/v3/order/status"), which
        // is handled below when building the flow type.
        $versionSuffix = null;
        if (preg_match('/^v\d+$/', strtolower(end($segments)))) {
            $versionSuffix = strtolower(array_pop($segments));
            if (count($segments) < 3) {
                return null;
            }
        }

        $last = strtolower(end($segments));
        $prev = strtolower($segments[count($segments) - 2]);

        // Map the action segment to a step label. Every provider endpoint in the
        // route table ends in one of these, so all providers are covered:
        //   */order/request, */order/status, */datapack/request,
        //   */datapack/request/status, */*/status/cb, */voucher/get-orderid,
        //   */balance/enquiry, */token/check, */provider/session/set, ...
        if ($last === 'cb') {
            // ".../status/cb" - asynchronous status callback from the provider.
            $step = 'STATUS-CB';
        } elseif ($last === 'status') {
            // Covers both ".../status" and ".../request/status" (// some partners use the former shape, some the latter).
            $step = ($prev === 'request') ? 'REQUEST-STATUS' : 'STATUS';
        } elseif ($last === 'request') {
            $step = 'REQUEST';
        } elseif ($last === 'get-orderid') {
            $step = 'GET-ORDERID';
        } elseif ($last === 'enquiry') {
            // ".../balance/enquiry" - balance check against the provider.
            // $prev is already "balance", which becomes the flow type below,
            // so the step is just the action to avoid a doubled label.
            $step = 'ENQUIRY';
        } elseif (in_array($last, ['check', 'reset', 'set'], true)) {
            // Session / token maintenance calls kept out of the trace.
            // $prev ("token"/"session") is part of the flow type already.
            $step = strtoupper($last);
        } else {
            return null;
        }

        // Prefix the step with the flow type (order / datapack / utility /
        // voucher / telco ...) so topup vs datapack are distinguishable, and
        // append any API version suffix (v2/v3) that was stripped above.
        // For mid-path versions ("sierra/v3/order/status") segment[1] is the
        // version, so take the next segment as the real flow type.
        $typeIndex = 1;
        $midVersion = null;
        if (preg_match('/^v\d+$/', strtolower($segments[1])) && count($segments) > 3) {
            $midVersion = strtolower($segments[1]);
            $typeIndex = 2;
        }
        $type = strtoupper($segments[$typeIndex]);

        $version = $versionSuffix ?: $midVersion;
        if ($version !== null) {
            $step .= '-' . strtoupper($version);
        }

        // Stage for this hop. These endpoints are the calls made against the
        // downstream provider, so the inbound leg is "provider_request" and the
        // outbound leg is "provider_response" - except for asynchronous status
        // callbacks (a webhook) and status/enquiry polls, which keep their own
        // stage on both legs so the timeline reads correctly.
        if (strpos($step, 'STATUS-CB') !== false) {
            $stageIn = $stageOut = 'webhook_received';
        } elseif (strpos($step, 'STATUS') !== false || strpos($step, 'ENQUIRY') !== false) {
            $stageIn = $stageOut = 'status_check';
        } else {
            $stageIn  = 'provider_request';
            $stageOut = 'provider_response';
        }

        return [
            'provider'    => $provider,
            'step'       => $type . '-' . $step,
            'ref_id'     => $this->refIdFrom($request->all()),
            'msisdn'     => $this->msisdnFrom($request->all()),
            'trans_type' => $type,
            'stage_in'   => $stageIn,
            'stage_out'  => $stageOut,
            'path'       => $path,
        ];
    }

    /**
     * Build the trace context for a client-facing endpoint. The "provider" is
     * recorded as CLIENT-API so these hops are visually distinct from the
     * downstream provider calls in the same timeline.
     */
    private function resolveClientContext(Request $request, $path, array $segments, $prefix)
    {
        $last = strtolower(end($segments));
        $type = self::$clientPrefixes[$prefix];

        if ($last === 'status') {
            $step = $type . '-STATUS';
        } elseif ($last === 'request' || $last === 'info') {
            $step = $type . '-REQUEST';
        } else {
            // Metadata lookups (get/operators, get/all/country, ...) carry no
            // transaction, so they stay out of the transaction trace.
            return null;
        }

        // These are our own inbound endpoints: the client calls us, we answer.
        return [
            'provider'    => 'CLIENT-API',
            'step'       => $step,
            'ref_id'     => $this->refIdFrom($request->all()),
            'msisdn'     => $this->msisdnFrom($request->all()),
            'trans_type' => $type,
            'stage_in'   => 'request_received',
            'stage_out'  => 'response_sent',
            'path'       => $path,
        ];
    }

    private function refIdFrom($payload)
    {
        return $this->firstScalar($payload, self::$refIdKeys);
    }

    private function msisdnFrom($payload)
    {
        return $this->firstScalar($payload, self::$msisdnKeys);
    }

    /**
     * First non-empty scalar value among $keys, searched one level deep so
     * wrapped payloads ({"data":{...}}) are handled too.
     */
    private function firstScalar($payload, array $keys)
    {
        if (!is_array($payload)) {
            return null;
        }
        foreach ($keys as $key) {
            if (!empty($payload[$key]) && is_scalar($payload[$key])) {
                return (string) $payload[$key];
            }
        }
        foreach ($payload as $value) {
            if (is_array($value)) {
                foreach ($keys as $key) {
                    if (!empty($value[$key]) && is_scalar($value[$key])) {
                        return (string) $value[$key];
                    }
                }
            }
        }
        return null;
    }

    /** Redact anything that looks like a credential before it hits the log. */
    private function sanitize($payload)
    {
        if (!is_array($payload)) {
            return $payload;
        }
        $clean = [];
        foreach ($payload as $key => $value) {
            if (preg_match('/pass|token|secret|\bpin\b|api_key|apikey|authorization|signature/i', (string) $key)) {
                $clean[$key] = '***';
            } elseif (is_array($value)) {
                $clean[$key] = $this->sanitize($value);
            } else {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }

    private function decodeResponse($response)
    {
        try {
            if (is_object($response) && method_exists($response, 'getContent')) {
                $decoded = json_decode($response->getContent(), true);
                return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return null;
    }

    /** Pull the provider's business status out of the standard response shapes. */
    private function extractProviderStatus($body)
    {
        if (!is_array($body)) {
            return null;
        }
        // Prefer the provider's own business code, which the API nests under
        // `data` (response_status / reload_status / StatusCode / Result / ...).
        if (isset($body['data']) && is_array($body['data'])) {
            foreach (['response_status', 'reload_status', 'StatusCode', 'status_code', 'Result', 'result_code'] as $key) {
                if (array_key_exists($key, $body['data']) && $body['data'][$key] !== null && $body['data'][$key] !== '') {
                    return $body['data'][$key];
                }
            }
        }
        // Top-level provider-specific codes (some providers answer flat).
        foreach (['response_status', 'StatusCode', 'Result', 'result_code'] as $key) {
            if (array_key_exists($key, $body) && $body[$key] !== null && $body[$key] !== '') {
                return $body[$key];
            }
        }
        // NOTE: deliberately NOT falling back to the top-level `status_code`.
        // In this API's response envelope that field is the HTTP-style status
        // (200/400/404), which is already captured as http_code - using it here
        // just duplicated the HTTP code into the GW Code column for ~95% of rows.
        return null;
    }

    /**
     * Pull OUR internal transaction status (0 Pending / 1 Processing /
     * 2 Successful / 3+ Failed) out of the response when the endpoint returns
     * it, so the timeline can colour each step by transaction state.
     */
    private function extractInternalStatus($body)
    {
        if (!is_array($body)) {
            return null;
        }
        // The internal status lives under different keys depending on the
        // endpoint. Order/status responses carry it as data.response_status
        // (a numeric string like "2"); others use data.status or top-level
        // status. Check every known location, nested first.
        if (isset($body['data']) && is_array($body['data'])) {
            foreach (['status', 'response_status', 'reload_status'] as $key) {
                if (isset($body['data'][$key]) && is_numeric($body['data'][$key])) {
                    return (int) $body['data'][$key];
                }
            }
        }
        foreach (['status', 'response_status'] as $key) {
            if (isset($body[$key]) && is_numeric($body[$key])) {
                return (int) $body[$key];
            }
        }
        return null;
    }

    private function extractMessage($body)
    {
        if (!is_array($body)) {
            return null;
        }
        // Top-level message first (note: some responses send "message":[] as an
        // empty array, which !empty()/is_scalar correctly skip).
        foreach (['message', 'response_msg', 'Message'] as $key) {
            if (!empty($body[$key]) && is_scalar($body[$key])) {
                return $body[$key];
            }
        }
        // Otherwise the human-readable message is often nested under `data`.
        if (isset($body['data']) && is_array($body['data'])) {
            foreach (['response_msg', 'message', 'Message', 'status_message'] as $key) {
                if (!empty($body['data'][$key]) && is_scalar($body['data'][$key])) {
                    return $body['data'][$key];
                }
            }
        }
        return null;
    }
}
