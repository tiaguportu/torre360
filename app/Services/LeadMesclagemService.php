<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentoInserido;
use App\Models\HistoricoContato;
use App\Models\IndicacaoInteressado;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\InteressadoStatusHistorico;
use App\Models\PesquisaSatisfacaoVisita;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Models\VisitaInteressado;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Serviço responsável pela mesclagem atômica de leads duplicados.
 *
 * Garante que:
 *  - O lead mais antigo (ou explicitamente preferido) é preservado como destino;
 *  - Nenhum documento, visita ou pesquisa de satisfação é perdido;
 *  - Dependentes em comum são unificados pelo nome normalizado, redirecionando visitas e documentos;
 *  - Histórico de contatos, histórico de transições do funil e indicações são movidos;
 *  - Tokens e rascunhos de pré-matrícula válidos são preservados;
 *  - Conflitos de campos são tratados completando dados ausentes e concatenando observações;
 *  - Auditoria no Spatie Activitylog e registro na linha do tempo são gravados;
 *  - Executa integralmente dentro de transação de banco de dados.
 */
class LeadMesclagemService
{
    /**
     * Executa a mesclagem entre dois interessados.
     *
     * @param  Interessado  $leadA  Primeiro interessado
     * @param  Interessado  $leadB  Segundo interessado
     * @param  int|null  $usuarioResponsavelId  ID do usuário operador (para auditoria)
     * @param  Interessado|null  $preferirDestino  Forçar qual lead deve ser o destino preservado
     * @return Interessado O interessado destino consolidado
     *
     * @throws DomainException
     */
    public function mesclar(
        Interessado $leadA,
        Interessado $leadB,
        ?int $usuarioResponsavelId = null,
        ?Interessado $preferirDestino = null
    ): Interessado {
        if ($leadA->id === $leadB->id) {
            throw new DomainException('Não é possível mesclar um lead com ele mesmo.');
        }

        return DB::transaction(function () use ($leadA, $leadB, $usuarioResponsavelId, $preferirDestino): Interessado {
            // Recarrega registros com lock de atualização para consistência
            $leadA = Interessado::whereKey($leadA->id)->lockForUpdate()->firstOrFail();
            $leadB = Interessado::whereKey($leadB->id)->lockForUpdate()->firstOrFail();

            // 1. Determina destino (preservado) e origem (absorvido)
            [$leadDestino, $leadOrigem] = $this->definirDestinoEOrigem($leadA, $leadB, $preferirDestino);

            $leadDestinoId = $leadDestino->id;
            $leadOrigemId = $leadOrigem->id;

            // 2. Mesclagem de Dependentes (com unificação de nomes normalizados)
            $this->mesclarDependentes($leadDestino, $leadOrigem);

            // 3. Mesclagem de Visitas e Pesquisas de Satisfação
            $this->mesclarVisitas($leadDestino, $leadOrigem);

            // 4. Mesclagem de Documentos Inseridos
            $this->mesclarDocumentos($leadDestino, $leadOrigem);

            // 5. Mesclagem de Histórico de Contatos e Transições de Funil
            $this->mesclarHistoricos($leadDestino, $leadOrigem);

            // 6. Mesclagem de Indicações
            $this->mesclarIndicacoes($leadDestino, $leadOrigem);

            // 7. Mesclagem de Propostas Comerciais e Régua de Follow-up (se houver)
            $this->mesclarTabelasComplementares($leadDestino, $leadOrigem);

            // 8. Mesclagem de Tokens e Dados de Pré-Matrícula
            $this->mesclarTokensEPreMatricula($leadDestino, $leadOrigem);

            // 9. Resolução de Conflitos e Preenchimento de Campos no Destino
            $this->mesclarCamposDoLead($leadDestino, $leadOrigem);

            // 10. Completar campos nulos na Pessoa de Destino (se pessoas distintas)
            $this->mesclarDadosPessoa($leadDestino, $leadOrigem);

            // 11. Auditoria e Linha do Tempo
            $this->registrarAuditoriaELinhaDoTempo($leadDestino, $leadOrigem, $usuarioResponsavelId);

            // 12. Salvar Destino, Limpar Tokens da Origem e Excluir Origem
            $leadDestino->saveQuietly();

            $leadOrigem->forceFill([
                'token_documentos' => null,
                'token_convite' => null,
            ])->saveQuietly();

            $leadOrigem->delete();

            // 13. Recalcular Lead Score e invalidar caches de contadores
            LeadScoreService::recalcular($leadDestino);
            ContadoresCrm::invalidar();

            return $leadDestino->fresh([
                'pessoa',
                'dependentes',
                'visitas',
                'documentosInseridos',
                'historicos',
                'status',
                'usuario',
            ]);
        });
    }

