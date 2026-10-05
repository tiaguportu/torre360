<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Concorrente extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'crm_concorrentes';

    public const FAIXAS_PRECO = [
        'mais_barato' => 'Mais acessível que nossa escola',
        'equivalente' => 'Faixa de valor equivalente',
        'mais_caro' => 'Mais caro / Premium',
    ];

    public const FATORES_DECISAO = [
        'Preço / Bolsa' => 'Preço da mensalidade / Bolsa de estudos',
        'Localização / Distância' => 'Mais próximo de casa ou do trabalho',
        'Turno Integral / Contraturno' => 'Proposta de período integral ou horários',
        'Estrutura Física' => 'Espaço esportivo / Instalações',
        'Metodologia Pedagógica' => 'Proposta de ensino / Linha pedagógica',
        'Indicação / Amigos' => 'Filhos de conhecidos ou amigos matriculados',
        'Outro' => 'Outro fator decisivo',
    ];

    protected $fillable = [
        'nome',
        'sigla',
        'cidade_id',
        'bairro',
        'faixa_preco',
        'mensalidade_estimada',
        'proposta_pedagogica',
        'pontos_fortes',
        'pontos_fracos',
        'diferenciais_nossos',
        'estrategia_abordagem',
        'observacoes',
        'is_ativo',
    ];

    protected function casts(): array
    {
        return [
            'mensalidade_estimada' => 'decimal:2',
            'pontos_fortes' => 'array',
            'pontos_fracos' => 'array',
            'is_ativo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'nome',
                'faixa_preco',
                'mensalidade_estimada',
                'proposta_pedagogica',
                'is_ativo',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('crm');
    }

    public function cidade(): BelongsTo
    {
        return $this->belongsTo(Cidade::class);
    }

    public function interessadosPerdidos(): HasMany
    {
        return $this->hasMany(Interessado::class, 'concorrente_id');
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('is_ativo', true);
    }

    /**
     * Retorna a descrição formatada da faixa de preço.
     */
    public function rotuloFaixaPreco(): ?string
    {
        return $this->faixa_preco ? (self::FAIXAS_PRECO[$this->faixa_preco] ?? ucfirst($this->faixa_preco)) : null;
    }
}
