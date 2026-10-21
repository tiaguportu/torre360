<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SituacaoPlanoManutencao;
use Carbon\CarbonInterface;
use Database\Factories\PlanoManutencaoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Manutenção recorrente de um bem do patrimônio e/ou de um espaço (ex.: revisar os extintores a cada 180 dias).
 * Quando o vencimento se aproxima, o sistema abre uma `OrdemServico` e acompanha a execução.
 */
class PlanoManutencao extends Model
{
    /** @use HasFactory<PlanoManutencaoFactory> */
    use HasFactory;

    use LogsActivity;

    protected $table = 'planos_manutencao';

    /** Dias antes do vencimento em que o plano passa a ser considerado "a vencer". */
    public const DIAS_A_VENCER = 30;

    protected $fillable = [
        'titulo',
        'descricao',
        'bem_patrimonial_id',
        'sala_id',
        'periodicidade_dias',
        'proxima_execucao',
        'ultima_execucao',
        'antecedencia_dias',
        'prioridade',
        'categoria_os_id',
        'checklist',
        'ativo',
        'criado_por_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'periodicidade_dias' => 'integer',
            'antecedencia_dias' => 'integer',
            'proxima_execucao' => 'date',
            'ultima_execucao' => 'date',
            'checklist' => 'array',
            'ativo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['titulo', 'bem_patrimonial_id', 'sala_id', 'periodicidade_dias', 'proxima_execucao', 'ultima_execucao', 'antecedencia_dias', 'ativo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('plano_manutencao')
            ->setDescriptionForEvent(fn (string $evento): string => match ($evento) {
                'created' => 'Plano de manutenção criado.',
                'updated' => 'Plano de manutenção atualizado.',
                'deleted' => 'Plano de manutenção excluído.',
                default => "Plano de manutenção: {$evento}",
            });
    }

    public function bemPatrimonial(): BelongsTo
    {
        return $this->belongsTo(BemPatrimonial::class);
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    public function categoriaOs(): BelongsTo
    {
        return $this->belongsTo(CategoriaOs::class, 'categoria_os_id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }

    public function execucoes(): HasMany
    {
        return $this->hasMany(ExecucaoManutencao::class)->orderByDesc('data_prevista');
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /**
     * Planos ativos cujo vencimento já passou.
     */
    public function scopeVencidos(Builder $query, ?CarbonInterface $hoje = null): Builder
    {
        return $query->ativos()->whereDate('proxima_execucao', '<', ($hoje ?? today())->toDateString());
    }

    public function situacao(?CarbonInterface $hoje = null): SituacaoPlanoManutencao
    {
        $hoje = ($hoje ?? today())->copy()->startOfDay();

        if (! $this->ativo) {
            return SituacaoPlanoManutencao::Inativo;
        }

        if ($this->proxima_execucao->copy()->startOfDay()->lt($hoje)) {
            return SituacaoPlanoManutencao::Vencido;
        }

        if ($this->proxima_execucao->copy()->startOfDay()->lte($hoje->copy()->addDays(self::DIAS_A_VENCER))) {
            return SituacaoPlanoManutencao::AVencer;
        }

        return SituacaoPlanoManutencao::EmDia;
    }

    /**
     * "Bem 12345 — Projetor (Sala 3)", "Sala 3" ou "Bem …", conforme o alvo.
     */
    public function alvoDescricao(): string
    {
        $partes = [];

        if ($this->bemPatrimonial) {
            $partes[] = trim("{$this->bemPatrimonial->numero_patrimonio} — {$this->bemPatrimonial->descricao}", ' —');
        }

        if ($this->sala) {
            $partes[] = $this->bemPatrimonial ? "({$this->sala->nome})" : $this->sala->nome;
        }

        return $partes !== [] ? implode(' ', $partes) : '—';
    }
}
