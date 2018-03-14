<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\TransactionRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use App\Traits\MessageProcessingTrait;
use App\Traits\ProviderLifecycleLogTrait;

class EloadEchoTopUpApiController extends Controller
{
    use MessageProcessingTrait;
    use ProviderLifecycleLogTrait;
    private $transactionRepository;
    private $apiBaseUrl;
    private $apiUserName;
    private $apiUserPassword;

    public function __construct(TransactionRepository $transactionRepository)
    {
        if (!app()->environment('production')) {  // sandbox config value
            $this->apiBaseUrl = 'https://sandbox-api.top-up-mm.com';
            $this->apiUserName = 'example-client';
            $this->apiUserPassword = 'replace-me';
        } else {    // production config value
            $this->apiBaseUrl = 'https://api.top-up-mm.com';
            $this->apiUserName = 'example-client';
            $this->apiUserPassword = 'replace-me';
        }
        $this->transactionRepository = $transactionRepository;

        parent::__construct();
    }

    /**
     * Thin Echo-tagged wrapper around the shared provider lifecycle logger
     * (ProviderLifecycleLogTrait), so every step lands in the common `provider`
     * channel tagged [PROVIDER:ECHO].
     */
    private function logStep($refId, $step, array $context = [], $level = 'info')
    {
        $this->logProviderStep('ECHO', $refId, $step, $context, $level);
    }

    public function sendOrderRequest(Request $request) {
        $inputs = $request->all();

        $validator = Validator::make($request->all(), [
            'orderNumber' => 'required|string',
            'phoneNumber' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $this->logStep($inputs['orderNumber'] ?? null, 'TOPUP-VALIDATION-FAILED', [
                'error' => $validator->errors()->first(),
            ], 'warning');
            return $this->formatError(Response::HTTP_BAD_REQUEST, $validator->errors()->first());
        }

        $orderNumber = trim($inputs['orderNumber']);
        $phoneNumber = trim($inputs['phoneNumber']);
        $amount = trim($inputs['amount']);

        $this->logStep($orderNumber, 'TOPUP-RECEIVED', [
            'phoneNumber' => $phoneNumber,
            'amount'      => $amount,
        ]);

        $token = json_decode($this->generateBearerToken($orderNumber)->getContent(), true)['access_token'] ?? null;

        // Per Eload API v5.4 (section 4, /api/v2/topup): Content-Type is
        // application/json with PascalCase fields - PhoneNumber (string),
        // Amount (number), OrderNumber (string). ("$amount + 0" keeps it numeric:
        // int for whole amounts, float for decimals.)
        $topUpData = [
            'PhoneNumber' => $phoneNumber,
            'Amount' => $amount + 0,
            'OrderNumber' => $orderNumber,
        ];

        $this->logStep($orderNumber, 'TOPUP-REQUEST', [
            'url'     => $this->apiBaseUrl . '/api/v2/topup',
            'payload' => $topUpData,
        ]);

        $curl = curl_init();
        curl_setopt_array($curl,array(
            CURLOPT_URL => $this->apiBaseUrl. '/api/v2/topup',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($topUpData),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: Bearer $token"
            ),
        ));

        $topUpResponse = curl_exec($curl);
        $err = curl_error($curl);
        // Transport-level HTTP status code. Echo exposes TWO independent
        // codes that must never be conflated (Eload API v5.4):
        //   - $httpCode  : the HTTP status of the call itself (200/401/429/5xx)
        //   - StatusCode : the business transaction code in the body (100/200/4xx)
        // The docs drive different behaviour off each, so we read both.
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $topUpResponseData = json_decode($topUpResponse, true);
        $bodyStatus = isset($topUpResponseData['StatusCode']) ? (int)$topUpResponseData['StatusCode'] : null;

        $this->logStep($orderNumber, 'TOPUP-RESPONSE', [
            'http_code'   => $httpCode,
            'status_code' => $bodyStatus,
            'message'     => $topUpResponseData['Message'] ?? null,
            'curl_error'  => $err ?: null,
        ]);

        try {
            $orderNumber = $topUpResponseData['OrderNumber'] ?? $orderNumber;
            $data['response_id'] = $data['txn_id'] = $data['transaction_id'] = $orderNumber;

            // 1) Transport failure (timeout / no response / connection reset).
            //    Per docs: anything other than HTTP 200/401/429 from /topup means
            //    "set the transaction to Pending and resolve it via /enquiry."
            //    We NEVER hard-fail money here, and NEVER mark success.
            // 2) Rate limited (HTTP 429 or body StatusCode 429) -> keep pending.
            // 3) Any HTTP that is not 200/401/429 -> pending, resolve via enquiry.
            if ($err || $httpCode == 429 || $bodyStatus === 429 || !in_array($httpCode, [200, 401, 429])) {
                $reason = $err ? 'transport_error' : (($httpCode == 429 || $bodyStatus === 429) ? 'rate_limited' : 'non_200_401_429_http');
                $this->logStep($orderNumber, 'TOPUP-DECISION PENDING (status=1)', [
                    'reason'      => $reason,
                    'http_code'   => $httpCode,
                    'status_code' => $bodyStatus,
                ], 'warning');
                $data['response_status'] = $bodyStatus;
                $data['response_msg'] = 'Request for transaction is in pending state';
                $this->saveTransactionStatus($orderNumber, 1, (string)$topUpResponse, $data['response_msg'], $data['txn_id']);
                return $this->format(Response::HTTP_OK, "Operation Successful", $data);
            }

            // HTTP 200 / 401 -> the outcome is decided by the body StatusCode.
            // Only a true StatusCode 200 is a final success.
            if ($bodyStatus === 200) {
                $this->logStep($orderNumber, 'TOPUP-DECISION SUCCESS (status=2)', ['status_code' => $bodyStatus]);
                $data['response_status'] = $bodyStatus;
                $data['response_msg'] = 'Request for transaction is successful';
                $this->saveTransactionStatus($orderNumber, 2, (string)$topUpResponse, $data['response_msg'], $data['txn_id']);
                return $this->format(Response::HTTP_OK, "Operation Successful", $data);
            } else if ($bodyStatus === 100) {
                // 100 = Transaction is pending (submitted, final result via enquiry).
                $this->logStep($orderNumber, 'TOPUP-DECISION PENDING (status=1)', [
                    'reason'      => 'status_code_100_pending',
                    'status_code' => $bodyStatus,
                ]);
                $data['response_status'] = $bodyStatus;
                $data['response_msg'] = 'Request for transaction is in pending state';
                $this->saveTransactionStatus($orderNumber, 1, (string)$topUpResponse, $data['response_msg'], $data['txn_id']);
                return $this->format(Response::HTTP_OK, "Operation Successful", $data);
            } else {
                // Any other StatusCode (301/302/406/407/415/501..., or 401 token
                // expired) is a definitive failure - no top-up happened.
                $this->logStep($orderNumber, 'TOPUP-DECISION FAILED (status=4)', [
                    'status_code' => $bodyStatus,
                    'message'     => $topUpResponseData['Message'] ?? null,
                ], 'warning');
                $data['response_status'] = $bodyStatus;
                $data['response_msg'] = $topUpResponseData['Message'] ?? 'Failed transaction';
                $this->saveTransactionStatus($orderNumber, 4, (string)$topUpResponse, $data['response_msg'], $data['txn_id']);
                return $this->formatError(Response::HTTP_PRECONDITION_FAILED, $data['response_msg']);
            }
        } catch(\Exception $exception) {
            $this->logStep($orderNumber, 'TOPUP-EXCEPTION', ['error' => $exception->getMessage()], 'error');
            return $this->formatError(Response::HTTP_NOT_FOUND, 'Request Failed');
        }
    }

