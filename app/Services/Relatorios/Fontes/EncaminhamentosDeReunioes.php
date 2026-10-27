<?php

declare(strict_types=1);

namespace App\Services\Relatorios\Fontes;

use App\Contracts\FonteDeRelatorio;
use App\Enums\StatusEncaminhamento;
use App\Enums\StatusReuniao;
use App\Enums\TipoColunaRelatorio;
use App\Enums\TipoReuniao;
use App\Models\ReuniaoEncaminhamento;
use App\Support\Relatorios\ColunaRelatorio;
use App\Support\Relatorios\FiltraPeriodo;
use App\Support\Relatorios\FiltroRelatorio;
use App\Support\Relatorios\IndexaItens;
use Illuminate\Database\Eloquent\Builder;

/**
 * Encaminhamentos das reuniões da equipe (Ondas 27 e 32), com responsável, prazo e situação. Ficam de fora as reuniões sigilosas
 * e as canceladas: a permissão da fonte é a da coordenação, que não vê as sigilosas dos outros.
 */
final class EncaminhamentosDeReunioes implements FonteDeRelatorio
{
    use FiltraPeriodo;
    use IndexaItens;

    public function chave(): string
    {
        return 'encaminhamentos_de_reunioes';
    }

    public function titulo(): string
    {
        return 'Encaminhamentos de reuniões';
    }

    public function descricao(): string
    {
        return 'O que ficou combinado nas reuniões da equipe, com responsável, prazo e situação (sem as reuniões sigilosas).';
    }

    public function permissao(): string
    {
        return 'VerTodas:Reuniao';
    }

    public function colunas(): array
    {
        return $this->indexar([
            new ColunaRelatorio('reuniao', 'Reunião'),
            new ColunaRelatorio('tipo', 'Tipo'),
            new ColunaRelatorio('data_reuniao', 'Data da reunião', TipoColunaRelatorio::Data),
            new ColunaRelatorio('descricao', 'O que foi combinado', sensivel: true),
            new ColunaRelatorio('aluno', 'Aluno', sensivel: true, padrao: false),
            new ColunaRelatorio('responsavel', 'Responsável', sensivel: true),
            new ColunaRelatorio('prazo', 'Prazo', TipoColunaRelatorio::Data),
            new ColunaRelatorio('situacao', 'Situação'),
            new ColunaRelatorio('vencido', 'Vencido'),
            new ColunaRelatorio('concluido_em', 'Encerrado em', TipoColunaRelatorio::Data, padrao: false),
        ]);
    }

    public function filtros(): array
    {
        return $this->indexar([
            new FiltroRelatorio('prazo', 'Prazo', 'data'),
            new FiltroRelatorio('status', 'Situação', 'opcao', collect(StatusEncaminhamento::cases())->mapWithKeys(fn (StatusEncaminhamento $s): array => [$s->value => $s->getLabel()])->all()),
            new FiltroRelatorio('tipo', 'Tipo de reunião', 'opcao', collect(TipoReuniao::cases())->reject(fn (TipoReuniao $t): bool => $t->sempreConfidencial())->mapWithKeys(fn (TipoReuniao $t): array => [$t->value => $t->getLabel()])->all()),
        ]);
    }

    public function linhas(array $filtros, int $limite): array
    {
        $consulta = ReuniaoEncaminhamento::query()
            ->with(['reuniao', 'responsavel', 'matricula.pessoa'])
            ->whereHas('reuniao', fn (Builder $q) => $q
                ->where('confidencial', false)
                ->where('tipo', '!=', TipoReuniao::Devolutiva->value)
                ->where('status', '!=', StatusReuniao::Cancelada->value)
                ->when(
                    filled($filtros['tipo'] ?? null) && TipoReuniao::tryFrom((string) $filtros['tipo']),
                    fn (Builder $b) => $b->where('tipo', (string) $filtros['tipo']),
                ))
            ->orderBy('prazo')
            ->orderBy('id');

        $this->aplicarPeriodo($consulta, 'prazo', $filtros, 'prazo');

        if (filled($filtros['status'] ?? null) && StatusEncaminhamento::tryFrom((string) $filtros['status'])) {
            $consulta->where('status', (string) $filtros['status']);
        }

        return $consulta->limit($limite)->get()->map(fn (ReuniaoEncaminhamento $e): array => [
            'reuniao' => $e->reuniao->titulo,
            'tipo' => $e->reuniao->tipo->getLabel(),
            'data_reuniao' => $e->reuniao->inicio_em,
            'descricao' => $e->descricao,
            'aluno' => $e->matricula?->pessoa?->nome,
            'responsavel' => $e->responsavel?->name,
            'prazo' => $e->prazo,
            'situacao' => $e->status->getLabel(),
            'vencido' => $e->estaVencido() ? 'Sim' : 'Não',
            'concluido_em' => $e->concluido_em,
        ])->all();
    }
}
