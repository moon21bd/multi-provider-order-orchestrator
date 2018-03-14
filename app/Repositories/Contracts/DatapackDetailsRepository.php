<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 11/4/2018
 * Time: 8:36 PM
 */

namespace App\Repositories\Contracts;


interface DatapackDetailsRepository extends BaseRepository
{

    public function getAllDatapackInfo();
    public function getAllDatapackInfoByOperatorId($operator_id);

}