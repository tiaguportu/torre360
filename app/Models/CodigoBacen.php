<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CodigoBacen extends Model
{
    protected $table = 'codigo_bacens';

    protected $fillable = ['codigo', 'nome_extenso', 'nome_reduzido', 'ispb'];

    public function bancos(): HasMany
    {
        return $this->hasMany(Banco::class);
    }
}
