<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CodigoBacen extends Model
{
    protected $table = 'codigo_bacens';

    protected $guarded = [];

    public function bancos(): HasMany
    {
        return $this->hasMany(Banco::class);
    }
}
