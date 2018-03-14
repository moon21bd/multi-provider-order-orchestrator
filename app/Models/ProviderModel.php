<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 12/24/2017
 * Time: 4:58 PM
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProviderModel extends Model
{
    use SoftDeletes;
    protected $table = 'providers';

    public function operatorMaps()
    {
        return $this->hasMany('App\Models\ProviderOperatorMapModel', 'provider_id', 'id');
    }

    public function datapackMaps()
    {
        return $this->hasMany('App\Models\ProviderOperatorMapModel', 'provider_id', 'id');
    }
}