<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 12/24/2017
 * Time: 5:38 PM
 */

namespace App\Repositories;


use App\Repositories\Contracts\OperatorRepository;

class OperatorRepositoryImpl extends AbstractEloquentRepository implements OperatorRepository
{

    public function getAllOperatorByCountry($country)
    {
        return $this->model->where('country', $country)->get();
    }
}