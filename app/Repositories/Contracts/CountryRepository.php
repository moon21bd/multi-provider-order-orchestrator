<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 1/25/2018
 * Time: 2:02 PM
 */

namespace App\Repositories\Contracts;


interface CountryRepository extends BaseRepository
{
    public function getAllCountry();
}