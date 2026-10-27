<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusEncaminhamento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O que ficou combinado numa reunião, com responsável e prazo. No conselho de classe nasce da deliberação sobre o aluno.
 */
class ReuniaoEncaminhamento extends Model
{
    protected $table = 'reuniao_encaminhamentos';

    protected $fillable = [
        'reuniao_id',
        'reuniao_aluno_id',
        'matricula_id',
        'descricao',
        'responsavel_user_id',
        'prazo',
        'status',
        'concluido_em',
        'concluido_por_user_id',
        'observacao_conclusao',
        'vencimento_avisado_em',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusEncaminhamento::class,
            'prazo' => 'date',
            'concluido_em' => 'datetime',
            'vencimento_avisado_em' => 'datetime',
        ];
    }

    public function reuniao(): BelongsTo
    {
        return $this->belongsTo(Reuniao::class, 'reuniao_id');
    }

    public function reuniaoAluno(): BelongsTo
    {
        return $this->belongsTo(ReuniaoAluno::class, 'reuniao_aluno_id');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }

    public function concluidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluido_por_user_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAbertos(Builder $query): Builder
    {
        return $query->where('status', StatusEncaminhamento::Aberto->value);
    }

    /**
     * Aberto e com o prazo anterior a hoje.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVencidos(Builder $query): Builder
    {
        return $query->abertos()->whereNotNull('prazo')->whereDate('prazo', '<', today());
    }

    public function estaAberto(): bool
    {
        return $this->status === StatusEncaminhamento::Aberto;
    }

    public function estaVencido(): bool
    {
        return $this->estaAberto() && $this->prazo !== null && $this->prazo->lt(today());
    }
}
