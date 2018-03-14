<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 12/24/2017
 * Time: 3:53 PM
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class OrderRequestModel extends Model
{

    protected $table = "order_request";
    protected $fillable = ['client_id', 'mobile_no', 'operator_code', 'amount', 'connection_type', 'status', 'ref_id', 'sender',"trans_type","datapack_id"];


}