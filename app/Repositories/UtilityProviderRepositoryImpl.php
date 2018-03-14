<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 23-Jul-19
 * Time: 2:51 PM
 */

namespace App\Repositories;


use App\Repositories\Contracts\UtilityProviderRepository;

class UtilityProviderRepositoryImpl extends AbstractEloquentRepository implements UtilityProviderRepository
{

    public function getActiveProviders($country)
    {
        return $this->model->active(1)->where('country',$country)->get();
    }
}