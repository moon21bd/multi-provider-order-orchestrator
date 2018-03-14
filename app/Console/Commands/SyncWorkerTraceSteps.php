<?php

namespace App\Console\Commands;

/**
 * @author  Raqibul Hasan Moon <rhmoon21@gmail.com>
 * @created 2026-07-23
 */

use App\Models\TransactionEventLogModel;
use App\Models\TransactionModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Reconstruct the worker-side lifecycle steps from the transactions table.
 *
 * Orders are processed by the compiled OrderSystemWorker JAR, which we do
 * not modify, so the work it does between picking a job off beanstalkd and
 * calling back into this API is not written to transaction_event_logs by any
 * code we control.
 *
 * The worker does, however, record its outcome on the transactions row itself:
 * it sets the provider it routed to, the attempt count, the final status, the
 * provider message/response and the client callback code. This command reads
 * those rows and materialises the missing steps, so the admin timeline shows
 * the worker stage instead of jumping straight from queue_dispatch to the
 * client callback.
 *
 * Idempotent: a step is only written when a row with the same ref_id and stage
 * does not already exist, so the command can run on a schedule (or be re-run
 * over the same window) without duplicating history.
 */
class SyncWorkerTraceSteps extends Command
{
    protected $signature = 'trace:sync-worker
        {--minutes=30 : Look back this many minutes over transactions.updated_at}
        {--limit=500 : Maximum transactions to process in one run}';

    protected $description = 'Materialise worker-side trace steps from the transactions table';

    /** Stage written for the worker\'s provider routing decision. */
    const STAGE_WORKER = 'worker_processed';

    /** Stage written once the transaction reaches a terminal status. */
    const STAGE_FINAL = 'final';

    public function handle()
    {
        $minutes = (int) $this->option('minutes');
        $limit   = (int) $this->option('limit');

        $since = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));

        // Only transactions the worker has actually touched are interesting:
        // it stamps a provider on the row when it routes the job.
        $transactions = TransactionModel::where('updated_at', '>=', $since)
            ->whereNotNull('provider')
            ->where('provider', '<>', '')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $written = 0;

        foreach ($transactions as $txn) {
            try {
                $written += $this->syncTransaction($txn);
            } catch (\Throwable $e) {
                // One bad row must never stop the sync.
                Log::error('trace:sync-worker failed for ' . $txn->ref_id . ': ' . $e->getMessage());
            }
        }

        $this->info(sprintf(
            'Scanned %d transaction(s) since %s; wrote %d step(s).',
            $transactions->count(),
            $since,
            $written
        ));

        return 0;
    }

    /**
     * Write whichever worker steps are missing for one transaction.
     *
     * @return int number of steps written
     */
    protected function syncTransaction($txn)
    {
        $written = 0;

        // Step 1: the worker picked the job up and routed it to a provider.
        // Timestamped from created_at, since that is the closest marker we have
        // to when routing happened; the final step carries updated_at.
        if (!$this->stageExists($txn->ref_id, self::STAGE_WORKER)) {
            $this->writeStep($txn, self::STAGE_WORKER, $txn->created_at, [
                'attempt_no' => $txn->try_count,
                'message'    => 'Worker routed transaction to ' . $txn->provider,
                'status'     => null,
            ]);
            $written++;
        }

        // Step 2: terminal outcome. Only once the transaction has actually
        // settled - a pending/processing row may still change.
        if ((int) $txn->status > 1 && !$this->stageExists($txn->ref_id, self::STAGE_FINAL)) {
            $this->writeStep($txn, self::STAGE_FINAL, $txn->updated_at, [
                'attempt_no'    => $txn->try_count,
                'status'        => (int) $txn->status,
                'message'       => $txn->message,
                'response_data' => $txn->response_data,
                'status_code'   => $txn->transaction_id,
            ]);
            $written++;
        }

        return $written;
    }

    /** Has this step already been recorded for this transaction? */
    protected function stageExists($refId, $stage)
    {
        return TransactionEventLogModel::where('ref_id', $refId)
            ->where('stage', $stage)
            ->exists();
    }

    protected function writeStep($txn, $stage, $timestamp, array $extra = [])
    {
        // Match the millisecond format the other writers use so the unified
        // timeline sorts correctly.
        $at = $timestamp ? date('Y-m-d H:i:s.000', strtotime((string) $timestamp)) : date('Y-m-d H:i:s.000');

        TransactionEventLogModel::create(array_merge([
            'created_at'      => $at,
            'updated_at'      => $at,
            'ref_id'          => $txn->ref_id,
            'msisdn'          => $txn->mobile_no,
            'source'          => 'worker',
            'stage'           => $stage,
            'provider'         => $txn->provider,
            'job_id'          => $txn->beanstalkd_job_id,
            'request_url'     => $txn->route,
            'request_payload' => $txn->request_payload,
        ], $extra));
    }
}
