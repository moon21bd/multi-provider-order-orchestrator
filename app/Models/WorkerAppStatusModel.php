<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class WorkerAppStatusModel extends Model
{
    use SoftDeletes;

    protected $table = "worker_app_status";
    protected $fillable = ['worker_id', 'last_check_in', 'created_at', 'updated_at'];
}
