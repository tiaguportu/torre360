<?php

namespace App\Models;

use Database\Factories\OrdemServicoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdemServico extends Model
{
    /** @use HasFactory<OrdemServicoFactory> */
    use HasFactory;

    protected $fillable = [
        'titulo',
        'descricao',
        'custo_estimado',
        'percentual_conclusao',
        'prazo_conclusao',
        'prioridade',
        'status',
        'fotos',
    ];

    protected function casts(): array
    {
        return [
            'fotos' => 'array',
            'prazo_conclusao' => 'date',
            'custo_estimado' => 'decimal:2',
            'percentual_conclusao' => 'integer',
        ];
    }

    public function categorias()
    {
        return $this->belongsToMany(CategoriaOs::class, 'categoria_os_ordem_servico');
    }

    public function anotacoes()
    {
        return $this->hasMany(AnotacaoOs::class);
    }
}
