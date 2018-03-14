<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 11/4/2018
 * Time: 8:36 PM
 */

namespace App\Repositories;


use App\Repositories\Contracts\DatapackDetailsRepository;

class DatapackDetailsRepositoryImpl extends AbstractEloquentRepository implements DatapackDetailsRepository
{

    public function getAllDatapackInfo()
    {
        return $this->model->where('status', "=",1)->get();
    }

    public function getAllDatapackInfoByOperatorId ($operator_id)
    {
        return $this->model->where('operator_id', "=",$operator_id)
            ->where('status', "=",1)->get();
    }
}