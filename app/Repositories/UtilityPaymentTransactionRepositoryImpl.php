<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 24-Jul-19
 * Time: 2:08 PM
 */

namespace App\Repositories;


use App\Repositories\Contracts\UtilityPaymentTransactionRepository;

class UtilityPaymentTransactionRepositoryImpl extends AbstractEloquentRepository implements UtilityPaymentTransactionRepository
{
    public function updateTransaction($refId, $field, $value)
    {
        return $this->model->where('ref_id', '=', $refId)
            ->update([$field => $value]);
    }
}