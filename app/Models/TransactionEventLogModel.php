<?php

namespace App\Models;

/**
 * @author  Raqibul Hasan Moon <rhmoon21@gmail.com>
 * @created 2026-07-23
 */

use Illuminate\Database\Eloquent\Model;

/**
 * Step-wise transaction lifecycle trace row.
 *
 * Written by App\Traits\ProviderLifecycleLogTrait alongside the existing
 * storage/logs/provider-*.log file, so every step that is already logged to disk
 * is also queryable. order_provider_admin reads these rows to build the
 * per-transaction timeline searchable by ref_id / mobile no.
 */
class TransactionEventLogModel extends Model
{
    protected $table = 'transaction_event_logs';

    // created_at/updated_at are set explicitly by the writer with millisecond
    // precision; disable Eloquent's automatic second-precision timestamps so it
    // does not overwrite them.
    public $timestamps = false;

    protected $fillable = [
        'created_at',
        'updated_at',
        'ref_id',
        'msisdn',
        'source',
        'stage',
        'provider',
        'attempt_no',
        'job_id',
        'http_code',
        'status_code',
        'status',
        'message',
        'request_url',
        'request_payload',
        'response_data',
        'meta',
    ];
}
