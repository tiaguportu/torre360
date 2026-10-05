<?php

namespace App\Services;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Regras de movimentação de um lead no funil, num único lugar. Antes, o Kanban, a ação de linha da
 * tabela e a edição em lote tinham cada um a sua cópia (com motivos e registros divergentes), e a
 * edição em lote conseguia mandar leads para "Perdido"/"Matriculado" sem passar pelas travas.
 *
 * Regras:
 *  - Etapas ativas se movem livremente, exceto a partir de um lead já matriculado.
 *  - Etapa de perda exige motivo da lista padronizada (`Interessado::MOTIVOS_PERDA`).
 *  - Etapa de ganho nunca é atribuída "no braço": a conversão vem da matrícula (assistente ou
 *    matrícula online) por `InteressadoMatriculaService::registrarConversao()`.
 *
 * Violações lançam `DomainException` com mensagem pronta para exibir ao usuário.
 */
class LeadFunilService
{
    public const MOTIVO_CONCORRENCIA = 'Concorrência';

    /**
     * Move o lead para uma etapa ativa (não final). Sair de uma etapa de perda limpa o motivo e deixa
     * um registro de reativação na linha do tempo.
     *
     * @return bool false quando o lead já estava na etapa (nada a fazer)
     */
    public function moverParaEtapaAtiva(Interessado $lead, StatusInteressado $novoStatus, ?int $usuarioId = null): bool
    {
        $lead->loadMissing('status');

        if ($novoStatus->is_final || $novoStatus->is_ganho || $novoStatus->isPerda()) {
            throw new DomainException("A etapa \"{$novoStatus->nome}\" encerra o lead: para perdê-lo informe o motivo e, para matriculá-lo, conclua a matrícula.");
        }

        if ($lead->status?->is_ganho) {
            throw new DomainException('Este lead já foi matriculado. Alterações na matrícula são feitas no módulo de Matrículas.');
        }

        if ($lead->status_interessado_id === $novoStatus->id) {
            return false;
        }

        $statusAnterior = $lead->status;
        $reativando = $statusAnterior?->isPerda() ?? false;

        DB::transaction(function () use ($lead, $novoStatus, $statusAnterior, $reativando, $usuarioId): void {
            $atualizacoes = ['status_interessado_id' => $novoStatus->id];

            if ($reativando) {
                $atualizacoes['motivo_perda'] = null;
            }

            $lead->update($atualizacoes);

            if ($reativando) {
                HistoricoContato::create([
                    'interessado_id' => $lead->id,
                    'tipo_contato_interessado_id' => TipoContatoInteressado::porNome(TipoContatoInteressado::FUNIL)->id,
                    'data_contato' => now(),
                    'usuario_id' => $usuarioId,
                    'relato' => "Lead reativado no Funil de Vendas: movido de '{$statusAnterior->nome}' para '{$novoStatus->nome}'.",
                    'resultado' => 'retornar',
                ]);
            }
        });

        LeadScoreService::recalcular($lead);

        return true;
    }

