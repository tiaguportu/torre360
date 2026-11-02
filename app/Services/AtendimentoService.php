<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PrioridadeChamado;
use App\Enums\ResultadoChamado;
use App\Enums\StatusChamado;
use App\Models\AtendimentoChamado;
use App\Models\AtendimentoMensagem;
use App\Models\AtendimentoSetor;
use App\Models\Pessoa;
use App\Models\User;
use App\Notifications\ChamadoParaEquipeNotification;
use App\Notifications\ChamadoRespondidoNotification;
use App\Support\HorarioComercial;
use App\Support\Requerimentos\CatalogoRequerimentos;
use App\Support\Requerimentos\TipoRequerimento;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/**
 * Ciclo do chamado da Central de Atendimento e dos requerimentos da família (Ondas 83, 80 e 34).
 *
 * Reúne o que antes estava espalhado nas telas: prazo em horas úteis, aviso à equipe e à família, assumir e reatribuir,
 * encerramento por inatividade e a conclusão de um requerimento (aplicando a mudança só depois da conferência humana).
 */
class AtendimentoService
{
    /**
     * Abre um chamado comum com a primeira mensagem da família e calcula os prazos do setor.
     *
     * @param  array{setor_id: int, solicitante_id: int, matricula_id?: ?int, categoria_id?: ?int, assunto: string, prioridade?: string, mensagem: string, anexo_path?: ?string}  $dados
     */
    public function abrir(array $dados): AtendimentoChamado
    {
        $setor = AtendimentoSetor::findOrFail($dados['setor_id']);

        return $this->criar($setor, null, [
            'solicitante_id' => $dados['solicitante_id'],
            'matricula_id' => $dados['matricula_id'] ?? null,
            'categoria_id' => $dados['categoria_id'] ?? null,
            'assunto' => $dados['assunto'],
            'prioridade' => $dados['prioridade'] ?? PrioridadeChamado::Normal->value,
        ], $dados['mensagem'], $dados['anexo_path'] ?? null);
    }

    /**
     * Abre um requerimento do catálogo. Os campos de arquivo chegam como caminho já guardado em `$campos`.
     *
     * @param  array<string, mixed>  $campos
     *
     * @throws ValidationException
     */
    public function abrirRequerimento(string $chave, int $solicitanteId, ?int $matriculaId, array $campos): AtendimentoChamado
    {
        $tipo = CatalogoRequerimentos::tipo($chave);

        if (! $tipo) {
            throw ValidationException::withMessages(['tipo' => 'Tipo de requerimento desconhecido.']);
        }

        $dados = $this->validarCampos($tipo, $campos, $matriculaId);
        $setor = $this->setorParaRequerimentos();

        return $this->criar($setor, $tipo, [
            'solicitante_id' => $solicitanteId,
            'matricula_id' => $matriculaId,
            'assunto' => 'Requerimento: '.$tipo->nome,
            'prioridade' => PrioridadeChamado::Normal->value,
            'tipo_requerimento' => $tipo->chave,
            'dados' => $dados,
            'restrito' => $tipo->restrito,
        ], 'Pedido aberto pelo Portal: '.$tipo->nome.'.', null);
    }

    /**
     * Resposta da equipe: grava, marca a primeira resposta, muda a situação e avisa a família.
     */
    public function responderEquipe(AtendimentoChamado $chamado, User $por, string $mensagem, ?string $anexo = null, ?StatusChamado $novoStatus = null): AtendimentoMensagem
    {
        $resposta = DB::transaction(function () use ($chamado, $por, $mensagem, $anexo, $novoStatus): AtendimentoMensagem {
            $resposta = AtendimentoMensagem::create([
                'chamado_id' => $chamado->id,
                'user_id' => $por->id,
                'mensagem' => $mensagem,
                'anexo_path' => $anexo,
            ]);

            $status = $novoStatus ?? ($chamado->status === StatusChamado::Aberto ? StatusChamado::EmAndamento : $chamado->status);

            $chamado->forceFill([
                'status' => $status,
                'responsavel_atendimento_id' => $chamado->responsavel_atendimento_id ?: $por->id,
                'primeira_resposta_em' => $chamado->primeira_resposta_em ?? now(),
                'aguardando_solicitante_desde' => $status === StatusChamado::AguardandoSolicitante ? ($chamado->aguardando_solicitante_desde ?? now()) : null,
                'fechado_em' => $status === StatusChamado::Fechado ? ($chamado->fechado_em ?? now()) : $chamado->fechado_em,
            ])->save();

            return $resposta;
        });

        $this->avisarFamilia($chamado, ChamadoRespondidoNotification::RESPOSTA);

        return $resposta;
    }