    /**
     * Define qual lead será mantido (destino) e qual será absorvido (origem).
     *
     * @return array{0: Interessado, 1: Interessado} [leadDestino, leadOrigem]
     */
    private function definirDestinoEOrigem(
        Interessado $leadA,
        Interessado $leadB,
        ?Interessado $preferirDestino
    ): array {
        if ($preferirDestino !== null) {
            if ($preferirDestino->id === $leadA->id) {
                return [$leadA, $leadB];
            }
            if ($preferirDestino->id === $leadB->id) {
                return [$leadB, $leadA];
            }
        }

        // Por padrão: preserva o lead mais antigo criado
        if ($leadA->created_at->lt($leadB->created_at)) {
            return [$leadA, $leadB];
        }

        if ($leadB->created_at->lt($leadA->created_at)) {
            return [$leadB, $leadA];
        }

        // Em caso de empate na data, o menor ID é o mais antigo
        return $leadA->id <= $leadB->id ? [$leadA, $leadB] : [$leadB, $leadA];
    }

    /**
     * Unifica dependentes por nome normalizado. Caso o dependente já exista no destino,
     * redireciona visitas e documentos que apontavam para o da origem e exclui o duplicado.
     */
    private function mesclarDependentes(Interessado $leadDestino, Interessado $leadOrigem): void
    {
        $leadDestino->load('dependentes');
        $leadOrigem->load('dependentes');

        /** @var array<string, InteressadoDependente> $mapaDestino */
        $mapaDestino = [];
        foreach ($leadDestino->dependentes as $dep) {
            $norm = InteressadoDependente::nomeNormalizado($dep->nome_crianca);
            if ($norm !== '') {
                $mapaDestino[$norm] = $dep;
            }
        }

        foreach ($leadOrigem->dependentes as $depOrigem) {
            $normOrigem = InteressadoDependente::nomeNormalizado($depOrigem->nome_crianca);

            if ($normOrigem !== '' && isset($mapaDestino[$normOrigem])) {
                $depDestino = $mapaDestino[$normOrigem];

                // Preenche dados nulos no dependente do destino
                $alterado = false;
                if ($depDestino->data_nascimento === null && $depOrigem->data_nascimento !== null) {
                    $depDestino->data_nascimento = $depOrigem->data_nascimento;
                    $alterado = true;
                }
                if ($depDestino->serie_id === null && $depOrigem->serie_id !== null) {
                    $depDestino->serie_id = $depOrigem->serie_id;
                    $alterado = true;
                }
                if ($depDestino->unidade_id === null && $depOrigem->unidade_id !== null) {
                    $depDestino->unidade_id = $depOrigem->unidade_id;
                    $alterado = true;
                }
                if (blank($depDestino->turno_preferencia) && filled($depOrigem->turno_preferencia)) {
                    $depDestino->turno_preferencia = $depOrigem->turno_preferencia;
                    $alterado = true;
                }
                if (blank($depDestino->vinculo) && filled($depOrigem->vinculo)) {
                    $depDestino->vinculo = $depOrigem->vinculo;
                    $alterado = true;
                }

                if ($alterado) {
                    $depDestino->saveQuietly();
                }

                // Redireciona visitas e documentos vinculados ao dependente de origem
                VisitaInteressado::where('interessado_dependente_id', $depOrigem->id)
                    ->update(['interessado_dependente_id' => $depDestino->id]);

                DocumentoInserido::where('interessado_dependente_id', $depOrigem->id)
                    ->update(['interessado_dependente_id' => $depDestino->id]);

                // Exclui dependente duplicado da origem
                $depOrigem->delete();
            } else {
                // Dependente novo para o destino: transfere propriedade
                $depOrigem->update(['interessado_id' => $leadDestino->id]);
                if ($normOrigem !== '') {
                    $mapaDestino[$normOrigem] = $depOrigem;
                }
            }
        }
    }

    /**
     * Move todas as visitas e pesquisas pós-tour para o interessado destino.
     */
    private function mesclarVisitas(Interessado $leadDestino, Interessado $leadOrigem): void
    {
        VisitaInteressado::where('interessado_id', $leadOrigem->id)
            ->update(['interessado_id' => $leadDestino->id]);

        PesquisaSatisfacaoVisita::where('interessado_id', $leadOrigem->id)
            ->update(['interessado_id' => $leadDestino->id]);
    }

    /**
     * Move todos os documentos inseridos para o interessado destino.
     */
    private function mesclarDocumentos(Interessado $leadDestino, Interessado $leadOrigem): void
    {
        DocumentoInserido::where('interessado_id', $leadOrigem->id)
            ->update(['interessado_id' => $leadDestino->id]);
    }

