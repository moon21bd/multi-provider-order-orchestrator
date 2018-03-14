<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 1/9/2019
 * Time: 7:20 PM
 */

namespace App\Repositories;


use App\Repositories\Contracts\SmsReceiveRepository;

class SmsReceiveRepositoryImpl extends AbstractEloquentRepository implements SmsReceiveRepository
{
    public function save(array $data)
    {
        $savedData = $this->model->create($data);;
        return $savedData;
    }
}