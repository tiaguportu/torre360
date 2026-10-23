<?php

declare(strict_types=1);

namespace App\Services\Relatorios\Fontes;

use App\Contracts\FonteDeRelatorio;
use App\Enums\StatusCasoFrequencia;
use App\Enums\TipoColunaRelatorio;
use App\Models\CasoFrequencia;
use App\Support\Relatorios\ColunaRelatorio;
use App\Support\Relatorios\FiltroRelatorio;
use App\Support\Relatorios\IndexaItens;

/**
 * Casos de faltas excessivas (Onda 57): a lista que a coordenação leva às reuniões e ao Conselho Tutelar. As observações dos
 * contatos com a família e a resposta do Conselho (criptografadas) não são oferecidas.
 */
final class CasosDeFaltas implements FonteDeRelatorio
{
    use IndexaItens;

    public function chave(): string
    {
        return 'casos_de_faltas';
    }

    public function titulo(): string
    {
        return 'Casos de faltas';
    }

    public function descricao(): string
    {
        return 'Casos de faltas excessivas com a apuração do dia da abertura e o andamento (contato, ofício, resposta).';
    }

    public function permissao(): string
    {
        return 'ViewAny:CasoFrequencia';
    }

    public function colunas(): array
    {
        return $this->indexar([
            new ColunaRelatorio('aluno', 'Aluno', sensivel: true),
            new ColunaRelatorio('turma', 'Turma'),
            new ColunaRelatorio('status', 'Situação do caso'),
            new ColunaRelatorio('aulas_previstas', 'Aulas previstas', TipoColunaRelatorio::Inteiro, padrao: false),
            new ColunaRelatorio('faltas', 'Faltas', TipoColunaRelatorio::Inteiro),
            new ColunaRelatorio('faltas_justificadas', 'Faltas justificadas', TipoColunaRelatorio::Inteiro, padrao: false),
            new ColunaRelatorio('percentual', 'Faltas (%)', TipoColunaRelatorio::Decimal),
            new ColunaRelatorio('aberto_em', 'Aberto em', TipoColunaRelatorio::Data),
            new ColunaRelatorio('notificado_em', 'Ofício enviado em', TipoColunaRelatorio::Data, padrao: false),
            new ColunaRelatorio('respondido_em', 'Resposta em', TipoColunaRelatorio::Data, padrao: false),
        ]);
    }

    public function filtros(): array
    {
        return $this->indexar([
            new FiltroRelatorio('status', 'Situação do caso', 'opcao', collect(StatusCasoFrequencia::cases())->mapWithKeys(fn (StatusCasoFrequencia $s): array => [$s->value => $s->getLabel()])->all()),
        ]);
    }

    public function linhas(array $filtros, int $limite): array
    {
        $consulta = CasoFrequencia::query()
            ->with(['matricula.pessoa', 'matricula.turma'])
            ->orderByDesc('id');

        if (filled($filtros['status'] ?? null) && StatusCasoFrequencia::tryFrom((string) $filtros['status'])) {
            $consulta->where('status', (string) $filtros['status']);
        }

        return $consulta->limit($limite)->get()->map(fn (CasoFrequencia $caso): array => [
            'aluno' => $caso->matricula?->pessoa?->nome,
            'turma' => $caso->matricula?->turma?->nome,
            'status' => $caso->status->getLabel(),
            'aulas_previstas' => $caso->aulas_previstas,
            'faltas' => $caso->faltas,
            'faltas_justificadas' => $caso->faltas_justificadas,
            'percentual' => (float) $caso->percentual_faltas,
            'aberto_em' => $caso->created_at,
            'notificado_em' => $caso->notificado_em,
            'respondido_em' => $caso->respondido_em,
        ])->all();
    }
}
