<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 1/15/2019
 * Time: 2:42 PM
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileLocationModel extends Model
{
    protected $table = 'mobile_locations';
    protected $fillable = ['mobile_no', 'product_id', 'state', 'district', 'created_at', 'updated_at'];
}
