<?php

/**
 * Created by PhpStorm.
 * User: dev
 * Date: 12/24/2017
 * Time: 3:51 PM
 *
 * @author  Raqibul Hasan Moon <rhmoon21@gmail.com>
 * @created 2026-07-23
 */

namespace App\Http\Controllers;


use App\Repositories\Contracts\ProviderRepository;
use App\Repositories\Contracts\OperatorRepository;
use App\Repositories\Contracts\OrderRequestRepository;
use App\Repositories\Contracts\TopupProductDetailsRepository;
use App\Repositories\Contracts\TopupRequestRepository;
use App\Repositories\Contracts\TransactionRepository;
use App\Repositories\Contracts\UserRepository;
use App\Traits\ProviderLifecycleLogTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Pheanstalk\Pheanstalk;
use Ramsey\Uuid\Uuid;

class OrderRequestController extends Controller
{
    // Lets this controller record lifecycle steps (queue dispatch) into
    // transaction_event_logs, alongside the request/response steps the
    // ProviderTraceMiddleware already writes.
    use ProviderLifecycleLogTrait;

    /**
     * Instance of UserRepository
     *
     * @var UserRepository
     */
    private $userRepository;

    /**
     * Instance of UserRepository
     *
     * @var OrderRequestRepository
     */
    private $orderRequestRepository;
    private $providerRepository;
    private $operatorRepository;
    private $transactionRepository;
    private $topupProductDetailsRepository;
    private $topupRequestRepository;

    /**
     * Constructor
     *
     * @param UserRepository $userRepository
     * @param OrderRequestRepository $orderRequestRepository
     * @param ProviderRepository $providerRepository
     * @param OperatorRepository $operatorRepository
     * @param TransactionRepository $transactionRepository
     * @param TopupProductDetailsRepository $topupProductDetailsRepository
     * @param TopupRequestRepository $topupRequestRepository
     */
    public function __construct(UserRepository $userRepository, OrderRequestRepository $orderRequestRepository, ProviderRepository $providerRepository, OperatorRepository $operatorRepository, TransactionRepository $transactionRepository, TopupProductDetailsRepository $topupProductDetailsRepository, TopupRequestRepository $topupRequestRepository)
    {
        $this->userRepository = $userRepository;
        $this->orderRequestRepository = $orderRequestRepository;
        $this->operatorRepository = $operatorRepository;
        $this->providerRepository = $providerRepository;
        $this->transactionRepository = $transactionRepository;
        $this->topupProductDetailsRepository = $topupProductDetailsRepository;
        $this->topupRequestRepository = $topupRequestRepository;
        parent::__construct();
    }

    public function getOrderRequest(Request $request)
    {
        Log::info('getOrderRequest:: ' . json_encode($request->all()));
        $pheanstalk = new Pheanstalk(config('settings.beanstalkd_host'), config('settings.beanstalkd_port'));
        $validatorResponse = $this->validateRequest($request, $this->storeRequestValidationRules($request));

        if ($validatorResponse !== true) {
            return $this->sendInvalidFieldResponse($validatorResponse);
        }

        $user = \Auth::user();
        $inputs = $request->all();
        $orderRequest['mobile_no'] = $inputs['mobile_no'];
        $orderRequest['operator_code'] = $inputs['operator_code'];
        $orderRequest['amount'] = $inputs['amount'];
        $orderRequest['connection_type'] = $inputs['connection_type'];
        $orderRequest['sender'] = $inputs['sender'] ?? NULL;
        $orderRequest['client_id'] = $user->id;
        $orderRequest['status'] = 0;
        $data = $this->orderRequestRepository->save($orderRequest);
        $delay = Pheanstalk::DEFAULT_DELAY;
        $transactions = $this->storeJob($data);

        if (is_null($transactions)) {
            return $this->formatError(Response::HTTP_INTERNAL_SERVER_ERROR, 'CODE 1001: Unable to process request. Please try again later');
        }

        $response['transaction_id'] = $transactions['ref_id'];
        $response['status'] = $transactions['status'];
        $transactions['type'] = 1;

        try {
            {
                $jobId = $pheanstalk->useTube('request')
                    ->put(json_encode($transactions), 0, Pheanstalk::DEFAULT_DELAY, 600);
                Log::info('ORDER-TRANSACTION:: ' . json_encode($transactions));
                $this->transactionRepository->updateTransaction($transactions['ref_id'], "beanstalkd_job_id", $jobId);

                // Records the hand-off to the beanstalkd worker, so the timeline
                // shows the transaction entering the queue (with its job id)
                // between our response to the client and the provider call.
                $this->logProviderStep('CLIENT-API', $transactions['ref_id'], 'ORDER-QUEUE-DISPATCH', [
                    'stage'      => 'queue_dispatch',
                    'msisdn'     => $transactions['mobile_no'] ?? null,
                    'job_id'     => $jobId,
                    'status'     => $transactions['status'] ?? null,
                    'trans_type' => 'ORDER',
                    'message'    => 'Queued to beanstalkd tube "request"',
                ]);
            }
        } catch (\Exception $ex) {
            return $this->formatError(Response::HTTP_INTERNAL_SERVER_ERROR, 'CODE 1002: Unable to process request. Please try again later' . config('settings.beanstalkd_host'));
        }

        return $this->format(Response::HTTP_OK, [], $response);
    }

