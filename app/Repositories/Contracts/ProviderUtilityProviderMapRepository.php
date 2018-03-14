<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 24-Jul-19
 * Time: 4:55 PM
 */

namespace App\Repositories\Contracts;


interface ProviderUtilityProviderMapRepository extends BaseRepository
{
    public function findAllProviderByUtilityPackageCode($code);
}