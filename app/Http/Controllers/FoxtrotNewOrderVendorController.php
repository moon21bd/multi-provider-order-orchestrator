<?php

/**
 * Created by PhpStorm.
 * User: Raqibul Hasan Moon
 * Date: 08-May-23
 * Time: 3:15 PM
 *
 * @author  Raqibul Hasan Moon <rhmoon21@gmail.com>
 * @created 2023-05-08
 */

namespace App\Http\Controllers;

use App\Repositories\Contracts\AdminConfigRepository;
use App\Repositories\Contracts\MobileLocationRepository;
use App\Repositories\Contracts\TopupRequestRepository;
use App\Repositories\Contracts\TransactionRepository;
use App\Repositories\Contracts\UtilityPaymentTransactionRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Traits\MessageProcessingTrait;
use App\Traits\FoxtrotCallbackTrait;


class FoxtrotNewOrderVendorController extends Controller
{
    use MessageProcessingTrait;
    use FoxtrotCallbackTrait;
    private $transactionRepository;
    private $topupRequestRepository;
    private $utilityPaymentTransactionRepository;
    private $adminConfigRepository;
    private $mobileLocationRepository;
    private $apiTransaction;
    private $apiTransactionStatus;
    private $apiBasicAuth;

    public function __construct(TransactionRepository $transactionRepository, TopupRequestRepository $topupRequestRepository, UtilityPaymentTransactionRepository $utilityPaymentTransactionRepository, AdminConfigRepository $adminConfigRepository, MobileLocationRepository $mobileLocationRepository)
    {
        $this->transactionRepository = $transactionRepository;
        $this->topupRequestRepository = $topupRequestRepository;
        $this->utilityPaymentTransactionRepository = $utilityPaymentTransactionRepository;
        $this->adminConfigRepository = $adminConfigRepository;
        $this->mobileLocationRepository = $mobileLocationRepository;

        // api credentials
        // API Key:Secret (Pre-Prod/Prod)
        if (!app()->environment('production')) { // development, staging
            // $apiKey = 'replace-me';
            // $apiSecret = 'replace-me';
            $apiKey = 'replace-me';
            $apiSecret = 'replace-me';
            $apiBaseUrl = 'https://preprod-dvs-api.foxtrot.com/v1/';
        } else { // production. key will be change later.
            // $apiKey = 'replace-me';
            // $apiSecret = 'replace-me';

            $apiKey = 'replace-me';
            $apiSecret = 'replace-me';
            $apiBaseUrl = 'https://dvs-api.foxtrot.com/v1/';
        }

        $this->apiTransaction = $apiBaseUrl . 'async/transactions';
        $this->apiTransactionStatus = $apiBaseUrl . 'transactions';
        $this->apiBasicAuth = (string)base64_encode($apiKey . ":" . $apiSecret);
        parent::__construct();
    }

    public function sendOrderRequestToFoxtrotOrderApi(Request $request)
    {
        Log::info('FOXTROT-REQUEST-FROM-order-sendOrderRequestToFoxtrotOrderApi:: ' . json_encode($request->all()));

        $refId = trim($request['ref_id']);
        $productId = $request['product_id'];

        if (is_null($productId)) {
            return $this->formatError(Response::HTTP_BAD_REQUEST, "Product code is not configured.");
        }

        $providerIntegrationCode = json_decode($productId);

        // Register the topup callback so Foxtrot pushes the final transaction
        // status instead of relying solely on status polling.
        $providerIntegrationCode = $this->attachFoxtrotCallbackUrl($providerIntegrationCode, 'topup');

        $apiResponse = $this->doCurl([
            'url' => $this->apiTransaction,
            'params' => $providerIntegrationCode,
            'headers' => [
                "Authorization:Basic {$this->apiBasicAuth}"
            ]
        ]);

        Log::info("FOXTROT-ORDER-API-RESPONSE:: " . json_encode($apiResponse));

        $err = $apiResponse['err'];
        $response = $apiResponse['response'];
        $http_code = $apiResponse['http_code'];

        if ($err) { // error case
            Log::debug("FOXTROT-ORDER-API-RESPONSE-ERROR: " . $err);
            return $this->formatError(Response::HTTP_INTERNAL_SERVER_ERROR, "Order request api getting error.");
        } else { // success case
            if ($http_code >= 200 && $http_code <= 299) { // request successfully accepted
                $responseData = json_decode($response, true);
                $transactionId = $responseData['id']; // transaction id from api
                // $externalIdFromApi = $responseData['external_id']; // external_id from api, actually it is the external id from platform end that send before the calling of api.
                $apiStatus = $responseData['status']; // status received from api

                if ($apiStatus['message'] == 'CONFIRMED') { // initially it's set as confirmed the status
                    $data['response_msg'] = $apiStatus['message'];
                    $data['transaction_id'] = $data['response_id'] = $transactionId;
                    $data['response_status'] = 200;
                    $txnResponse['response'] = $response;
                    $this->saveTransactionStatus($refId, 1, json_encode($txnResponse), $data['response_msg'], $transactionId);
                    return $this->format(Response::HTTP_OK, "Order request accepted.", $data);
                } else {
                    $data['response_msg'] = $apiStatus['message'];
                    $this->saveTransactionStatus($refId, 3, json_encode($response), 'Unhandled order request api response.');
                    return $this->formatError(Response::HTTP_PRECONDITION_FAILED, "Unhandled request api response.");
                }

            } else if ($http_code >= 400 && $http_code <= 499) {
                $data['response_msg'] = $response;
                $this->saveTransactionStatus($refId, 3, json_encode($response), $data['response_msg']);
                return $this->formatError(Response::HTTP_EXPECTATION_FAILED, (string)$data['response_msg']);
            } else {
                $this->saveTransactionStatus($refId, 3, json_encode($response), 'Unhandled order request api response.');
                return $this->formatError(Response::HTTP_INTERNAL_SERVER_ERROR, "Unhandled order request api response.");
            }
        }

    }

