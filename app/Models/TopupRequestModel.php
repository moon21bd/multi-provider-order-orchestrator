<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 7/11/2018
 * Time: 4:43 PM
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class TopupRequestModel extends Model
{
    protected $table = "topup_request";

    protected $fillable = ["ref_id", "srcuid", "srcpwd", "type", "msisdn", "status", "payload",
        "created_at", "updated_at", "amount", "response_id", "url", "status_url"];
}