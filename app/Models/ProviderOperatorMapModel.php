<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 12/24/2017
 * Time: 5:00 PM
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProviderOperatorMapModel extends Model
{
    use SoftDeletes;
    protected $table = 'gw_operator_map';


    public function provider()
    {
        return $this->belongsTo('App\Models\ProviderModel','provider_id');
    }

    public function operator()
    {
        return $this->belongsTo('App\Models\Operator');
    }
}