    /**
     * Mensagem da família: reabre o chamado que esperava por ela e avisa quem cuida (ou o setor, se ninguém assumiu).
     */
    public function responderFamilia(AtendimentoChamado $chamado, int $pessoaId, string $mensagem, ?string $anexo = null): AtendimentoMensagem
    {
        $resposta = DB::transaction(function () use ($chamado, $pessoaId, $mensagem, $anexo): AtendimentoMensagem {
            $resposta = AtendimentoMensagem::create([
                'chamado_id' => $chamado->id,
                'pessoa_id' => $pessoaId,
                'mensagem' => $mensagem,
                'anexo_path' => $anexo,
            ]);

            if (in_array($chamado->status, [StatusChamado::AguardandoSolicitante, StatusChamado::Resolvido], true)) {
                $chamado->forceFill(['status' => StatusChamado::EmAndamento, 'aguardando_solicitante_desde' => null])->save();
            }

            return $resposta;
        });

        $this->avisarEquipe($chamado, ChamadoParaEquipeNotification::RESPOSTA_FAMILIA);

        return $resposta;
    }

    /**
     * Assume o chamado se ninguém o fez antes (a atualização é atômica: dois cliques simultâneos não sobrescrevem).
     */
    public function assumir(AtendimentoChamado $chamado, User $por): bool
    {
        $atualizados = AtendimentoChamado::query()
            ->whereKey($chamado->id)
            ->whereNull('responsavel_atendimento_id')
            ->update(['responsavel_atendimento_id' => $por->id]);

        $chamado->refresh();

        return $atualizados === 1;
    }

    public function reatribuir(AtendimentoChamado $chamado, User $para, User $por): void
    {
        $chamado->forceFill(['responsavel_atendimento_id' => $para->id])->save();

        if ($para->id !== $por->id) {
            $para->notify(new ChamadoParaEquipeNotification($chamado->id, ChamadoParaEquipeNotification::ATRIBUIDO));
        }
    }

    /**
     * Conclui um requerimento. `Atendido` com `$aplicar` chama o tratador do tipo (que muda o dado depois da conferência);
     * `Indeferido` exige justificativa. Em ambos a família é avisada e o chamado fica resolvido.
     *
     * @param  array<string, mixed>  $opcoes
     *
     * @throws \DomainException regra do tratador ou justificativa ausente
     * @throws AuthorizationException quando aplicar sem a permissão
     */
    public function concluirRequerimento(AtendimentoChamado $chamado, User $por, ResultadoChamado $resultado, ?string $justificativa = null, bool $aplicar = false, array $opcoes = []): AtendimentoChamado
    {
        if (! $chamado->ehRequerimento()) {
            throw new \DomainException('Este chamado não é um requerimento.');
        }

        if ($chamado->resultado !== null) {
            throw new \DomainException('Este requerimento já foi concluído.');
        }

        if ($resultado === ResultadoChamado::Indeferido && blank($justificativa)) {
            throw new \DomainException('Explique o motivo do indeferimento: a família recebe essa justificativa.');
        }

        if ($aplicar && ! $por->can('Aplicar:AtendimentoChamado')) {
            throw new AuthorizationException('Você não pode aplicar alterações de requerimento.');
        }

        $textoFinal = filled($justificativa) ? trim((string) $justificativa) : null;

        DB::transaction(function () use ($chamado, $por, $resultado, $aplicar, $opcoes, &$textoFinal): void {
            $tratador = $chamado->tipo()?->tratador();

            if ($resultado === ResultadoChamado::Atendido && $aplicar && $tratador) {
                $resumo = $tratador->aplicar($chamado, $por, $opcoes);
                $textoFinal = trim($resumo.' '.($textoFinal ?? ''));
                $chamado->forceFill(['aplicado_em' => now(), 'aplicado_por_user_id' => $por->id]);
            }

            $textoFinal ??= $resultado === ResultadoChamado::Atendido ? 'Pedido atendido.' : 'Pedido indeferido.';

            AtendimentoMensagem::create([
                'chamado_id' => $chamado->id,
                'user_id' => $por->id,
                'mensagem' => ($resultado === ResultadoChamado::Atendido ? 'Pedido atendido. ' : 'Pedido indeferido. ').$textoFinal,
            ]);

            $chamado->forceFill([
                'resultado' => $resultado,
                'resultado_justificativa' => $textoFinal,
                'status' => StatusChamado::Resolvido,
                'responsavel_atendimento_id' => $chamado->responsavel_atendimento_id ?: $por->id,
                'primeira_resposta_em' => $chamado->primeira_resposta_em ?? now(),
                'aguardando_solicitante_desde' => null,
            ])->save();
        });

        $this->avisarFamilia($chamado, ChamadoRespondidoNotification::ENCERRADO);

        return $chamado->refresh();
    }

