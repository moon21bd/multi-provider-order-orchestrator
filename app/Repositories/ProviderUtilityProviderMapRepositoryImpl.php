<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 24-Jul-19
 * Time: 4:56 PM
 */

namespace App\Repositories;


use App\Models\ProviderUtilityProviderMapModel;
use App\Repositories\Contracts\ProviderUtilityProviderMapRepository;

class ProviderUtilityProviderMapRepositoryImpl extends AbstractEloquentRepository implements ProviderUtilityProviderMapRepository
{

    public function findAllProviderByUtilityPackageCode($code)
    {
        return ProviderUtilityProviderMapModel::with('provider')->where('package_code',"=", $code)->get();
    }
}