<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class SmsReceiveModel extends Model
{
    use SoftDeletes;

    protected $table = "sms_receive";
    protected $fillable = ['text', 'sender', 'operator', 'text', 'receive_time', 'operator_id', 'type', 'status', 'sim_no'];
}
