<?php

/**
 * @author  Raqibul Hasan Moon <rhmoon21@gmail.com>
 * @created 2026-07-23
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified, step-wise transaction lifecycle trace.
 *
 * One row per meaningful step of a transaction, written by this provider API
 * (client request received, provider request/response, status check, inbound
 * webhook, final). Today the same information only exists in the rotating
 * storage/logs/provider-*.log file, which means tracing a transaction requires
 * SSH + grep. Persisting the same steps to a table lets order_provider_admin
 * render them as a searchable timeline (by ref_id / mobile no).
 *
 * Rows are ordered chronologically by the auto-increment id, which avoids
 * relying on whole-second created_at precision when several steps of the same
 * transaction land inside the same second.
 *
 * The table lives in the shared order DB that both order_provider_api and
 * order_provider_admin point at, so the admin reads it directly.
 */
class CreateTransactionEventLogsTable extends Migration
{
    public function up()
    {
        Schema::create('transaction_event_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Primary search keys.
            $table->string('ref_id', 255)->nullable();
            $table->string('msisdn', 64)->nullable();

            // Who wrote the row. Kept for parity with the multi-writer design;
            // currently always 'api' (this service).
            $table->string('source', 16)->nullable();

            // Lifecycle stage, e.g. request_received, provider_request,
            // provider_response, provider_error, status_check, webhook_received,
            // callback_sent, final.
            $table->string('stage', 48);

            $table->string('provider', 64)->nullable();
            $table->integer('attempt_no')->nullable();
            $table->string('job_id', 128)->nullable();

            // Codes captured at this step.
            $table->integer('http_code')->nullable();       // transport HTTP code
            // Provider business code. VARCHAR because every provider returns its
            // native code in a different shape: CHARLIE "SUBMIT_SUCCESS", HOTEL
            // "009", BRAVO "2001", Echo 100/200/415.
            $table->string('status_code', 64)->nullable();
            $table->integer('status')->nullable();          // our internal txn status

            $table->text('message')->nullable();

            // Full request / response detail for this step.
            $table->string('request_url', 512)->nullable();
            $table->longText('request_payload')->nullable();
            $table->longText('response_data')->nullable();

            // Anything extra (client ip, durations, trans_type, etc.).
            $table->text('meta')->nullable();

            // Millisecond precision so steps within the same second still sort
            // and read correctly. Set explicitly by the writer, never by the ORM.
            $table->timestamps();
        });

        // MySQL-only refinements: DATETIME(3) for millisecond precision, and a
        // JSON column for meta. Guarded so the migration still runs on sqlite
        // (used by the test suite).
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::getConnection()->statement(
                'ALTER TABLE transaction_event_logs
                    MODIFY created_at DATETIME(3) NULL,
                    MODIFY updated_at DATETIME(3) NULL'
            );
        }

        // Indexes tuned for how the admin queries this table:
        //  - ref_id / msisdn : the two search keys, and the timeline lookup.
        //  - provider, stage  : grid filters.
        //  - created_at      : date-range filter + "latest first" ordering.
        Schema::table('transaction_event_logs', function (Blueprint $table) {
            $table->index('ref_id', 'tel_ref_id_idx');
            $table->index('msisdn', 'tel_msisdn_idx');
            $table->index('stage', 'tel_stage_idx');
            $table->index('provider', 'tel_provider_idx');
            $table->index('created_at', 'tel_created_at_idx');
            // Composite: the summary/landing grid groups by ref_id and orders
            // by id, and the timeline reads one ref_id ordered by id.
            $table->index(['ref_id', 'id'], 'tel_ref_id_id_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transaction_event_logs');
    }
}
