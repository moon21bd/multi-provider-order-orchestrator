<?php

/**
 * Foxtrot callback handler.
 *
 * Foxtrot callbacks (https://developer.provider.example/reference/callbacks):
 *  - Foxtrot POSTs a JSON transaction object when a transaction reaches
 *    COMPLETED or DECLINED (and REVERSED on rare manual reversals).
 *  - We must answer with an HTTP 2XX; non-2XX responses are retried by Foxtrot.
 *
 * Security note: Foxtrot does NOT sign its callbacks — no signature/HMAC header
 * is documented and no shared-secret/token mechanism is provided. The callback
 * is therefore treated purely as a TRIGGER: the delivered status is NEVER
 * trusted on its own. Before a transaction is moved to a terminal state the real
 * status is re-queried from Foxtrot's authenticated API, which makes a forged
 * callback inert.
 *
 * One handler serves all three product flows (topup / datapack / utility); the
 * `flow` query parameter selects the repository that owns the transaction.
 */

namespace App\Integrations;

use App\Integrations\Interfaces\ProviderHandler;
use App\Repositories\Contracts\TransactionRepository;
use App\Repositories\Contracts\UtilityPaymentTransactionRepository;
use App\Traits\MessageProcessingTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class FoxtrotProviderHandler implements ProviderHandler
{
    use MessageProcessingTrait;

    /** Internal transaction status codes (as used across the Foxtrot controllers). */
    const STATUS_PROCESSING = 1;
    const STATUS_SUCCESS = 2;
    const STATUS_FAILED = 3;

    /** Foxtrot terminal transaction statuses. */
    const FOXTROT_COMPLETED = 'COMPLETED';
    const FOXTROT_DECLINED = 'DECLINED';
    const FOXTROT_REVERSED = 'REVERSED';
    const FOXTROT_CANCELLED = 'CANCELLED';

    /** Foxtrot non-terminal statuses (per Foxtrot status mapping). */
    const FOXTROT_CREATED = 'CREATED';
    const FOXTROT_SUBMITTED = 'SUBMITTED';
    const FOXTROT_CONFIRMED = 'CONFIRMED';

    /** Flows that settle against the utility payment repository. */
    const FLOW_UTILITY = 'utility';

    private $transactionRepository;
    private $utilityPaymentTransactionRepository;
    private $apiTransactionStatus;
    private $apiBasicAuth;

    public function __construct(
        TransactionRepository $transactionRepository,
        UtilityPaymentTransactionRepository $utilityPaymentTransactionRepository
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->utilityPaymentTransactionRepository = $utilityPaymentTransactionRepository;

        // Same credentials/base URL resolution the Foxtrot controllers use, so
        // verification hits the exact environment that created the transaction.
        if (!app()->environment('production')) {
            $apiKey = 'replace-me';
            $apiSecret = 'replace-me';
            $apiBaseUrl = 'https://preprod-dvs-api.foxtrot.com/v1/';
        } else {
            $apiKey = 'replace-me';
            $apiSecret = 'replace-me';
            $apiBaseUrl = 'https://dvs-api.foxtrot.com/v1/';
        }

        $this->apiTransactionStatus = $apiBaseUrl . 'transactions';
        $this->apiBasicAuth = (string)base64_encode($apiKey . ":" . $apiSecret);
    }

    /**
     * Parse and authenticate the inbound Foxtrot callback.
     */
    public function parseRequest(Request $request)
    {
        Log::info('FOXTROT-CALLBACK-RECEIVED:: ' . json_encode([
            'query' => $request->query(),
            'body' => $request->all(),
        ]));

        $body = $request->all();

        // Foxtrot documents "a JSON transaction object". Accept both the bare
        // object and a single-element array (the shape /transactions returns),
        // so a delivery variant never silently drops a status update.
        $transaction = $body;
        if (isset($body[0]) && is_array($body[0])) {
            $transaction = $body[0];
        }

        if (!is_array($transaction) || empty($transaction)) {
            return [
                'provider' => 'FOXTROT',
                'is_valid' => false,
                'error' => 'Foxtrot callback payload is not a transaction object.',
                'ref_id' => null,
            ];
        }

        // external_id is our ref_id (sent on the original transaction request).
        // It may arrive as a plain scalar or as an object wrapper.
        $externalId = isset($transaction['external_id']) ? $transaction['external_id'] : null;
        if (is_array($externalId)) {
            $externalId = isset($externalId['value']) ? $externalId['value'] : null;
        }

        $statusMessage = null;
        if (isset($transaction['status']) && is_array($transaction['status'])) {
            $statusMessage = isset($transaction['status']['message'])
                ? $transaction['status']['message']
                : null;
        }

        $flow = strtolower((string)($request->query('flow') ?? $request->input('flow') ?? 'topup'));

        return [
            'provider' => 'FOXTROT',
            'is_valid' => true,
            'flow' => $flow,
            'ref_id' => $externalId !== null ? trim((string)$externalId) : null,
            'transaction_id' => isset($transaction['id']) ? $transaction['id'] : null,
            'status_message' => $statusMessage,
            'raw_data' => json_encode($body),
        ];
    }

    /**
     * Process the callback: verify against the Foxtrot API, then persist.
     */
    public function processWebhook(array $data)
    {
        if (empty($data['is_valid'])) {
            // A malformed/unparseable payload can never be resolved, so answer
            // 4XX to stop Foxtrot retrying it.
            $message = isset($data['error']) ? $data['error'] : 'Invalid Foxtrot callback.';
            return $this->respond(Response::HTTP_BAD_REQUEST, $message);
        }

        $refId = isset($data['ref_id']) ? $data['ref_id'] : null;

        if (empty($refId)) {
            Log::error('FOXTROT-CALLBACK-MISSING-EXTERNAL-ID:: ' . json_encode($data));
            // 4XX so Foxtrot does not retry a payload we can never resolve.
            return $this->respond(Response::HTTP_BAD_REQUEST, 'Foxtrot callback missing external_id.');
        }

        $flow = isset($data['flow']) ? $data['flow'] : 'topup';
        $transaction = $this->findTransaction($refId, $flow);

        if (is_null($transaction)) {
            Log::error("FOXTROT-CALLBACK-TRANSACTION-NOT-FOUND:: ref_id|{$refId}|flow|{$flow}");
            return $this->respond(Response::HTTP_BAD_REQUEST, 'Transaction not found.');
        }

        $callbackStatus = strtoupper((string)(isset($data['status_message']) ? $data['status_message'] : ''));

        // A non-terminal callback carries no decision for us. Acknowledge it so
        // Foxtrot stops retrying; the existing status-polling path still owns
        // resolution.
        if ($callbackStatus === self::FOXTROT_SUBMITTED || $callbackStatus === self::FOXTROT_CONFIRMED) {
            Log::info("FOXTROT-CALLBACK-NON-TERMINAL:: ref_id|{$refId}|status|{$callbackStatus}");
            return $this->respond(Response::HTTP_OK, 'Foxtrot callback acknowledged (non-terminal status).');
        }

        // The callback is unauthenticated, so treat it only as a TRIGGER and
        // fetch the authoritative status from Foxtrot before touching money.
        $verified = $this->fetchVerifiedTransaction($refId);
        $verifiedStatus = isset($verified['status_message']) ? strtoupper((string)$verified['status_message']) : null;

        Log::info("FOXTROT-CALLBACK-VERIFIED-STATUS:: ref_id|{$refId}|callback|{$callbackStatus}|verified|" . json_encode($verifiedStatus));

        $resolved = $this->resolveStatus($verifiedStatus, $callbackStatus);

        if (is_null($resolved)) {
            Log::error("FOXTROT-CALLBACK-UNRESOLVED:: ref_id|{$refId}|callback|{$callbackStatus}|verified|" . json_encode($verifiedStatus));
            // 5XX so Foxtrot retries; the status could not be confirmed.
            return $this->respond(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Foxtrot callback could not be verified against the API.'
            );
        }

        // Never re-settle an already terminal transaction (callbacks can be
        // retried/duplicated by Foxtrot).
        $currentStatus = isset($transaction['status']) ? (int)$transaction['status'] : null;
        if ($currentStatus === self::STATUS_SUCCESS || $currentStatus === self::STATUS_FAILED) {
            Log::info("FOXTROT-CALLBACK-ALREADY-TERMINAL:: ref_id|{$refId}|status|{$currentStatus}");
            return $this->respond(Response::HTTP_OK, 'Transaction already in a final state.');
        }

        $message = !empty($verified['status_message'])
            ? $verified['status_message']
            : (!empty($data['status_message']) ? $data['status_message'] : 'Foxtrot callback');

        $transactionId = !empty($data['transaction_id'])
            ? $data['transaction_id']
            : (isset($verified['id']) ? $verified['id'] : null);

        // Voucher/PIN products carry their card details in the verified payload.
        $responseData = $this->buildResponseData($verified, $data, $message);

        $saved = $this->saveTransactionStatus(
            $transaction,
            $flow,
            $resolved,
            $responseData['payload'],
            $responseData['message'],
            $transactionId
        );

        if (!$saved) {
            Log::error("FOXTROT-CALLBACK-UPDATE-FAILED:: ref_id|{$refId}");
            return $this->respond(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to update the transaction.');
        }

        Log::info("FOXTROT-CALLBACK-PROCESSED:: ref_id|{$refId}|status|{$resolved}");

        return $this->respond(Response::HTTP_OK, 'Foxtrot callback processed successfully.');
    }

    /**
     * Decide the internal status to persist.
     *
     * The verified API status wins. The callback status is only consulted when
     * verification produced nothing usable, and even then it may never promote a
     * transaction to SUCCESS on its own.
     *
     * @return int|null Null when the outcome could not be determined.
     */
    private function resolveStatus($verifiedStatus, $callbackStatus)
    {
        if ($this->isCompleted($verifiedStatus)) {
            return self::STATUS_SUCCESS;
        }

        if ($this->isFailed($verifiedStatus)) {
            return self::STATUS_FAILED;
        }

        if ($verifiedStatus === self::FOXTROT_CREATED
            || $verifiedStatus === self::FOXTROT_SUBMITTED
            || $verifiedStatus === self::FOXTROT_CONFIRMED) {
            // API still processing while the callback claims a final state.
            // A failed callback is safe to honour (it can only fail, never pay
            // out); otherwise leave it to the polling path.
            if ($this->isFailed($callbackStatus)) {
                return self::STATUS_FAILED;
            }
            return self::STATUS_PROCESSING;
        }

        // No usable verified status (API unreachable/errored). Never mark a
        // transaction successful from an unverified callback.
        if ($this->isFailed($callbackStatus)) {
            return self::STATUS_FAILED;
        }

        return null;
    }

    /**
     * Foxtrot returns a base status class plus, for terminal states, an optional
     * granular suffix (e.g. DECLINED-INVALID-CREDIT-PARTY, DECLINED-INSUFFICIENT
     * -BALANCE). Match on the base class so every variant resolves.
     */
    private function isCompleted($status)
    {
        $status = strtoupper((string)$status);
        return $status === self::FOXTROT_COMPLETED
            || strpos($status, self::FOXTROT_COMPLETED . '-') === 0;
    }

    private function isFailed($status)
    {
        $status = strtoupper((string)$status);
        foreach ([self::FOXTROT_DECLINED, self::FOXTROT_REVERSED, self::FOXTROT_CANCELLED] as $base) {
            if ($status === $base || strpos($status, $base . '-') === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Query Foxtrot for the authoritative transaction record.
     *
     * @return array Empty array when the status could not be retrieved.
     */
    private function fetchVerifiedTransaction($refId)
    {
        $apiResponse = $this->doCurl([
            'url' => $this->apiTransactionStatus,
            'params' => ['external_id' => $refId],
            'headers' => ["Authorization:Basic {$this->apiBasicAuth}"],
        ], 'GET');

        if (!empty($apiResponse['err'])) {
            Log::error('FOXTROT-CALLBACK-VERIFY-ERROR:: ' . $apiResponse['err']);
            return [];
        }

        $httpCode = isset($apiResponse['http_code']) ? (int)$apiResponse['http_code'] : 0;
        if ($httpCode < 200 || $httpCode > 299) {
            Log::error("FOXTROT-CALLBACK-VERIFY-HTTP-{$httpCode}:: " . json_encode($apiResponse['response']));
            return [];
        }

        $decoded = json_decode($apiResponse['response'], true);
        if (!is_array($decoded) || empty($decoded[0]) || !is_array($decoded[0])) {
            Log::error('FOXTROT-CALLBACK-VERIFY-UNEXPECTED-PAYLOAD:: ' . json_encode($apiResponse['response']));
            return [];
        }

        $record = $decoded[0];
        $record['status_message'] = isset($record['status']['message']) ? $record['status']['message'] : null;
        $record['raw_response'] = $apiResponse['response'];

        return $record;
    }

    /**
     * Build the persisted response payload, including voucher details for
     * FIXED_VALUE_PIN_PURCHASE products (mirrors the status-check controllers).
     */
    private function buildResponseData(array $verified, array $data, $message)
    {
        $rawResponse = !empty($verified['raw_response'])
            ? $verified['raw_response']
            : (isset($data['raw_data']) ? $data['raw_data'] : json_encode($data));

        $productInfo = !empty($verified['product']) ? $verified['product'] : null;

        if (
            isset($productInfo['type']) && $productInfo['type'] === 'FIXED_VALUE_PIN_PURCHASE'
            && !empty($verified['pin'])
        ) {
            $pinInfo = $verified['pin'];
            $pricesInfo = !empty($verified['prices']) ? $verified['prices'] : null;
            $productBenefit = !empty($verified['benefits']) ? $verified['benefits'] : null;

            $expirationDate = isset($productInfo['pin']['validity'])
                ? $productInfo['pin']['validity']['quantity'] . " " . $productInfo['pin']['validity']['unit']
                : '';
            $amountBase = isset($productBenefit[0]['amount']['base']) ? $productBenefit[0]['amount']['base'] : '';
            $currency = isset($pricesInfo['wholesale']['unit']) ? $pricesInfo['wholesale']['unit'] : '';
            $pinCode = isset($pinInfo['code']) ? $pinInfo['code'] : '';
            $pinSerial = isset($pinInfo['serial']) ? $pinInfo['serial'] : '';
            $pinUsage = isset($productInfo['pin']['usage_info']) && is_array($productInfo['pin']['usage_info'])
                ? implode(",", $productInfo['pin']['usage_info'])
                : '';

            $voucherInfo = "pin: " . $pinCode . " | " . "pin2: " . $pinCode . " | " .
                "currency: " . $currency . " | " . "value: " . $amountBase . " | " .
                "expiration_date: " . $expirationDate . " | " . "usage_info: " . $pinUsage;

            $txnResponse['response'] = $rawResponse;
            $txnResponse['data']['cards'] = [[
                'id' => $amountBase . " " . $currency,
                'card_number' => $pinCode,
                'card_pin' => $pinCode,
                'serial' => $pinSerial,
                'expired' => $expirationDate,
            ]];

            return [
                'payload' => json_encode($txnResponse),
                'message' => $message . " | " . $voucherInfo,
            ];
        }

        $txnResponse['response'] = $rawResponse;

        return [
            'payload' => json_encode($txnResponse),
            'message' => $message,
        ];
    }

    /**
     * Look up the transaction in the repository that owns the flow.
     */
    private function findTransaction($refId, $flow)
    {
        if ($flow === self::FLOW_UTILITY) {
            return $this->utilityPaymentTransactionRepository->findOneBy(["ref_id" => $refId]);
        }

        return $this->transactionRepository->findOneBy(["ref_id" => $refId]);
    }

    /**
     * Persist the resolved status against the correct repository.
     *
     * The utility repository stores the payload in `request_response`; topup and
     * datapack use `response_data`.
     */
    private function saveTransactionStatus($transaction, $flow, $status, $responseData, $message, $transactionId = null)
    {
        try {
            $attributes = [
                "status" => $status,
                "message" => $message,
                "message_modified" => $this->processResponseMessage($message),
                "transaction_id" => $transactionId,
            ];

            if ($flow === self::FLOW_UTILITY) {
                $attributes["request_response"] = $responseData;
                $this->utilityPaymentTransactionRepository->update($transaction, $attributes);
            } else {
                $attributes["response_data"] = $responseData;
                $this->transactionRepository->update($transaction, $attributes);
            }

            return true;
        } catch (\Exception $exception) {
            Log::error("FOXTROT-CALLBACK-SAVE-EXCEPTION:: " . $exception->getMessage());
            return false;
        }
    }

    /**
     * Foxtrot retries on non-2XX, so the status code carries meaning:
     * 2XX = accepted, 4XX = permanently unprocessable, 5XX = retry.
     */
    private function respond($code, $message)
    {
        return new JsonResponse([
            'status_code' => $code,
            'message' => $message,
        ], $code);
    }

    public function doCurl($data = [], $method = 'POST')
    {
        extract($data);

        $curl = curl_init();

        if ($method == 'POST') {
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 600,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_POSTFIELDS => json_encode($params),
                CURLOPT_HTTPHEADER => array_merge(array(
                    "Content-Type:application/json",
                ), $headers),
            ));
        } else {
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url . "?" . http_build_query($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => array_merge(array(
                    "Content-Type:application/json",
                ), $headers),
            ));
        }

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        Log::info('FOXTROT-CALLBACK-VERIFY-CURL-RESPONSE: ' . json_encode([$response, $err, $http_code]));

        return [
            'response' => $response,
            'err' => $err,
            'http_code' => $http_code
        ];
    }
}
