<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Objecao extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'crm_objecoes';

    public const CATEGORIAS = [
        'preco' => 'Preço / Financeiro',
        'distancia' => 'Distância / Localização',
        'pedagogico' => 'Proposta Pedagógica / Método',
        'estrutura' => 'Estrutura / Espaço Físico',
        'vagas' => 'Vagas / Turnos',
        'outro' => 'Outras Dúvidas',
    ];

    public const CORES_CATEGORIAS = [
        'preco' => 'rose',
        'distancia' => 'amber',
        'pedagogico' => 'indigo',
        'estrutura' => 'teal',
        'vagas' => 'sky',
        'outro' => 'gray',
    ];

    protected $fillable = [
        'titulo',
        'categoria',
        'descricao',
        'resposta_sugerida',
        'pergunta_virada',
        'dicas_postura',
        'ordem',
        'is_ativo',
    ];

    protected function casts(): array
    {
        return [
            'is_ativo' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'titulo',
                'categoria',
                'is_ativo',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('crm');
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('is_ativo', true)->orderBy('ordem')->orderBy('titulo');
    }

    public function scopePorCategoria(Builder $query, string $categoria): Builder
    {
        return $query->where('categoria', $categoria);
    }

    public function rotuloCategoria(): string
    {
        return self::CATEGORIAS[$this->categoria] ?? ucfirst($this->categoria);
    }

    public function corCategoria(): string
    {
        return self::CORES_CATEGORIAS[$this->categoria] ?? 'gray';
    }
}