    public function getStatusFromFoxtrotOrderApi(Request $request)
    {
        Log::info('FOXTROT-REQUEST-FROM-order-getStatusFromFoxtrotOrderApi:: ' . json_encode($request->all()));
        $refId = trim($request['ref_id']);
        $transaction = $this->transactionRepository->findOneBy(["ref_id" => $refId]);
        if (is_null($transaction)) {
            return $this->formatError(Response::HTTP_BAD_REQUEST, "No transaction found.");
        }

        $apiResponse = $this->doCurl([
            'url' => $this->apiTransactionStatus,
            'params' => [
                'external_id' => $refId
            ],
            'headers' => [
                "Authorization:Basic {$this->apiBasicAuth}"
            ]
        ], 'GET');

        Log::info("::FOXTROT-ORDER-STATUS-API-RESPONSE:: " . json_encode($apiResponse));

        $err = $apiResponse['err'];
        $response = $apiResponse['response'];
        $http_code = $apiResponse['http_code'];

        if ($err) { // error case
            Log::info("FOXTROT-ORDER-STATUS-CHECK-API-ERROR: " . $err);
            return $this->formatError(Response::HTTP_INTERNAL_SERVER_ERROR, "Order request status api getting error.");

        } else { // success case

            try {
                if ($http_code >= 200 && $http_code <= 299) { // request successfully accepted
                    $responseData = json_decode($response, true);
                    Log::info('FOXTROT_ORDER_STATUS_API_RESPONSE: ' . json_encode(['response' => $responseData]));
                    $transactionId = !empty($responseData[0]['id']) ? $responseData[0]['id'] : null; // transaction id from api
                    $apiStatus = $responseData[0]['status']; // status received from api

                    if ($apiStatus['message'] == 'COMPLETED') { // need to change as per api response messages or provided from foxtrot.

                        $productInfo = !empty($responseData[0]['product']) ? $responseData[0]['product'] : null; // product info from api
                        $pinInfo = !empty($responseData[0]['pin']) ? $responseData[0]['pin'] : null;
                        $pricesInfo = !empty($responseData[0]['prices']) ? $responseData[0]['prices'] : null;
                        $productBenefit = !empty($responseData[0]['benefits']) ? $responseData[0]['benefits'] : null;

                        if (isset($productInfo['type']) && $productInfo['type'] == "FIXED_VALUE_PIN_PURCHASE") {

                            $expirationDate = $productInfo['pin']['validity']['quantity'] . " " . $productInfo['pin']['validity']['unit'];
                            $amountBase = $productBenefit[0]['amount']['base'];
                            $currency = $pricesInfo['wholesale']['unit'];
                            $pinCode = $pinInfo['code'];
                            $pinSerial = $pinInfo['serial'];
                            $pinUsage = implode(",", $productInfo['pin']['usage_info']);

                            $voucherInfo = "pin: " . $pinCode . " | " . "pin2: " . $pinCode . " | " .
                                "currency: " . $currency . " | " . "value: " . $amountBase . " | " .
                                "expiration_date: " . $expirationDate . " | " . "usage_info: " . $pinUsage;
                            $card = [
                                'id' => $amountBase . " " . $currency,
                                'card_number' => $pinCode,
                                'card_pin' => $pinCode,
                                'serial' => $pinSerial,
                                'expired' => $expirationDate
                            ];

                            $messageFinal = $msg = $apiStatus['message'] . " | " . $voucherInfo;
                            $txnResponse['response'] = $response;
                            $txnResponse['data']['cards'] = [$card];
                            $this->saveTransactionStatus($refId, 2, json_encode($txnResponse), $msg, $transactionId);
                        } else {
                            $messageFinal = $apiStatus['message'];
                            $this->saveTransactionStatus($refId, 2, json_encode($response), $apiStatus['message'], $transactionId);
                        }

                        $data['response_msg'] = $messageFinal;
                        $data['response_status'] = $data['reload_status'] = (string)$transaction['status'];
                        $data['response_id'] = $data['txn_id'] = $data['transaction_id'] = $transactionId;
                        return $this->format(Response::HTTP_OK, "Order request was successful.", $data);

                    } else if ($apiStatus['message'] == 'SUBMITTED') {
                        $this->saveTransactionStatus($refId, 1, json_encode($response), $apiStatus['message'], $transactionId);
                        return $this->formatError(Response::HTTP_PRECONDITION_FAILED, "Order request submitted for transaction.");
                    } else {
                        $data['response_msg'] = $apiStatus['message'];
                        $this->saveTransactionStatus($refId, 3, json_encode($response), $data['response_msg'], $transactionId);
                        // Failed (terminal) -> 417 so the worker finalizes it (not re-enqueue).
                        return $this->formatError(Response::HTTP_EXPECTATION_FAILED, (string)$data['response_msg']);
                    }

                } else if ($http_code >= 400 && $http_code <= 499) {
                    $data['response_msg'] = $response;
                    $this->saveTransactionStatus($refId, 3, json_encode($response), $data['response_msg']);
                    return $this->formatError(Response::HTTP_EXPECTATION_FAILED, (string)$data['response_msg']);
                } else {
                    $this->saveTransactionStatus($refId, 3, json_encode($response), "Order status api unhandled response code.");
                    return $this->formatError(Response::HTTP_INTERNAL_SERVER_ERROR, "Order status api unhandled response code.");
                }
            } catch (\Exception $exception) {
                Log::error("FOXTROT NEW STATUS API RESPONSE EXCEPTION | error: " . $exception);
                return $this->formatError(Response::HTTP_INTERNAL_SERVER_ERROR, 'Order status api unreachable.');
            }

        }
    }