    /**
     * Store Request Validation Rules
     *
     * @param Request $request
     * @return array
     */
    private function storeRequestValidationRules(Request $request)
    {
        $rules = [
            'mobile_no' => 'required',
            'operator_code' => 'required',
            'amount' => 'required|integer',
            'connection_type' => 'required'
        ];
        return $rules;
    }

    private function storeTopUpRequestValidationRules(Request $request)
    {
        $rules = [
            'msisdn' => 'required',
            'srcuid' => 'required',
            'amount' => 'required',
            'srcpwd' => 'required'
        ];
        return $rules;
    }

    private function storeJob($data)
    {
        $user = \Auth::user();
        $transactions['client_id'] = $user->id;
        $transactions['ref_id'] = Uuid::uuid4()->toString();
        $transactions['client_callback_url'] = $user->org->callback_url;
        $transactions['client_callback_code'] = null;
        $transactions['mobile_no'] = $data['mobile_no'];
        $transactions['operator_code'] = $data['operator_code'];
        $transactions['connection_type'] = $data['connection_type'];
        $transactions['amount'] = $data['amount'];
        $transactions['sender'] = $data['sender'];
        $provider = $this->getSuitableProvider($this->providerRepository->findAllProviderByOperatorCode($data['operator_code']));

        if (is_null($provider)) {
            return null;
        }
        $transactions['provider'] = $provider->provider['name'];
        $transactions['route'] = config('settings.host_url') . $provider->provider['route'];
        $transactions['status_route'] = config('settings.host_url') . $provider->provider['status_route'];
        $transactions['method'] = $provider->provider['method'];
        $transactions['status'] = 0;
        $transactions['request_payload'] = $this->generatePayload($transactions, $provider);
        return $this->transactionRepository->save($transactions);
    }

    private function getSuitableProvider($providers)
    {
        if (count($providers) < 1) {
            return null;
        }
        $index = 0;
        $priority = $providers[$index]->priority;
        for ($i = 1; $i < count($providers); $i++) {
            if ($priority < $providers[$i]->priority) {
                $priority = $providers[$i]->priority;
                $index = $i;
            }
        }
        return $providers[$index];
    }

    /**
     * Build the outbound payload for whichever provider this transaction was
     * routed to. Every partner wants the same handful of facts under
     * different field names, so this is the single place that knows those
     * differences and nothing upstream has to branch on provider code.
     *
     * `gw_integration_code` is the per-(operator, provider) mapping value
     * stored against the route row. It is allowed to be either a plain
     * product code or a JSON blob, because partners onboarded later needed
     * more than one field to identify a product and changing the column
     * type would have meant migrating every existing route.
     *
     * Only three representative provider shapes are kept here; the rest in
     * the original are variations on the same three.
     */
    private function generatePayload($transactions, $provider)
    {
        $payload = [];
        $code = $provider->provider['code'];

        $payload['ref_id']     = $transactions['ref_id'];
        $payload['msisdn']     = $transactions['mobile_no'];
        $payload['amount']     = $transactions['amount'];
        $payload['product_id'] = $provider->gw_integration_code;

        if ($code == "ALPHA" || $code == "CHARLIE") {
            // Simplest shape: integration code is the product id as-is.
            return $payload;
        }

        if ($code == "BRAVO") {
            // Partner additionally distinguishes prepaid/postpaid.
            $payload['type'] = $transactions['connection_type'];
            return $payload;
        }

        if ($code == "FOXTROT") {
            // Async partner: integration code may be JSON, and the callback
            // URL is supplied per request rather than pre-registered.
            $decoded = json_decode($provider->gw_integration_code, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['product_id'])) {
                $payload['product_id'] = $decoded['product_id'];
            }
            $payload['type']         = $transactions['connection_type'];
            $payload['callback_url'] = config('settings.callback_base_url') . '/webhook?provider=FOXTROT';
            return $payload;
        }

