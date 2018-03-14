<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 11/4/2018
 * Time: 8:32 PM
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DatapackDetailsModel extends Model
{
    use SoftDeletes;
    protected $table = 'datapack_details';

    public function operator()
    {
        return $this->belongsTo('App\Models\Operator');
    }

}