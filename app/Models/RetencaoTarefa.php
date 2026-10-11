<?php

namespace App\Models;

use App\Enums\StatusRetencaoTarefa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetencaoTarefa extends Model
{
    use HasFactory;

    protected $table = 'retencao_tarefas';

    protected $fillable = [
        'matricula_id',
        'aluno_pessoa_id',
        'rematricula_id',
        'score_risco',
        'status',
        'prioridade',
        'titulo',
        'fatores_resumo',
        'descricao',
        'responsavel_user_id',
        'data_limite',
        'alertado_coordenacao_em',
        'resolvido_em',
        'observacoes_resolucao',
    ];

    protected function casts(): array
    {
        return [
            'score_risco' => 'integer',
            'status' => StatusRetencaoTarefa::class,
            'fatores_resumo' => 'array',
            'data_limite' => 'date',
            'alertado_coordenacao_em' => 'datetime',
            'resolvido_em' => 'datetime',
        ];
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'aluno_pessoa_id');
    }

    public function rematricula(): BelongsTo
    {
        return $this->belongsTo(Rematricula::class, 'rematricula_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }

    public function scopePendentes(Builder $query): Builder
    {
        return $query->where('status', StatusRetencaoTarefa::Pendente);
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->whereIn('status', [
            StatusRetencaoTarefa::Pendente,
            StatusRetencaoTarefa::EmAndamento,
        ]);
    }
}
