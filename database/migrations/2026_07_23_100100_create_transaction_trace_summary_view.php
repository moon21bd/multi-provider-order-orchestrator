<?php

/**
 * @author  Raqibul Hasan Moon <rhmoon21@gmail.com>
 * @created 2026-07-23
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-transaction summary over transaction_event_logs.
 *
 * The Transaction Trace landing page in order_provider_admin shows ONE row
 * per transaction (ref_id): provider, step count, current/final status, start +
 * last time and duration. Clicking a row opens the full step-by-step timeline.
 * This view backs that landing grid so the grouping / final-status resolution
 * is done in SQL rather than in PHP over thousands of rows.
 *
 *  - final_status : status of the latest event that carried a status.
 *  - last_stage   : stage of the latest event.
 *  - id           : MAX(id), drives "latest transaction first" ordering and
 *                   doubles as the grid's row key.
 */
class CreateTransactionTraceSummaryView extends Migration
{
    public function up()
    {
        // View is MySQL-specific (GROUP_CONCAT / SUBSTRING_INDEX); skip on the
        // sqlite test connection so the suite can still migrate.
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        // Plain CREATE VIEW (not CREATE OR REPLACE): "OR REPLACE" additionally
        // requires the DROP privilege, which the application DB user is not
        // granted on this deployment. Re-running this migration on a database
        // that already has the view is handled by down() dropping it first.
        DB::statement("
            CREATE VIEW transaction_trace_summary AS
            SELECT
                ref_id,
                MAX(msisdn)  AS msisdn,
                MAX(provider) AS provider,
                COUNT(*)     AS steps,
                MIN(created_at) AS started_at,
                MAX(created_at) AS last_at,
                MAX(id)         AS id,
                TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) AS duration_seconds,
                CAST(SUBSTRING_INDEX(
                    GROUP_CONCAT(CASE WHEN status IS NOT NULL THEN status END ORDER BY id DESC),
                    ',', 1
                ) AS UNSIGNED) AS final_status,
                SUBSTRING_INDEX(GROUP_CONCAT(stage ORDER BY id DESC), ',', 1) AS last_stage
            FROM transaction_event_logs
            WHERE ref_id IS NOT NULL AND ref_id <> ''
            GROUP BY ref_id
        ");
    }

    public function down()
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('DROP VIEW IF EXISTS transaction_trace_summary');
    }
}
