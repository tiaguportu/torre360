<?php

namespace App\Models;

use Database\Factories\CategoriaOsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoriaOs extends Model
{
    /** @use HasFactory<CategoriaOsFactory> */
    use HasFactory;

    protected $fillable = ['nome', 'cor'];

    public function ordemServicos()
    {
        return $this->belongsToMany(OrdemServico::class, 'categoria_os_ordem_servico');
    }
}
