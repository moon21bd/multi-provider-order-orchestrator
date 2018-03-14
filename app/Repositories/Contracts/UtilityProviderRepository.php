<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 23-Jul-19
 * Time: 2:50 PM
 */

namespace App\Repositories\Contracts;


interface UtilityProviderRepository extends BaseRepository
{
    public function getActiveProviders($country);
}