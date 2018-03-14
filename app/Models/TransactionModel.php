<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 12/24/2017
 * Time: 8:30 PM
 */

namespace App\Models;



use Illuminate\Database\Eloquent\Model;

class TransactionModel extends Model
{
    protected $table = "transactions";

    protected $fillable = ["ref_id", "client_id", "mobile_no", "operator_code", "amount", "connection_type",
        "provider", "request_payload", "route", "status_route", "client_callback_url", "client_callback_code",
        "method", "response_data", "transaction_id", "message", "status", "sender", "trans_type", "datapack_id","try_count","beanstalkd_job_id", "message_modified"];
}