    /**
     * Status read after a Foxtrot callback (mirrors bravo/order/status/cb).
     *
     * The Foxtrot callback (POST /webhook?provider=FOXTROT&flow=topup) already
     * persists the final status against this transaction, so this endpoint just
     * returns the persisted status — identical behaviour and response shape to
     * bravo/order/status/cb. When the status is still non-terminal (the
     * callback has not arrived yet) it returns 404 with the current message; the
     * caller polls again and a live enquiry can be added here later if needed.
     */
    public function getStatusFromCallbackFoxtrotOrderApi(Request $request)
    {
        $refId = trim($request['ref_id']);
        $transaction = $this->transactionRepository->findOneBy(["ref_id" => $refId]);

        if (is_null($transaction)) {
            return $this->formatError(Response::HTTP_EXPECTATION_FAILED, "Invalid Request.");
        }

        $data['response_status'] = (string)$transaction['status'];
        $data['reload_status'] = (string)$transaction['status'];
        $data['response_msg'] = (string)$transaction['message'];
        $data['response_id'] = (string)$transaction['status'];
        $data['txn_id'] = (string)$transaction['status'];
        if ($transaction['status'] == 2) {
            return $this->format(Response::HTTP_OK, "Order Successful", $data);
        } else if ($transaction['status'] == 3 || $transaction['status'] == 4) {
            return $this->formatError(Response::HTTP_EXPECTATION_FAILED, $transaction['message']);
        } else {
            // Still pending/processing (status 0/1). Return 412 (not 404) so the
            // worker treats it as "keep polling" per the status-endpoint rule;
            // 404 was ambiguous and only differed from the 412 re-enqueue path
            // by luck.
            return $this->formatError(Response::HTTP_PRECONDITION_FAILED, $transaction['message']);
        }
    }

    public function doCurl($data = [], $method = 'POST')
    {
        $funcCallerName = debug_backtrace()[1]['function'];
        $logMsg = 'doCurl-CALLED-FROM: ' . $funcCallerName;
        Log::info('doCurl-REQUEST: ' . json_encode([$logMsg, $data, $method]));
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
        } else { // for GET request
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url . "?" . http_build_query($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                // CURLOPT_ENCODING => "",
                // CURLOPT_MAXREDIRS => 10,
                // CURLOPT_TIMEOUT => 600,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => array_merge(array(
                    "Content-Type:application/json",
                ), $headers),
            ));

            Log::info('doCurl-GET-URL: ' . $url . "?" . http_build_query($params));

        }

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        Log::info('doCurl-RESPONSE: ' . json_encode([$response, $err, $http_code]));

        return [
            'response' => $response,
            'err' => $err,
            'http_code' => $http_code
        ];
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
