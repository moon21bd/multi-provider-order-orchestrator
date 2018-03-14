<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 11/4/2018
 * Time: 8:39 PM
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProviderDatapackMapModel extends Model
{
    use SoftDeletes;
    protected $table = 'gw_datapack_map';


    public function provider()
    {
        return $this->belongsTo('App\Models\ProviderModel','provider_id');
    }

    public function operator()
    {
        return $this->belongsTo('App\Models\Operator', 'operator_id');
    }


}