<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 12/31/2017
 * Time: 3:05 PM
 */

namespace App\Transformers;


use App\Models\CountryModel;
use League\Fractal\TransformerAbstract;

class CountryTransformer extends TransformerAbstract
{
    public function transform(CountryModel $item)
    {
        $formattedItem = [
            'name' => $item->name,
            'code' => $item->code
        ];

        return $formattedItem;
    }
}