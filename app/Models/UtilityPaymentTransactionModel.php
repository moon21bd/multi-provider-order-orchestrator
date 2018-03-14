<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class UtilityPaymentTransactionModel extends Model
{
    protected $table = 'utility_payment_transactions';
    protected $fillable = ["ref_id", "client_id", "account_id", "provider_id", "amount", "package_id", "sender",
        "provider_id", "utility_category", "route", "request_payload", "client_request_payload", "status_route",
        "client_callback_url", "client_callback_code", "request_response", "status_response", "transaction_id",
        "try_count", "message", "status", "beanstalkd_job_id", "client_txn_id"];
}
