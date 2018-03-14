<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 1/15/2019
 * Time: 2:42 PM
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BalanceInfoModel extends Model
{
    use SoftDeletes;
    protected $table='balance_info';
    protected $fillable =['name', 'balance', 'code', 'created_at', 'updated_at'];
}
