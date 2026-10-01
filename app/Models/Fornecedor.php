<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fornecedor extends Model
{
    protected $table = 'fornecedors';

    protected $fillable = ['razao_social', 'nome_fantasia', 'cnpj', 'email', 'telefone'];

    public function transacoes(): HasMany
    {
        return $this->hasMany(TransacaoBancaria::class);
    }

    public function getNomeCnpjAttribute(): string
    {
        return "{$this->razao_social} - {$this->cnpj}";
    }
}
