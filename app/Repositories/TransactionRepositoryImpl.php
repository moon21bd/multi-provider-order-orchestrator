<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 12/24/2017
 * Time: 8:31 PM
 */

namespace App\Repositories;

use App\Repositories\Contracts\TransactionRepository;
use Carbon\Carbon;

class TransactionRepositoryImpl extends AbstractEloquentRepository implements TransactionRepository
{
    public function save(array $data)
    {
        $orderRequest = $this->model->create($data);;
        return $orderRequest;
    }

    public function getSuccessfulTransactionByOperatorProvider($opCode, $provider)
    {
        return $this->model->where('operator_code', "=", $opCode)
            ->where('provider', "=", $provider)
            ->where('status', "=", 2)->orderBy('id', 'desc')->first();
    }

    public function getProcessingTransactionByOperatorProviderNumberAmount($opCode, $provider, $number, $amount)
    {
        return $this->model->where('operator_code', "=", $opCode)
            ->where('provider', "=", $provider)
            ->where('amount', "=", $amount)
            ->where('mobile_no', "like", '%' . $number . '%')
            ->orWhere('mobile_no', '=', $number)
            ->where('status', "=", 1)->orderBy('id', 'desc')->first();
    }

    public function updateTransaction($refId, $field, $value)
    {
        return $this->model->where('ref_id', '=', $refId)
            ->update([$field => $value]);
    }

    public function hasTopupRequestInLastSevenMinutes($mobileNo)
    {
        $formatted_date = Carbon::now()->subMinutes(7)->toDateTimeString();
        $result = $this->model->where('mobile_no', '=', $mobileNo)->where('updated_at', '>=', $formatted_date)->count();
        if ($result < 2) {
            return false;
        }
        return true;
    }
}