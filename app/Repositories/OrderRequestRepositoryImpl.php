<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 12/24/2017
 * Time: 4:03 PM
 */

namespace App\Repositories;


use App\Repositories\Contracts\OrderRequestRepository;

class OrderRequestRepositoryImpl extends AbstractEloquentRepository implements OrderRequestRepository
{
    public function save(array $data)
    {
        $orderRequest = $this->model->create($data);;
        return $orderRequest;
    }


}