        // Unknown provider code: return an empty payload rather than a
        // half-built one. The caller treats that as a routing configuration
        // error, which is what it is -- a route row points at a provider this
        // build does not implement.
        return [];
    }

    private function getProductCode($operator_code, $amount)
    {
        $data = $this->topupProductDetailsRepository->findOneBy(["operator_code" => $operator_code, "amount" => $amount]);
        if (is_null($data)) {
            return false;
        }

        return $data->product_code;
    }

    public function getOrderRequestStatus(Request $request)
    {
        $validatorResponse = $this->validateRequest($request, $this->orderRequestStatusValidationRules($request));

        if ($validatorResponse !== true) {
            return $this->sendInvalidFieldResponse($validatorResponse);
        }

        $data = $this->transactionRepository->findOneBy(["ref_id" => $request['transaction_id']]);

        if (is_null($data)) {
            return $this->formatError(Response::HTTP_NOT_FOUND, "Invalid Transaction ID");
        }

        $response['transaction_id'] = $data->ref_id;
        $response['status'] = (int)$data->status;
        $response['mobile_no'] = $data->mobile_no;
        $response['amount'] = (int)$data->amount;

        return $this->format(Response::HTTP_OK, [], $response);
    }

    public function getOrderRequestStatusCallback(Request $request)
    {
        $validatorResponse = $this->validateRequest($request, $this->orderRequestStatusValidationRules($request));

        if ($validatorResponse !== true) {
            return $this->sendInvalidFieldResponse($validatorResponse);
        }

        $data = $this->transactionRepository->findOneBy(["ref_id" => $request['transaction_id']]);

        if (is_null($data)) {
            return $this->formatError(Response::HTTP_NOT_FOUND, "Invalid Transaction ID");
        }

        $response['transaction_id'] = $data->ref_id;
        $response['status'] = (int)$data->status;
        $response['mobile_no'] = $data->mobile_no;
        $response['amount'] = (int)$data->amount;

        return $this->format(Response::HTTP_OK, [], $response);
    }

    private function orderRequestStatusValidationRules(Request $request)
    {
        $rules = [
            'transaction_id' => 'required'
        ];
        return $rules;
    }

    private function orderRequestStatusCallbackValidationRules(Request $request)
    {
        $rules = [
            'ref_id' => 'required',
            'status' => 'required',
            'response_data' => 'required',
            'transaction_id' => 'required',
            'message' => 'required'
        ];
        return $rules;
    }

    public function requeueByTransactionId($transaction_id)
    {
        $transaction = $this->transactionRepository->findOneBy(["ref_id" => $transaction_id]);
        if (is_null($transaction)) {
            return $this->formatError(Response::HTTP_NOT_FOUND, "Invalid Transaction ID");
        } else if ($transaction['status'] < 1) {
            $transaction['type'] = 1;
            $response['transaction_id'] = $transaction['ref_id'];
            $response['status'] = $transaction['status'];

            try {
                $pheanstalk = new Pheanstalk(config('settings.beanstalkd_host'), config('settings.beanstalkd_port'));
                $jobId = $pheanstalk->useTube('request')->put(json_encode($transaction), 0, Pheanstalk::DEFAULT_DELAY, 600);
                $this->transactionRepository->updateTransaction($transaction['ref_id'], "beanstalkd_job_id", $jobId);
            } catch (\Exception $ex) {
                return $this->formatError(Response::HTTP_INTERNAL_SERVER_ERROR, 'CODE 1002: Unable to process request. Please try again later' . $ex);
            }

            return $this->format(Response::HTTP_OK, [], $response);
        } else {
            return $this->formatError(Response::HTTP_NOT_FOUND, json_encode($transaction));
        }
    }

    public function resendByTransactionId($transaction_id)
    {
        $transaction = $this->transactionRepository->findOneBy(["ref_id" => $transaction_id]);
        if (is_null($transaction)) {
            return $this->formatError(Response::HTTP_NOT_FOUND, "Invalid Transaction ID");
        } else if ($transaction['status'] == 1) {
            $transaction['type'] = 1;
            $response['transaction_id'] = $transaction['ref_id'];
            $response['status'] = $transaction['status'];

            try {
                $pheanstalk = new Pheanstalk(config('settings.beanstalkd_host'), config('settings.beanstalkd_port'));
                $jobId = $pheanstalk->useTube('request')->put(json_encode($transaction), 0, Pheanstalk::DEFAULT_DELAY, 600);
                $this->transactionRepository->updateTransaction($transaction['ref_id'], "beanstalkd_job_id", $jobId);
            } catch (\Exception $ex) {
                return $this->formatError(Response::HTTP_INTERNAL_SERVER_ERROR, 'CODE 1002: Unable to process request. Please try again later' . $ex);
            }

            return $this->format(Response::HTTP_OK, [], $response);
        } else if ($transaction['status'] != 1) {
            return $this->formatError(Response::HTTP_FAILED_DEPENDENCY, json_encode($transaction));
        } else {
            return $this->formatError(Response::HTTP_NOT_FOUND, json_encode($transaction));
        }
    }

    private function isStartWith($string, $query)
    {
        if (substr($string, 0, strlen($query)) === $query) {
            return true;
        }
        return false;
    }

}
