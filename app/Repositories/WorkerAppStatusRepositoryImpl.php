<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 1/23/2019
 * Time: 4:20 PM
 */

namespace App\Repositories;


use App\Repositories\Contracts\SimBoxLogRepository;
use App\Repositories\Contracts\WorkerAppStatusRepository;

class WorkerAppStatusRepositoryImpl extends AbstractEloquentRepository implements WorkerAppStatusRepository
{
    public function save(array $data)
    {
        $savedData = $this->model->create($data);;
        return $savedData;
    }
}