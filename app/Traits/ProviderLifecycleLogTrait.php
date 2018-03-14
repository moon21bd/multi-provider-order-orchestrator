<?php

namespace App\Traits;

use App\Models\TransactionEventLogModel;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

/**
 * @author Raqibul Hasan Moon <rhmoon21@gmail.com> (01710455990)
 *
 * Shared, human-readable lifecycle logging for ALL order providers.
 *
 * Any provider controller can `use ProviderLifecycleLogTrait;` and call
 * `logProviderStep()` to emit a single greppable line per step. Every line goes
 * to the dedicated `provider` log channel (storage/logs/provider-YYYY-MM-DD.log),
 * so one transaction can be followed end-to-end by grepping its ref_id, and a
 * single provider can be isolated by grepping its name.
 *
 * In addition to the file, every step is persisted to the transaction_event_logs
 * table so order_provider_admin can render the same trace as a searchable
 * per-transaction timeline (no SSH + grep needed). The DB write derives its
 * fields from the same $context array that is already passed for the file line,
 * so existing call sites keep working unchanged.
 *
 * Logging must NEVER break a order flow, so any logger failure silently
 * falls back to the default channel, and the DB write swallows its own errors.
 *
 * Format: [PROVIDER:NAME] ref_id=<id> | <STEP> | {json context}
 */
trait ProviderLifecycleLogTrait
{
    /** Dedicated Monolog logger for the provider trace (built once per process). */
    private static $providerLogger = null;

    /**
     * Lazily build a dedicated rotating-file logger writing to
     * storage/logs/provider-YYYY-MM-DD.log. This bypasses the framework log
     * channel system (Lumen has no Log::channel()), so the dedicated provider
     * trace file is produced reliably. Returns null on any failure.
     */
    private static function providerLogger()
    {
        if (self::$providerLogger !== null) {
            return self::$providerLogger;
        }
        try {
            $logger = new Logger('provider');
            $handler = new RotatingFileHandler(storage_path('logs/provider.log'), 30, Logger::DEBUG);
            $handler->setFormatter(new LineFormatter("[%datetime%] %level_name%: %message%\n", 'Y-m-d H:i:s', true, true));
            $logger->pushHandler($handler);
            self::$providerLogger = $logger;
        } catch (\Throwable $e) {
            self::$providerLogger = false; // mark as unavailable, don't retry
        }
        return self::$providerLogger ?: null;
    }

