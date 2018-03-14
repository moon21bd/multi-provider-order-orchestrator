<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 23-Jul-19
 * Time: 8:06 PM
 */

namespace App\Repositories\Contracts;


interface UtilityPaymentTransactionRepository extends BaseRepository
{
    public function updateTransaction($refId, $field, $value);
}