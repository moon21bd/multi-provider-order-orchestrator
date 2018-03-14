<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 12/24/2017
 * Time: 5:06 PM
 */

namespace App\Repositories\Contracts;


interface ProviderRepository extends BaseRepository
{
    /**
     * @param $code
     * @return mixed
     */
    public function findAllProviderByOperatorCode($code);

    public function findAllProviderByCodeForDatapackId($id);
}