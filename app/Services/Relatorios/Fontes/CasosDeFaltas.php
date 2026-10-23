<?php

declare(strict_types=1);

namespace App\Services\Relatorios\Fontes;

use App\Contracts\FonteDeRelatorio;
use App\Enums\TipoColunaRelatorio;
use App\Models\NotificacaoConselhoTutelar;
use App\Support\Relatorios\ColunaRelatorio;
use App\Support\Relatorios\FiltroRelatorio;
use App\Support\Relatorios\IndexaItens;

/**
 * Notificações ao Conselho Tutelar por faltas excessivas (Onda 57): a lista que a coordenação leva às reuniões. As
 * observações e as tentativas de contato com a família ficam de fora do relatório.
 */
final class CasosDeFaltas implements FonteDeRelatorio
{
    use IndexaItens;

    private const STATUS = [
        'em_analise' => 'Em análise / mediação escolar',
        'notificado' => 'Ofício encaminhado ao Conselho',
        'resolvido' => 'Resolvido / retorno às aulas',
        'arquivado' => 'Arquivado',
    ];

    public function chave(): string
    {
        return 'casos_de_faltas';
    }

    public function titulo(): string
    {
        return 'Faltas excessivas (Conselho Tutelar)';
    }

    public function descricao(): string
    {
        return 'Notificações ao Conselho Tutelar por faltas excessivas, com a apuração e o andamento do ofício.';
    }

    public function permissao(): string
    {
        return 'ViewAny:NotificacaoConselhoTutelar';
    }

    public function colunas(): array
    {
        return $this->indexar([
            new ColunaRelatorio('aluno', 'Aluno', sensivel: true),
            new ColunaRelatorio('turma', 'Turma'),
            new ColunaRelatorio('oficio', 'Ofício'),
            new ColunaRelatorio('status', 'Situação'),
            new ColunaRelatorio('aulas_previstas', 'Aulas previstas', TipoColunaRelatorio::Inteiro, padrao: false),
            new ColunaRelatorio('faltas', 'Faltas', TipoColunaRelatorio::Inteiro),
            new ColunaRelatorio('faltas_justificadas', 'Faltas justificadas', TipoColunaRelatorio::Inteiro, padrao: false),
            new ColunaRelatorio('percentual', 'Faltas (%)', TipoColunaRelatorio::Decimal),
            new ColunaRelatorio('notificado_em', 'Notificado em', TipoColunaRelatorio::Data),
            new ColunaRelatorio('protocolo', 'Protocolo no Conselho', padrao: false),
        ]);
    }

    public function filtros(): array
    {
        return $this->indexar([
            new FiltroRelatorio('status', 'Situação', 'opcao', self::STATUS),
        ]);
    }

    public function linhas(array $filtros, int $limite): array
    {
        $consulta = NotificacaoConselhoTutelar::query()
            ->with(['matricula.pessoa', 'turma'])
            ->orderByDesc('id');

        if (array_key_exists((string) ($filtros['status'] ?? ''), self::STATUS)) {
            $consulta->where('status', (string) $filtros['status']);
        }

        return $consulta->limit($limite)->get()->map(fn (NotificacaoConselhoTutelar $notificacao): array => [
            'aluno' => $notificacao->matricula?->pessoa?->nome,
            'turma' => $notificacao->turma?->nome,
            'oficio' => $notificacao->numero_oficio,
            'status' => self::STATUS[$notificacao->status] ?? (string) $notificacao->status,
            'aulas_previstas' => $notificacao->total_aulas_previstas,
            'faltas' => $notificacao->total_faltas,
            'faltas_justificadas' => $notificacao->total_faltas_justificadas,
            'percentual' => (float) $notificacao->percentual_faltas,
            'notificado_em' => $notificacao->data_notificacao,
            'protocolo' => $notificacao->protocolo_conselho,
        ])->all();
    }
}