    /**
     * Move históricos de contatos e histórico de etapas do funil para o destino.
     */
    private function mesclarHistoricos(Interessado $leadDestino, Interessado $leadOrigem): void
    {
        HistoricoContato::where('interessado_id', $leadOrigem->id)
            ->update(['interessado_id' => $leadDestino->id]);

        InteressadoStatusHistorico::where('interessado_id', $leadOrigem->id)
            ->update(['interessado_id' => $leadDestino->id]);
    }

    /**
     * Move registros de indicações para o destino.
     */
    private function mesclarIndicacoes(Interessado $leadDestino, Interessado $leadOrigem): void
    {
        IndicacaoInteressado::where('interessado_id', $leadOrigem->id)
            ->update(['interessado_id' => $leadDestino->id]);
    }

    /**
     * Move tabelas complementares existentes (ex: propostas comerciais e régua).
     */
    private function mesclarTabelasComplementares(Interessado $leadDestino, Interessado $leadOrigem): void
    {
        if (Schema::hasTable('proposta_comercials')) {
            DB::table('proposta_comercials')
                ->where('interessado_id', $leadOrigem->id)
                ->update(['interessado_id' => $leadDestino->id]);
        }

        if (Schema::hasTable('regua_follow_up_envios')) {
            DB::table('regua_follow_up_envios')
                ->where('interessado_id', $leadOrigem->id)
                ->update(['interessado_id' => $leadDestino->id]);
        }
    }

    /**
     * Preserva tokens e rascunhos de pré-matrícula válidos.
     */
    private function mesclarTokensEPreMatricula(Interessado $leadDestino, Interessado $leadOrigem): void
    {
        $tokensTransferidos = false;

        // Token de documentos
        if (blank($leadDestino->token_documentos) && filled($leadOrigem->token_documentos)) {
            $tokenDocs = $leadOrigem->token_documentos;
            $tokenDocsExp = $leadOrigem->token_documentos_expira_em;

            $leadOrigem->forceFill(['token_documentos' => null])->saveQuietly();

            $leadDestino->token_documentos = $tokenDocs;
            $leadDestino->token_documentos_expira_em = $tokenDocsExp;
            $tokensTransferidos = true;
        }

        // Token de convite
        if (! $leadDestino->conviteValido() && $leadOrigem->conviteValido()) {
            $tokenConv = $leadOrigem->token_convite;
            $tokenConvExp = $leadOrigem->token_convite_expira_em;
            $tokenConvUsado = $leadOrigem->token_convite_usado_em;

            $leadOrigem->forceFill(['token_convite' => null])->saveQuietly();

            $leadDestino->token_convite = $tokenConv;
            $leadDestino->token_convite_expira_em = $tokenConvExp;
            $leadDestino->token_convite_usado_em = $tokenConvUsado;
            $tokensTransferidos = true;
        }

        // Dados de pré-matrícula
        if (empty($leadDestino->dados_pre_matricula) && ! empty($leadOrigem->dados_pre_matricula)) {
            $leadDestino->dados_pre_matricula = $leadOrigem->dados_pre_matricula;
            $leadDestino->dados_pre_matricula_em = $leadOrigem->dados_pre_matricula_em;
        } elseif (! empty($leadDestino->dados_pre_matricula) && ! empty($leadOrigem->dados_pre_matricula)) {
            // Mescla arrays mantendo dados preenchidos
            $leadDestino->dados_pre_matricula = array_merge(
                (array) $leadOrigem->dados_pre_matricula,
                array_filter((array) $leadDestino->dados_pre_matricula)
            );
        }
    }

