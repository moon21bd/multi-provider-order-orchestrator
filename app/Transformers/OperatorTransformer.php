<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 12/31/2017
 * Time: 3:05 PM
 */

namespace App\Transformers;


use App\Models\Operator;
use League\Fractal\TransformerAbstract;

class OperatorTransformer extends TransformerAbstract
{
    public function transform(Operator $item)
    {
        $formattedItem = [
            'name' => $item->name,
            'country' => $item->country,
            'code' => $item->code,
            'validation_regex' => $item->validation_regex
        ];

        return $formattedItem;
    }
}