    /**
     * Chamado esperando a família há mais dias que o setor define fica resolvido sozinho, com aviso. Se a família responder,
     * ele reabre (ver `responderFamilia`).
     *
     * @return int quantidade de chamados encerrados
     */
    public function resolverAguardandoVencidos(): int
    {
        $total = 0;

        AtendimentoChamado::query()
            ->with('setor')
            ->where('status', StatusChamado::AguardandoSolicitante->value)
            ->whereNotNull('aguardando_solicitante_desde')
            ->chunkById(100, function (Collection $chamados) use (&$total): void {
                foreach ($chamados as $chamado) {
                    $dias = (int) ($chamado->setor?->fecha_aguardando_dias ?? config('atendimento.fecha_aguardando_dias', 7));

                    if ($dias <= 0 || $chamado->aguardando_solicitante_desde->gt(now()->subDays($dias))) {
                        continue;
                    }

                    AtendimentoMensagem::create([
                        'chamado_id' => $chamado->id,
                        'mensagem' => "Chamado encerrado automaticamente: sem resposta há {$dias} dias. Se ainda precisar, responda aqui e ele será reaberto.",
                    ]);
                    $chamado->forceFill(['status' => StatusChamado::Resolvido, 'aguardando_solicitante_desde' => null])->save();
                    $this->avisarFamilia($chamado, ChamadoRespondidoNotification::FECHADO_POR_INATIVIDADE);
                    $total++;
                }
            });

        return $total;
    }

    /**
     * Quem recebe o aviso do chamado novo: o setor (e-mail cadastrado) e quem pode atender. Chamado restrito vai só a quem
     * tem a permissão de ver restritos e nunca ao e-mail geral do setor.
     */
    public function avisarEquipe(AtendimentoChamado $chamado, string $evento): void
    {
        $notificacao = new ChamadoParaEquipeNotification($chamado->id, $evento);

        $responsavel = $chamado->responsavelAtendimento;

        if ($responsavel && $evento !== ChamadoParaEquipeNotification::NOVO) {
            $responsavel->notify($notificacao);

            return;
        }

        $this->usuariosComPermissao($chamado->restrito ? 'VerRestritos:AtendimentoChamado' : 'Update:AtendimentoChamado')
            ->each(fn (User $usuario) => $usuario->notify($notificacao));

        $email = $chamado->setor?->email_notificacao;

        if (! $chamado->restrito && filled($email) && $evento === ChamadoParaEquipeNotification::NOVO) {
            Notification::route('mail', $email)->notify($notificacao);
        }
    }

    public function avisarFamilia(AtendimentoChamado $chamado, string $evento): void
    {
        $this->destinatariosDaFamilia($chamado)
            ->each(fn (User $usuario) => $usuario->notify(new ChamadoRespondidoNotification($chamado->id, $evento)));
    }

    /**
     * Contas ativas dos responsáveis do solicitante; sem conta, as dos responsáveis do aluno do chamado.
     *
     * @return Collection<int, User>
     */
    public function destinatariosDaFamilia(AtendimentoChamado $chamado): Collection
    {
        $chamado->loadMissing(['solicitante.users', 'matricula.pessoa.responsaveis.users']);

        $usuarios = $chamado->solicitante?->users ?? collect();

        if ($usuarios->isEmpty()) {
            $usuarios = $chamado->matricula?->pessoa?->responsaveis->flatMap(fn (Pessoa $responsavel) => $responsavel->users) ?? collect();
        }

        return $usuarios->filter(fn (User $usuario): bool => $usuario->is_active)->unique('id')->values();
    }