    public function getOrderStatus(Request $request) {
        $inputs = $request->all();
        $orderNumber = trim($inputs['ref_id']);

        $this->logStep($orderNumber, 'ENQUIRY-RECEIVED');

        $transaction = $this->transactionRepository->findOneBy(["ref_id" => $orderNumber]);

        if ($transaction && in_array($transaction->status, [3,4])) {
            $responseData = json_decode($transaction->response_data, true);
            $this->logStep($orderNumber, 'ENQUIRY-ALREADY-TERMINAL', [
                'stored_status' => $transaction->status,
            ]);
            // Already terminal (failed). Return 417 so the beanstalkd worker
            // finalizes it as failed. (Worker contract: status_route 2xx=success,
            // 417=failed, any other non-2xx=re-enqueue for re-check.)
            return $this->formatError(Response::HTTP_EXPECTATION_FAILED, $responseData['response_msg']);
        }

        // Per Eload API v5.4 (section 6, /api/v2/enquiry): Content-Type is
        // application/json with a single PascalCase field - OrderNumber.
        $inquiryPayload = [
            'OrderNumber' => $orderNumber,
        ];

        $token = json_decode($this->generateBearerToken($orderNumber)->getContent(), true)['access_token'] ?? null;

        $this->logStep($orderNumber, 'ENQUIRY-REQUEST', [
            'url'     => $this->apiBaseUrl . '/api/v2/enquiry',
            'payload' => $inquiryPayload,
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiBaseUrl. '/api/v2/enquiry',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($inquiryPayload),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: Bearer $token"
            ),
        ));

        $inquiryResponse = curl_exec($curl);
        $err = curl_error($curl);
        // Transport HTTP code, kept separate from the body StatusCode (see topup).
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $inquiryResponseData = json_decode($inquiryResponse, true);
        $bodyStatus = isset($inquiryResponseData['StatusCode']) ? (int)$inquiryResponseData['StatusCode'] : null;

        $this->logStep($orderNumber, 'ENQUIRY-RESPONSE', [
            'http_code'   => $httpCode,
            'status_code' => $bodyStatus,
            'message'     => $inquiryResponseData['Message'] ?? null,
            'curl_error'  => $err ?: null,
        ]);

