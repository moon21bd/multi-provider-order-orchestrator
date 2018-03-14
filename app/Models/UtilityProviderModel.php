<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class UtilityProviderModel extends Model
{
    use SoftDeletes;
    protected $table = 'utility_providers';

    public function availableParams()
    {
        return $this->hasMany('App\Models\UtilityProviderParamModel', 'provider_id', 'id');
    }

    public function availablePackages()
    {
        return $this->hasMany('App\Models\UtilityPackageModel', 'provider_id', 'id');
    }

    public function scopeActive($query, $value)
    {
        return $query->where('status', $value);
    }

    public function params() {
        return $this->availableParams()->where('status','=', 1);
    }

    public function packages() {
        return $this->availablePackages()->where('status','=', 1);
    }

}