    /**
     * Encerra o lead por perda, com motivo obrigatório e registro na linha do tempo.
     *
     * `$atributosExtras` e `$relatoExtra` são o ponto de extensão para dados de inteligência
     * competitiva (ex.: escola concorrente escolhida) sem duplicar o restante do fluxo.
     *
     * @param  array<string, mixed>  $atributosExtras  colunas adicionais gravadas junto com a perda
     * @return string motivo como gravado em `motivo_perda` (com o concorrente, quando houver)
     */
    public function marcarComoPerdido(
        Interessado $lead,
        StatusInteressado $status,
        string $motivo,
        ?string $concorrente = null,
        ?string $observacoes = null,
        ?int $usuarioId = null,
        array $atributosExtras = [],
        ?string $relatoExtra = null,
    ): string {
        $lead->loadMissing('status');

        if (! $status->isPerda()) {
            throw new DomainException("A etapa \"{$status->nome}\" não é uma etapa de perda.");
        }

        if (! array_key_exists($motivo, Interessado::MOTIVOS_PERDA)) {
            throw new DomainException('Selecione um dos motivos de perda da lista.');
        }

        if ($lead->status?->is_ganho) {
            throw new DomainException('Este lead já foi matriculado e não pode ser marcado como perdido.');
        }

        $motivoFinal = self::motivoPerdaFormatado($motivo, $concorrente);

        $relato = "Lead marcado como perdido no Funil de Vendas ({$status->nome}). Motivo: {$motivoFinal}.";

        if (filled($relatoExtra)) {
            $relato .= ' '.trim((string) $relatoExtra);
        }

        if (filled($observacoes)) {
            $relato .= ' Detalhes: '.trim((string) $observacoes);
        }

        DB::transaction(function () use ($lead, $status, $motivoFinal, $atributosExtras, $usuarioId, $relato): void {
            $lead->update([
                'status_interessado_id' => $status->id,
                'motivo_perda' => $motivoFinal,
            ] + $atributosExtras);

            HistoricoContato::create([
                'interessado_id' => $lead->id,
                'tipo_contato_interessado_id' => TipoContatoInteressado::porNome(TipoContatoInteressado::FUNIL)->id,
                'data_contato' => now(),
                'usuario_id' => $usuarioId,
                'relato' => $relato,
                'resultado' => 'sem_interesse',
            ]);
        });

        LeadScoreService::recalcular($lead);

        return $motivoFinal;
    }

    /**
     * Atalho "Marcar matriculado" para quem não tem acesso ao Assistente de Matrícula: aplica a mesma
     * conversão do assistente (status de ganho, data, indicação, documentos), em vez de só trocar o status.
     */
    public function marcarMatriculado(Interessado $lead): void
    {
        if (! StatusInteressado::ganho()) {
            throw new DomainException('Não há etapa de matrícula (ganho) cadastrada no funil. Cadastre uma em Status de Interessado.');
        }

        InteressadoMatriculaService::registrarConversao($lead);
    }

    /**
     * Registra um atendimento (contato humano) com o lead e reagenda o próximo contato.
     *
     * @param  array{tipo_contato_interessado_id: int|string, relato: string, data_contato?: mixed, duracao_minutos?: int|string|null, resultado?: ?string, data_proximo_contato?: mixed}  $dados
     */
    public function registrarAtendimento(Interessado $lead, array $dados, ?int $usuarioId = null): HistoricoContato
    {
        $historico = DB::transaction(function () use ($lead, $dados, $usuarioId): HistoricoContato {
            $historico = $lead->historicos()->create([
                'tipo_contato_interessado_id' => $dados['tipo_contato_interessado_id'],
                'relato' => $dados['relato'],
                'data_contato' => $dados['data_contato'] ?? now(),
                'usuario_id' => $usuarioId,
                'duracao_minutos' => $dados['duracao_minutos'] ?? null,
                'resultado' => $dados['resultado'] ?? null,
            ]);

            $atualizacoes = [];

            if (array_key_exists('data_proximo_contato', $dados)) {
                $atualizacoes['data_proximo_contato'] = $dados['data_proximo_contato'];
            }

            // Primeiro contato real da escola com a família (não o cadastro do lead).
            if (! $lead->data_primeiro_contato) {
                $atualizacoes['data_primeiro_contato'] = $historico->data_contato;
            }

            if ($atualizacoes !== []) {
                $lead->update($atualizacoes);
            }

            return $historico;
        });

        LeadScoreService::recalcular($lead);

        return $historico;
    }

    /**
     * Texto gravado em `motivo_perda`: o motivo padronizado e, na perda para concorrente, o nome da escola.
     */
    public static function motivoPerdaFormatado(string $motivo, ?string $concorrente = null): string
    {
        if ($motivo === self::MOTIVO_CONCORRENCIA && filled($concorrente)) {
            return $motivo.': '.trim((string) $concorrente);
        }

        return $motivo;
    }

    /**
     * Motivo padronizado sem o complemento ("Concorrência: Colégio X" → "Concorrência"), para agregar
     * relatórios por motivo sem separar a mesma causa em dezenas de textos.
     */
    public static function motivoBase(?string $motivoPerda): ?string
    {
        if (blank($motivoPerda)) {
            return null;
        }

        return trim(explode(':', $motivoPerda, 2)[0]);
    }
}
