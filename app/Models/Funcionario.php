<?php

namespace App\Models;

use App\Enums\RegimeContratacao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Funcionario extends Model
{
    use HasFactory;

    protected $fillable = [
        'pessoa_id',
        'cargo',
        'data_admissao',
        'data_desligamento',
        'regime',
        'carga_horaria_semanal',
        'unidade_id',
    ];

    protected function casts(): array
    {
        return [
            'data_admissao' => 'date',
            'data_desligamento' => 'date',
            'regime' => RegimeContratacao::class,
        ];
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function contratosTrabalho(): HasMany
    {
        return $this->hasMany(ContratoTrabalho::class)->orderByDesc('vigencia_inicio');
    }

    public function periodosFerias(): HasMany
    {
        return $this->hasMany(PeriodoFerias::class)->orderByDesc('periodo_aquisitivo_inicio');
    }

    /**
     * Histórico de avaliações de desempenho (Onda 19). Dado sensível: quem consulta deve passar por
     * `AvaliacaoDocente::visiveisPara()`.
     */
    public function avaliacoesDocente(): HasMany
    {
        return $this->hasMany(AvaliacaoDocente::class)->orderByDesc('data_observacao');
    }

    /**
     * Contrato de trabalho vigente — a linha de `contratos_trabalho` sem `vigencia_fim`
     * (ou com `vigencia_fim` ainda não alcançada).
     */
    public function contratoAtual(): ?ContratoTrabalho
    {
        return $this->contratosTrabalho
            ->first(fn (ContratoTrabalho $c) => $c->vigencia_fim === null || $c->vigencia_fim->isFuture());
    }

    /**
     * Ponto eletrônico (Onda 41). Os registros nunca são apagados com o funcionário (restrictOnDelete).
     */
    public function registrosPonto(): HasMany
    {
        return $this->hasMany(PontoRegistro::class)->orderByDesc('momento');
    }

    public function jornadasPonto(): HasMany
    {
        return $this->hasMany(PontoJornada::class)->orderByDesc('vigencia_inicio');
    }

    public function acordosBancoHoras(): HasMany
    {
        return $this->hasMany(PontoAcordoBancoHoras::class)->orderByDesc('vigencia_inicio');
    }

    public function lancamentosBancoHoras(): HasMany
    {
        return $this->hasMany(PontoBancoHorasLancamento::class)->orderByDesc('data')->orderByDesc('id');
    }

    public function isAtivo(): bool
    {
        return $this->data_desligamento === null;
    }
}
