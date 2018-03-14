<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 1/23/2019
 * Time: 4:20 PM
 */

namespace App\Repositories;


use App\Repositories\Contracts\SimBoxLogRepository;

class SimBoxLogRepositoryImpl extends AbstractEloquentRepository implements SimBoxLogRepository
{
    public function save(array $data)
    {
        $savedData = $this->model->create($data);;
        return $savedData;
    }
}