        try {
            $orderNumber = $inquiryResponseData['OrderNumber'] ?? $orderNumber;
            $data['response_id'] = $data['txn_id'] = $data['transaction_id'] = $orderNumber;

            // Per docs: "If you do not receive an HTTP status code of 200 from
            // /enquiry, set the transaction to Pending." A transport error, a
            // non-200 HTTP, or a rate-limit must NEVER finalize the transaction -
            // keep it pending so it is re-checked. Return 412 (non-2xx, non-417)
            // so the beanstalkd worker RE-ENQUEUES a status re-check. Returning
            // 2xx here would make the worker wrongly treat it as SUCCESS.
            if ($err || $httpCode != 200 || $bodyStatus === 429) {
                $reason = $err ? 'transport_error' : (($bodyStatus === 429) ? 'rate_limited' : 'non_200_http');
                $this->logStep($orderNumber, 'ENQUIRY-DECISION PENDING (status=1)', [
                    'reason'      => $reason,
                    'http_code'   => $httpCode,
                    'status_code' => $bodyStatus,
                ], 'warning');
                $data['response_status'] = $bodyStatus;
                $data['response_msg'] = 'Request for transaction is in pending state';
                $this->saveTransactionStatus($orderNumber, 1, (string)$inquiryResponse, $data['response_msg'], $data['txn_id']);
                return $this->formatError(Response::HTTP_PRECONDITION_FAILED, $data['response_msg']);
            }

            // HTTP 200 -> decide from the body StatusCode.
            if ($bodyStatus === 200) {
                // Only a true StatusCode 200 is a final success.
                $this->logStep($orderNumber, 'ENQUIRY-DECISION SUCCESS (status=2)', ['status_code' => $bodyStatus]);
                $data['response_status'] = $bodyStatus;
                $data['response_msg'] = 'Request for transaction is successful';
                $this->saveTransactionStatus($orderNumber, 2, (string)$inquiryResponse, $data['response_msg'], $data['txn_id']);
                return $this->format(Response::HTTP_OK, "Operation Successful", $data);
            } else if (in_array($bodyStatus, [100, 103, 414], true)) {
                // Transient: 100 pending, 103 invalid IP (retry), 414 order not
                // found yet. Keep pending, do not finalize.
                $this->logStep($orderNumber, 'ENQUIRY-DECISION PENDING (status=1)', [
                    'reason'      => 'transient_status_code',
                    'status_code' => $bodyStatus,
                ]);
                $data['response_status'] = $bodyStatus;
                $data['response_msg'] = 'Request for transaction is in pending state';
                $this->saveTransactionStatus($orderNumber, 1, (string)$inquiryResponse, $data['response_msg'], $data['txn_id']);
                // 412 (non-2xx, non-417) -> worker re-enqueues a status re-check.
                return $this->formatError(Response::HTTP_PRECONDITION_FAILED, $data['response_msg']);
            } else {
                // Anything else (301/302/406/407/415/501...) is a final failure.
                $this->logStep($orderNumber, 'ENQUIRY-DECISION FAILED (status=3)', [
                    'status_code' => $bodyStatus,
                    'message'     => $inquiryResponseData['Message'] ?? null,
                ], 'warning');
                $data['response_status'] = $bodyStatus;
                $data['response_msg'] = $inquiryResponseData['Message'] ?? 'Request for transaction is Failed';
                $this->saveTransactionStatus($orderNumber, 3, (string)$inquiryResponse, $data['response_msg'], $data['txn_id']);
                // 417 -> worker finalizes as failed (any other non-2xx re-enqueues).
                return $this->formatError(Response::HTTP_EXPECTATION_FAILED, $data['response_msg']);
            }
        } catch (\Exception $exception) {
            $this->logStep($orderNumber, 'ENQUIRY-EXCEPTION', ['error' => $exception->getMessage()], 'error');
            return $this->formatError(Response::HTTP_NOT_FOUND, 'Request Failed');
        }
    }

    private function generateBearerToken($refId = null) {
        $payload = [
            'grant_type' => 'password',
            'username' => $this->apiUserName,
            'password' => $this->apiUserPassword
        ];

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiBaseUrl. '/Token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_HTTPHEADER => array(
                "content-type: application/x-www-form-urlencoded",
            )
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $responseData = json_decode($response,true);
        // Never log the raw token response - it contains the bearer access_token.
        // Log only the outcome (and expiry) needed to trace auth issues.
        if (isset($responseData['access_token'])) {
            $this->logStep($refId, 'TOKEN-OK', [
                'http_code'  => $httpCode,
                'expires_in' => $responseData['expires_in'] ?? null,
            ]);
            return response()->json($responseData);
        } else {
            $this->logStep($refId, 'TOKEN-FAILED', [
                'http_code'         => $httpCode,
                'error'             => $responseData['error'] ?? null,
                'error_description' => $responseData['error_description'] ?? null,
            ], 'error');
            return response()->json(['error' => 'Unable to get access token'], 500);
        }
    }

    private function saveTransactionStatus($refId, $status, $responseData, $message, $transactionId = null)
    {
        $transaction = $this->transactionRepository->findOneBy(["ref_id" => $refId]);
        if (is_null($transaction)) {
            return false;
        }
        $this->transactionRepository->update($transaction, ["status" => $status, "response_data" => $responseData, "message" => $message, "message_modified" => $this->processResponseMessage($message), "transaction_id" => $transactionId]);
    }

}
