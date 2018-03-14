<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 12/24/2017
 * Time: 5:07 PM
 */

namespace App\Repositories\Contracts;


interface OperatorRepository extends BaseRepository
{
    public function getAllOperatorByCountry($country);
}