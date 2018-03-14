<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class SimBoxLogModel extends Model
{
    use SoftDeletes;

    protected $table = "simbox_logs";
    protected $fillable = ['ref_id', 'mobile_no', 'amount', 'message', 'data', 'status', 'operator', 'sim_no'];
}