    /**
     * @param string      $provider  Provider name, e.g. "ECHO", "CHARLIE", "FOXTROT".
     * @param string|null $refId    Transaction reference id (orderNumber/ref_id).
     * @param string      $step     Short step label, e.g. "TOPUP-RESPONSE".
     * @param array       $context  Extra structured fields (no secrets/tokens).
     * @param string      $level    Log level: info|debug|warning|error.
     */
    protected function logProviderStep($provider, $refId, $step, array $context = [], $level = 'info')
    {
        $line = sprintf(
            '[PROVIDER:%s] ref_id=%s | %s',
            strtoupper((string) $provider),
            ($refId !== null && $refId !== '') ? $refId : '-',
            $step
        );

        if (!empty($context)) {
            $line .= ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        // Logging is strictly best-effort: a logging/permission failure must
        // NEVER propagate into the order flow. Write to the dedicated
        // provider file via Monolog; if that fails, fall back to the default
        // app log; if that also fails, swallow silently.
        $method = in_array($level, ['debug', 'info', 'warning', 'error'], true) ? $level : 'info';
        try {
            $logger = self::providerLogger();
            if ($logger) {
                $logger->{$method}($line);
            } else {
                Log::{$method}($line);
            }
        } catch (\Throwable $e) {
            try {
                Log::{$method}($line);
            } catch (\Throwable $e2) {
                // swallow - never break the transaction because of logging
            }
        }

        // Mirror the same step into transaction_event_logs for the admin
        // timeline. Strictly best-effort and isolated from the file logging
        // above, so a DB hiccup can never break a order.
        return $this->persistProviderStep($provider, $refId, $step, $context, $level);
    }

    /**
     * Persist one lifecycle step to transaction_event_logs.
     *
     * Field values are derived from the same $context array that call sites
     * already build for the log line, so no call site has to change. Unknown /
     * absent keys simply stay null.
     *
     * @return int|null id of the written row, or null if it could not be written
     */
    protected function persistProviderStep($provider, $refId, $step, array $context = [], $level = 'info')
    {
        try {
            // Always store the step time in the app timezone (Country B) with
            // millisecond precision, regardless of PHP's ambient timezone -
            // php-fpm may run in Asia/Kuala_Lumpur while the CLI runs in UTC, so
            // relying on date()'s default would make CLI-written steps 8h off.
            $now = self::traceNow();

            $row = TransactionEventLogModel::create([
                'created_at'      => $now,
                'updated_at'      => $now,
                'ref_id'          => ($refId !== null && $refId !== '') ? (string) $refId : null,
                'msisdn'          => $this->pickContext($context, ['msisdn', 'mobile_no', 'mobileNo', 'msisdn_no', 'number']),
                'source'          => 'api',
                // An explicit stage in the context always wins; otherwise it is
                // inferred from the step label.
                'stage'           => $this->pickContext($context, ['stage'])
                    ?: $this->mapStepToStage($step, $level),
                'provider'         => $provider !== null ? strtoupper((string) $provider) : null,
                'attempt_no'      => $this->pickContext($context, ['attempt_no', 'try_count', 'attempt']),
                'job_id'          => $this->pickContext($context, ['job_id', 'beanstalkd_job_id']),
                'http_code'       => $this->pickContext($context, ['http_code', 'httpCode', 'http_status']),
                'status_code'     => $this->pickContext($context, ['status_code', 'StatusCode', 'response_status', 'result_code', 'Result']),
                'status'          => $this->pickContext($context, ['status', 'txn_status', 'transaction_status']),
                'message'         => $this->pickContext($context, ['message', 'response_msg', 'Message', 'error']),
                'request_url'     => $this->pickContext($context, ['request_url', 'url', 'path', 'endpoint']),
                'request_payload' => $this->stringifyContext($this->pickContextRaw($context, ['request_payload', 'payload', 'request'])),
                'response_data'   => $this->stringifyContext($this->pickContextRaw($context, ['response_data', 'response', 'body'])),
                // Keep the whole context as meta so nothing captured for the
                // file line is lost in the table.
                'meta'            => $this->stringifyContext($context),
            ]);

            return $row ? $row->id : null;
        } catch (\Throwable $e) {
            // Never break the transaction because of trace persistence.
        }

        return null;
    }

    /**
     * Current time in the app timezone (Country B) as "Y-m-d H:i:s.mmm".
     *
     * Built explicitly so it does not depend on PHP's ambient timezone (which
     * differs between php-fpm and the CLI on this deployment).
     * overrides the default when set.
     */
    protected static function traceNow()
    {
        $tz = getenv('APP_TIMEZONE');
        if (!$tz && function_exists('config')) {
            try {
                $tz = config('app.timezone');
            } catch (\Throwable $e) {
                $tz = null;
            }
        }
        $tz = $tz ?: 'Asia/Kuala_Lumpur';

        try {
            $d = new \DateTime('now', new \DateTimeZone($tz));
        } catch (\Throwable $e) {
            $d = new \DateTime('now', new \DateTimeZone('Asia/Kuala_Lumpur'));
        }

        return $d->format('Y-m-d H:i:s') . '.' . substr($d->format('u'), 0, 3);
    }

    /**
     * Set the ref_id on an already-written step.
     *
     * Client-facing endpoints receive no ref_id - the API generates one and
     * returns it - so the inbound step is written before the id is known. Once
     * the response reveals it, the earlier row is updated so both legs of the
     * call share one ref_id and form a single timeline.
     *
     * Best-effort, like every other write here.
     */
    protected function backfillRefId($logId, $refId)
    {
        try {
            if (empty($logId) || $refId === null || $refId === '') {
                return;
            }
            TransactionEventLogModel::where('id', $logId)->update(['ref_id' => (string) $refId]);
        } catch (\Throwable $e) {
            // Never break the transaction because of trace persistence.
        }
    }

    /**
     * Normalise a step label (e.g. "ORDER-REQUEST-IN", "TOPUP-RESPONSE") to
     * one of the stages the admin timeline colours and filters on.
     */
    protected function mapStepToStage($step, $level = 'info')
    {
        $s = strtoupper((string) $step);

        if ($level === 'error') {
            return 'provider_error';
        }

        // An asynchronous status callback from the provider is an inbound
        // webhook regardless of direction suffix.
        if (strpos($s, 'WEBHOOK') !== false || strpos($s, '-CB-') !== false
            || substr($s, -3) === '-CB' || strpos($s, 'CALLBACK-IN') !== false) {
            return 'webhook_received';
        }

        // Status/enquiry polling, on either leg.
        if (strpos($s, 'STATUS') !== false || strpos($s, 'ENQUIRY') !== false) {
            return 'status_check';
        }

        if (strpos($s, 'CALLBACK') !== false) {
            return 'callback_sent';
        }

        // Direction suffixes added by ProviderTraceMiddleware. Note these are
        // only reached for non-status, non-webhook steps; the caller can also
        // pass an explicit stage in the context to override all of this.
        if (strpos($s, 'REQUEST-IN') !== false) {
            return 'request_received';
        }
        if (strpos($s, 'RESPONSE-OUT') !== false) {
            return 'response_sent';
        }
        if (strpos($s, 'RESPONSE') !== false) {
            return 'provider_response';
        }
        if (strpos($s, 'REQUEST') !== false) {
            return 'provider_request';
        }

        // Unknown labels are still stored (lower-cased, underscored) so no step
        // is ever dropped from the timeline.
        return strtolower(str_replace([' ', '-'], '_', substr($s, 0, 48))) ?: 'step';
    }

    /** First present, scalar value among $keys, as a string. */
    private function pickContext(array $context, array $keys)
    {
        $value = $this->pickContextRaw($context, $keys);
        return (is_scalar($value) && $value !== '') ? (string) $value : null;
    }

    /**
     * First present value among $keys, unconverted. Searched one level deep as
     * well, so values nested inside the logged payload/response (e.g. the
     * mobile_no inside 'payload') are still picked up.
     */
    private function pickContextRaw(array $context, array $keys)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $context) && $context[$key] !== null && $context[$key] !== '') {
                return $context[$key];
            }
        }
        foreach ($context as $value) {
            if (!is_array($value)) {
                continue;
            }
            foreach ($keys as $key) {
                if (array_key_exists($key, $value) && $value[$key] !== null && $value[$key] !== ''
                    && is_scalar($value[$key])) {
                    return $value[$key];
                }
            }
        }
        return null;
    }

    /** Normalise a value for a text column (arrays/objects -> JSON). */
    private function stringifyContext($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return ($encoded === false) ? null : $encoded;
    }
}
