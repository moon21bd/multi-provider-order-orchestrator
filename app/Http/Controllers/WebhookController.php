<?php

namespace App\Http\Controllers;

/**
 * @author  Raqibul Hasan Moon <rhmoon21@gmail.com>
 * @created 2024-08-07
 */

use App\Integrations\Interfaces\ProviderHandler;
use App\Repositories\Contracts\TransactionRepository;
use App\Traits\ProviderLifecycleLogTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    // Records each inbound provider webhook into transaction_event_logs so the
    // admin timeline shows asynchronous provider callbacks alongside the rest of
    // the transaction lifecycle.
    use ProviderLifecycleLogTrait;

    private $transactionRepository;
    private $providerHandler;

    public function __construct(TransactionRepository $transactionRepository, ProviderHandler $providerHandler = null)
    {
        $this->transactionRepository = $transactionRepository;
        $this->providerHandler = $providerHandler;

        parent::__construct();
    }

    public function callWebhook(Request $request)
    {
        Log::info('REQUEST COME TO THE WEBHOOK ' .  json_encode($request->query()) ?? json_encode($request->all()));

        if (!$request->query('provider') && !$request->input('provider')) {
            return $this->formatError(Response::HTTP_PRECONDITION_FAILED, 'Provider must be added in the header or body of the request.');
        }

        if (!$this->providerHandler) {
            return $this->formatError(Response::HTTP_PRECONDITION_FAILED, 'Unknown provider received.');
        }

        try {
            $data = $this->providerHandler->parseRequest($request);

            // The parsed payload is the first point where the provider and the
            // transaction reference are both known, so the webhook is recorded
            // here for every provider that reaches this controller.
            $this->logProviderStep(
                $data['provider'] ?? ($request->query('provider') ?: $request->input('provider')),
                $data['ref_id'] ?? null,
                'WEBHOOK-RECEIVED',
                [
                    'stage'           => 'webhook_received',
                    'msisdn'          => $data['mobile_no'] ?? ($data['msisdn'] ?? null),
                    'status'          => $data['status'] ?? null,
                    // Providers name their status differently: Foxtrot returns
                    // status_message, others a status_code / message.
                    'status_code'     => $data['status_code'] ?? ($data['status_message'] ?? null),
                    'message'         => $data['message'] ?? ($data['error'] ?? 'Webhook received from provider'),
                    'request_url'     => $request->path(),
                    'request_payload' => $request->all() ?: $request->query(),
                ]
            );

            // Foxtrot transactions may live in either the topup/datapack or the
            // utility repository, and the handler additionally authenticates the
            // callback and verifies the status against the Foxtrot API. Let it own
            // transaction lookup rather than pre-validating against a single
            // repository here.
            if (isset($data['provider']) && $data['provider'] === 'FOXTROT') {
                return $this->providerHandler->processWebhook($data);
            }

            $transaction = $this->transactionRepository->findOneBy(["ref_id" => $data['ref_id']]);

            if (is_null($transaction)) {
                return $this->formatError(Response::HTTP_EXPECTATION_FAILED, "Invalid Request.");
            }

            return $this->providerHandler->processWebhook($data);
        } catch (\Exception $exception) {
            Log::error("Webhook exception error: " . $exception);
            return $this->formatError(Response::HTTP_NOT_FOUND, 'Request Failed.');
        }
    }

}