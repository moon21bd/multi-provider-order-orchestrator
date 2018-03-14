<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 12/24/2017
 * Time: 8:31 PM
 */

namespace App\Repositories\Contracts;


interface TransactionRepository extends BaseRepository
{
    public function getSuccessfulTransactionByOperatorProvider($opCode, $provider);
    public function getProcessingTransactionByOperatorProviderNumberAmount($opCode, $provider, $number, $amount);
    public function updateTransaction($refId, $field, $value);
}