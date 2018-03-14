<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class UtilityProviderParamModel extends Model
{
    protected $table = 'utility_provider_params';

    public function provider()
    {
        return $this->belongsTo('App\Models\UtilityProviderModel','provider_id');
    }

    public function scopeActive($query, $value)
    {
        return $query->where('status', $value);
    }
}
