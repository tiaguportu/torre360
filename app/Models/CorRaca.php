<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorRaca extends Model
{
    protected $table = 'cor_raca';
    protected $guarded = [];

    public function pessoas(): HasMany
    {
        return $this->hasMany(Pessoa::class, 'cor_raca_id');
    }
}