    /**
     * Resolve conflitos de campos e preenche dados ausentes no lead destino.
     */
    private function mesclarCamposDoLead(Interessado $leadDestino, Interessado $leadOrigem): void
    {
        // Campos que devem ser herdados caso nulos no destino
        $camposHeranca = [
            'origem_interessado_id',
            'campanha_marketing_id',
            'usuario_id',
            'valor_estimado',
            'temperatura',
            'concorrente_id',
            'fator_decisivo_concorrente',
            'detalhes_concorrencia',
            'motivo_perda',
            'faixa_distancia_escola',
            'meio_transporte',
            'utm_source',
            'utm_medium',
            'utm_campaign',
        ];

        foreach ($camposHeranca as $campo) {
            if (blank($leadDestino->getAttribute($campo)) && filled($leadOrigem->getAttribute($campo))) {
                $leadDestino->setAttribute($campo, $leadOrigem->getAttribute($campo));
            }
        }

        // Data do primeiro contato: preserva a mais antiga
        if ($leadDestino->data_primeiro_contato === null && $leadOrigem->data_primeiro_contato !== null) {
            $leadDestino->data_primeiro_contato = $leadOrigem->data_primeiro_contato;
        } elseif ($leadDestino->data_primeiro_contato !== null && $leadOrigem->data_primeiro_contato !== null) {
            if ($leadOrigem->data_primeiro_contato->lt($leadDestino->data_primeiro_contato)) {
                $leadDestino->data_primeiro_contato = $leadOrigem->data_primeiro_contato;
            }
        }

        // Data de próximo contato: se nula no destino, aproveita da origem
        if ($leadDestino->data_proximo_contato === null && $leadOrigem->data_proximo_contato !== null) {
            $leadDestino->data_proximo_contato = $leadOrigem->data_proximo_contato;
        }

        // Redes sociais: merge de chaves
        $redesDestino = (array) ($leadDestino->redes_sociais ?? []);
        $redesOrigem = (array) ($leadOrigem->redes_sociais ?? []);
        $redesMescladas = array_merge($redesOrigem, array_filter($redesDestino));
        if (! empty($redesMescladas)) {
            $leadDestino->redes_sociais = $redesMescladas;
        }

        // Observações: concatenação cronológica explicativa
        $obsOrigem = trim((string) $leadOrigem->observacoes);
        if ($obsOrigem !== '') {
            $obsDestino = trim((string) $leadDestino->observacoes);
            $separador = $obsDestino !== '' ? "\n\n" : '';
            $dataHoje = now()->format('d/m/Y H:i');
            $leadDestino->observacoes = $obsDestino.$separador."[Mesclado do Lead #{$leadOrigem->id} em {$dataHoje}]:\n".$obsOrigem;
        }
    }

    /**
     * Preenche dados cadastrais da Pessoa destino se ela tiver campos vazios e a origem tiver dados.
     */
    private function mesclarDadosPessoa(Interessado $leadDestino, Interessado $leadOrigem): void
    {
        if ($leadDestino->pessoa_id === $leadOrigem->pessoa_id) {
            return;
        }

        $pessoaDestino = $leadDestino->pessoa;
        $pessoaOrigem = $leadOrigem->pessoa;

        if (! $pessoaDestino || ! $pessoaOrigem) {
            return;
        }

        $camposPessoa = [
            'cpf',
            'telefone',
            'email',
            'data_nascimento',
            'profissao',
            'estado_civil',
            'identidade',
            'sexo',
            'cor_raca',
            'naturalidade_id',
            'nacionalidade_id',
            'endereco_id',
        ];

        $alterado = false;
        foreach ($camposPessoa as $campo) {
            if (blank($pessoaDestino->getAttribute($campo)) && filled($pessoaOrigem->getAttribute($campo))) {
                $pessoaDestino->setAttribute($campo, $pessoaOrigem->getAttribute($campo));
                $alterado = true;
            }
        }

        if ($alterado) {
            $pessoaDestino->saveQuietly();
        }
    }

    /**
     * Registra auditoria no activity_log e insere evento de mesclagem na linha do tempo.
     */
    private function registrarAuditoriaELinhaDoTempo(
        Interessado $leadDestino,
        Interessado $leadOrigem,
        ?int $usuarioResponsavelId
    ): void {
        $usuario = $usuarioResponsavelId ? User::find($usuarioResponsavelId) : auth()->user();

        // 1. Spatie Activitylog
        activity('crm')
            ->performedOn($leadDestino)
            ->causedBy($usuario)
            ->withProperties([
                'lead_origem_id' => $leadOrigem->id,
                'lead_destino_id' => $leadDestino->id,
                'lead_origem_status' => $leadOrigem->status?->nome,
                'lead_destino_status' => $leadDestino->status?->nome,
                'lead_origem_criado_em' => $leadOrigem->created_at?->toIso8601String(),
            ])
            ->log("Lead #{$leadOrigem->id} foi mesclado com sucesso neste Lead (#{$leadDestino->id}).");

        // 2. Registro na linha do tempo (HistoricoContato)
        $tipoContato = TipoContatoInteressado::porNome('Sistema')
            ?? TipoContatoInteressado::first();

        $nomeOperador = $usuario?->name ?? 'Sistema';

        HistoricoContato::create([
            'interessado_id' => $leadDestino->id,
            'usuario_id' => $usuario?->id,
            'tipo_contato_interessado_id' => $tipoContato?->id,
            'relato' => "Mesclagem de leads concluída por {$nomeOperador}. O Lead #{$leadOrigem->id} foi absorvido e unificado neste registro. Todos os contatos, visitas, documentos e dependentes foram consolidados.",
            'data_contato' => now(),
            'duracao_minutos' => 0,
            'resultado' => 'outro',
            'automatico' => true,
        ]);
    }
}
