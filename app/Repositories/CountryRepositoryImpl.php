<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 1/25/2018
 * Time: 2:03 PM
 */

namespace App\Repositories;


use App\Repositories\Contracts\CountryRepository;

class CountryRepositoryImpl extends AbstractEloquentRepository implements CountryRepository
{

    public function getAllCountry()
    {
        return $this->model->where('status', 1)->get();
    }
}