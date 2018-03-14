<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class UtilityPackageModel extends Model
{
    protected $table = 'utility_packages';

    public function provider()
    {
        return $this->belongsTo('App\Models\UtilityProviderModel', 'provider_id');
    }

    public function scopeActive($query, $value)
    {
        return $query->where('status', $value);
    }


}
