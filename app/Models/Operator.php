<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 12/24/2017
 * Time: 4:59 PM
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Operator extends Model
{
    use SoftDeletes;
    protected $table = 'operators';

    public function providers()
    {
        return $this->hasMany('App\Models\ProviderOperatorMapModel', 'operator_id');
    }
}