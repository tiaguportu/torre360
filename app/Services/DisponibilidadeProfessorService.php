<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TipoDisponibilidadeProfessor;
use App\Models\DisponibilidadeProfessor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Disponibilidade dos professores (Onda 64).
 *
 * Regra: sem nenhuma janela cadastrada o professor está livre; "indisponível" sempre bloqueia; havendo ao menos uma janela
 * "disponível", ele só pode dar aula dentro delas (nos dias sem janela, não dá aula). Janelas "disponível" que se encostam
 * valem como uma só.
 */
class DisponibilidadeProfessorService
{
    public const DIAS = [0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado'];

    /**
     * Janelas dos professores, agrupadas por professor.
     *
     * @param  iterable<int>  $pessoaIds
     * @return Collection<int, Collection<int, DisponibilidadeProfessor>>
     */
    public function janelasDe(iterable $pessoaIds): Collection
    {
        $ids = collect($pessoaIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return DisponibilidadeProfessor::query()
            ->whereIn('pessoa_id', $ids)
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio')
            ->get()
            ->groupBy('pessoa_id');
    }

    /**
     * Por que o professor não pode dar aula nesse horário, ou null se pode.
     *
     * @param  Collection<int, DisponibilidadeProfessor>  $janelas  só as do professor em questão
     */
    public function motivoDeIndisponibilidade(Collection $janelas, int $dia, string $inicio, string $fim): ?string
    {
        $inicio = substr($inicio, 0, 5);
        $fim = substr($fim, 0, 5);

        foreach ($janelas as $janela) {
            if ($janela->tipo === TipoDisponibilidadeProfessor::Indisponivel && $janela->dia_semana === $dia && $janela->inicio() < $fim && $janela->fim() > $inicio) {
                return "indisponível das {$janela->inicio()} às {$janela->fim()}".($janela->observacao ? " ({$janela->observacao})" : '');
            }
        }

        $disponiveis = $janelas->filter(fn (DisponibilidadeProfessor $j): bool => $j->tipo === TipoDisponibilidadeProfessor::Disponivel);

        if ($disponiveis->isEmpty()) {
            return null;
        }

        foreach ($this->unir($disponiveis->where('dia_semana', $dia)) as [$de, $ate]) {
            if ($de <= $inicio && $ate >= $fim) {
                return null;
            }
        }

        return 'fora das janelas em que informou estar disponível';
    }

    /**
     * Junta janelas que se cruzam ou se encostam no mesmo dia.
     *
     * @param  Collection<int, DisponibilidadeProfessor>  $janelas
     * @return list<array{0: string, 1: string}>
     */
    private function unir(Collection $janelas): array
    {
        $unidas = [];

        foreach ($janelas->sortBy(fn (DisponibilidadeProfessor $j): string => $j->inicio())->values() as $janela) {
            $ultimo = count($unidas) - 1;

            if ($ultimo >= 0 && $janela->inicio() <= $unidas[$ultimo][1]) {
                $unidas[$ultimo][1] = max($unidas[$ultimo][1], $janela->fim());

                continue;
            }

            $unidas[] = [$janela->inicio(), $janela->fim()];
        }

        return $unidas;
    }

    /**
     * Texto curto das janelas, para a tela: "Seg 07:30–12:00 · Qua indisponível 10:00–11:00".
     *
     * @param  Collection<int, DisponibilidadeProfessor>  $janelas
     */
    public function resumo(Collection $janelas): string
    {
        if ($janelas->isEmpty()) {
            return 'Sem restrições';
        }

        return $janelas
            ->map(fn (DisponibilidadeProfessor $j): string => mb_substr(self::DIAS[$j->dia_semana] ?? '?', 0, 3).' '.($j->tipo === TipoDisponibilidadeProfessor::Indisponivel ? 'indisponível ' : '')."{$j->inicio()}–{$j->fim()}")
            ->implode(' · ');
    }

    /**
     * Troca todas as janelas do professor pelas informadas. Cada janela: dia_semana (0 a 6), hora_inicio, hora_fim (maior
     * que o início), tipo e observacao opcional.
     *
     * @param  list<array<string, mixed>>  $janelas
     *
     * @throws \DomainException
     */
    public function substituir(int $pessoaId, array $janelas): void
    {
        $novas = [];

        foreach ($janelas as $janela) {
            $dia = (int) ($janela['dia_semana'] ?? -1);
            $inicio = substr((string) ($janela['hora_inicio'] ?? ''), 0, 5);
            $fim = substr((string) ($janela['hora_fim'] ?? ''), 0, 5);
            $tipo = $janela['tipo'] ?? TipoDisponibilidadeProfessor::Disponivel->value;
            $tipo = $tipo instanceof TipoDisponibilidadeProfessor ? $tipo : TipoDisponibilidadeProfessor::tryFrom((string) $tipo);

            if ($dia < 0 || $dia > 6 || $inicio === '' || $fim === '' || $fim <= $inicio || $tipo === null) {
                throw new \DomainException('Confira as janelas: informe o dia, o início e um fim depois do início.');
            }

            $novas[] = [
                'pessoa_id' => $pessoaId,
                'dia_semana' => $dia,
                'hora_inicio' => $inicio.':00',
                'hora_fim' => $fim.':00',
                'tipo' => $tipo->value,
                'observacao' => filled($janela['observacao'] ?? null) ? mb_substr(trim((string) $janela['observacao']), 0, 160) : null,
            ];
        }

        DB::transaction(function () use ($pessoaId, $novas): void {
            DisponibilidadeProfessor::query()->where('pessoa_id', $pessoaId)->delete();

            foreach ($novas as $janela) {
                DisponibilidadeProfessor::create($janela);
            }
        });
    }
}