    /**
     * Prazo de primeira resposta e de resolução do chamado, em horas úteis (ou em dias, no requerimento).
     *
     * @return array{prazo_primeira_resposta_em: ?CarbonInterface, prazo_resolucao_em: ?CarbonInterface}
     */
    public function prazos(AtendimentoSetor $setor, ?TipoRequerimento $tipo = null, ?CarbonInterface $agora = null): array
    {
        $agora ??= now();
        $primeira = $setor->prazo_primeira_resposta_horas ?? (int) config('atendimento.prazo_primeira_resposta_horas', 24);
        $resolucao = $setor->prazo_resolucao_horas ?? (int) config('atendimento.prazo_resolucao_horas', 72);

        $prazoResolucao = match (true) {
            $tipo !== null && $tipo->diasUteis => HorarioComercial::somarDiasUteis($agora, $tipo->prazoDias)->endOfDay(),
            $tipo !== null => $agora->copy()->addDays($tipo->prazoDias)->endOfDay(),
            $resolucao > 0 => HorarioComercial::somarHoras($agora, $resolucao),
            default => null,
        };

        return [
            'prazo_primeira_resposta_em' => $primeira > 0 ? HorarioComercial::somarHoras($agora, $primeira) : null,
            'prazo_resolucao_em' => $prazoResolucao,
        ];
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function criar(AtendimentoSetor $setor, ?TipoRequerimento $tipo, array $atributos, string $mensagemInicial, ?string $anexo): AtendimentoChamado
    {
        $chamado = DB::transaction(function () use ($setor, $tipo, $atributos, $mensagemInicial, $anexo): AtendimentoChamado {
            $chamado = AtendimentoChamado::create([
                ...$atributos,
                'setor_id' => $setor->id,
                'status' => StatusChamado::Aberto,
                ...$this->prazos($setor, $tipo),
            ]);

            AtendimentoMensagem::create([
                'chamado_id' => $chamado->id,
                'pessoa_id' => $atributos['solicitante_id'],
                'mensagem' => $mensagemInicial,
                'anexo_path' => $anexo,
            ]);

            return $chamado;
        });

        $this->avisarEquipe($chamado->load('setor'), ChamadoParaEquipeNotification::NOVO);

        return $chamado;
    }

    /**
     * @param  array<string, mixed>  $campos
     * @return array<string, mixed> só os campos do tipo, já validados
     *
     * @throws ValidationException
     */
    private function validarCampos(TipoRequerimento $tipo, array $campos, ?int $matriculaId): array
    {
        $regras = [];
        $nomes = [];

        foreach ($tipo->campos as $campo) {
            $regra = [($campo['obrigatorio'] ?? false) ? 'required' : 'nullable'];
            $regra = [...$regra, ...match ($campo['tipo']) {
                'date' => ['date'],
                'textarea' => ['string', 'max:5000'],
                'select' => [Rule::in(array_keys($campo['opcoes'] ?? []))],
                default => ['string', 'max:255'],
            }];

            if ($campo['nome'] === 'email') {
                $regra[] = 'email';
            }

            $regras[$campo['nome']] = $regra;
            $nomes[$campo['nome']] = mb_strtolower($campo['rotulo']);
        }

        $validador = Validator::make($campos, $regras, [], $nomes);

        $validador->after(function ($v) use ($tipo, $campos, $matriculaId): void {
            if ($tipo->exigeMatricula && ! $matriculaId) {
                $v->errors()->add('matricula_id', 'Escolha o aluno a que o pedido se refere.');
            }

            if ($tipo->chave === 'atualizacao_cadastral' && blank($campos['telefone'] ?? null) && blank($campos['email'] ?? null) && blank($campos['endereco'] ?? null)) {
                $v->errors()->add('telefone', 'Informe ao menos um dado novo: telefone, e-mail ou endereço.');
            }

            if ($tipo->chave === 'atestado_aluno' && filled($campos['inicio'] ?? null) && filled($campos['fim'] ?? null) && $campos['fim'] < $campos['inicio']) {
                $v->errors()->add('fim', 'O último dia não pode ser anterior ao primeiro.');
            }
        });

        $validador->validate();

        return array_intersect_key($campos, array_flip(array_column($tipo->campos, 'nome')));
    }

    private function setorParaRequerimentos(): AtendimentoSetor
    {
        return AtendimentoSetor::query()->where('ativo', true)->where('recebe_requerimentos', true)->orderBy('ordem')->first()
            ?? AtendimentoSetor::query()->where('ativo', true)->orderBy('ordem')->first()
            ?? AtendimentoSetor::create(['nome' => 'Secretaria', 'descricao' => 'Requerimentos e documentos', 'ativo' => true, 'recebe_requerimentos' => true, 'ordem' => 1]);
    }

    /**
     * @return Collection<int, User>
     */
    private function usuariosComPermissao(string $permissao): Collection
    {
        if (! Permission::query()->where('name', $permissao)->where('guard_name', 'web')->exists()) {
            return collect();
        }

        return User::permission($permissao)->ativos()->get();
    }
}
