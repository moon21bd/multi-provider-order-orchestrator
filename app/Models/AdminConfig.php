<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdminConfig extends Model
{
    protected $table = 'admin_config';
    protected $fillable = ['value', 'name'];

}
