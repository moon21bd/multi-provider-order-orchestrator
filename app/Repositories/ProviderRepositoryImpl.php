<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 12/24/2017
 * Time: 5:37 PM
 */

namespace App\Repositories;


use App\Models\ProviderDatapackMapModel;
use App\Models\ProviderModel;
use App\Models\ProviderOperatorMapModel;
use App\Repositories\Contracts\ProviderRepository;

class ProviderRepositoryImpl extends AbstractEloquentRepository implements ProviderRepository
{

    /**
     * @param $code
     * @return mixed
     */
    public function findAllProviderByOperatorCode($code)
    {
        return ProviderOperatorMapModel::with('provider')->where('operator_code',"=", $code)->get();
    }

    public function findAllProviderByCodeForDatapackId($id)
    {
        return ProviderDatapackMapModel::with('provider')->where('datapack_id',"=", $id)->get();
    }
}