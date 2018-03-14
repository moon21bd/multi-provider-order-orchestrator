<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class ProviderUtilityProviderMapModel extends Model
{
    use SoftDeletes;
    protected $table = 'gw_utility_provider_map';

    public function provider()
    {
        return $this->belongsTo('App\Models\ProviderModel','provider_id');
    }

    public function provider()
    {
        return $this->belongsTo('App\Models\UtilityProviderModel', 'provider_id');
    }
}
