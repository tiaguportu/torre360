<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\FonteDeRelatorio;
use App\Enums\FormatoRelatorio;
use App\Enums\FrequenciaAgendamento;
use App\Services\Relatorios\CatalogoDeRelatorios;
use App\Services\Relatorios\RelatorioExecutorService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Relatório configurado por uma pessoa (Onda 72): a fonte de dados, as colunas, os filtros, a ordem, o formato e, se quiser, o
 * agendamento. As regras de execução vivem em {@see RelatorioExecutorService}.
 */
class RelatorioSalvo extends Model
{
    protected $table = 'relatorios_salvos';

    protected $fillable = [
        'nome', 'fonte', 'colunas', 'filtros', 'ordenar_por', 'direcao', 'formato', 'agendado', 'frequencia', 'dia', 'hora',
        'destinatarios', 'proxima_execucao_em', 'ultima_execucao_em', 'criado_por_user_id',
    ];

    protected function casts(): array
    {
        return [
            'colunas' => 'array',
            'filtros' => 'array',
            'destinatarios' => 'array',
            'formato' => FormatoRelatorio::class,
            'frequencia' => FrequenciaAgendamento::class,
            'agendado' => 'boolean',
            'dia' => 'integer',
            'proxima_execucao_em' => 'datetime',
            'ultima_execucao_em' => 'datetime',
        ];
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }

    public function execucoes(): HasMany
    {
        return $this->hasMany(ExecucaoRelatorio::class, 'relatorio_salvo_id')->orderByDesc('id');
    }

    public function fonteDeDados(): ?FonteDeRelatorio
    {
        return app(CatalogoDeRelatorios::class)->da($this->fonte);
    }

    /**
     * Cada pessoa vê os seus relatórios; quem tem `VerTodos:RelatorioSalvo` vê os de todos.
     *
     * @param  Builder<RelatorioSalvo>  $query
     * @return Builder<RelatorioSalvo>
     */
    public function scopeVisiveisPara(Builder $query, User $usuario): Builder
    {
        return $usuario->can('VerTodos:RelatorioSalvo') ? $query : $query->where('criado_por_user_id', $usuario->id);
    }

    /**
     * Próxima execução depois de `$apos`, na hora marcada: todo dia, na semana (dia 1 = segunda a 7 = domingo) ou no mês (dia 1
     * a 28, para existir em todos os meses). Sempre estritamente depois de `$apos`.
     */
    public function calcularProximaExecucao(?CarbonInterface $apos = null): ?Carbon
    {
        if (! $this->frequencia || blank($this->hora) || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $this->hora) !== 1) {
            return null;
        }

        $apos = Carbon::instance($apos ?? now());
        [$hora, $minuto] = array_map('intval', explode(':', (string) $this->hora));

        $candidata = $apos->copy()->setTime($hora, $minuto, 0);

        switch ($this->frequencia) {
            case FrequenciaAgendamento::Diaria:
                break;

            case FrequenciaAgendamento::Semanal:
                $dia = min(7, max(1, (int) ($this->dia ?? 1)));
                $candidata->addDays(($dia - $candidata->isoWeekday() + 7) % 7);
                break;

            case FrequenciaAgendamento::Mensal:
                $dia = min(28, max(1, (int) ($this->dia ?? 1)));
                $candidata->day($dia);
                break;
        }

        while ($candidata->lte($apos)) {
            match ($this->frequencia) {
                FrequenciaAgendamento::Diaria => $candidata->addDay(),
                FrequenciaAgendamento::Semanal => $candidata->addWeek(),
                FrequenciaAgendamento::Mensal => $candidata->addMonthNoOverflow()->day(min(28, max(1, (int) ($this->dia ?? 1)))),
            };
        }

        return $candidata;
    }

    /**
     * Resumo do agendamento em palavras, para a lista.
     */
    public function descricaoDoAgendamento(): string
    {
        if (! $this->agendado || ! $this->frequencia) {
            return 'Sem agendamento';
        }

        $quando = match ($this->frequencia) {
            FrequenciaAgendamento::Diaria => 'Todo dia',
            FrequenciaAgendamento::Semanal => 'Toda '.(['1' => 'segunda', '2' => 'terça', '3' => 'quarta', '4' => 'quinta', '5' => 'sexta', '6' => 'sábado', '7' => 'domingo'][(string) ($this->dia ?? 1)] ?? 'semana'),
            FrequenciaAgendamento::Mensal => 'Todo dia '.($this->dia ?? 1),
        };

        return "{$quando} às {$this->hora}";
    }
}
