<?php

namespace App\Services;

use App\Enums\SituacaoDocumento;
use App\Enums\SituacaoMatricula;
use App\Jobs\ValidarDocumentoComIaJob;
use App\Models\AlunoResponsavel;
use App\Models\Cidade;
use App\Models\Contrato;
use App\Models\DocumentoInserido;
use App\Models\Endereco;
use App\Models\Estado;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\ResponsavelFinanceiro;
use App\Models\TemplateContrato;
use App\Models\TipoDocumento;
use App\Models\TipoVinculo;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\WelcomeUserMail;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MatriculaOnlineService
{
    /**
     * Efetiva a matrícula self-service externa de um novo estudante.
     *
     * @param  array<string, mixed>  $dados
     *
     * @throws \DomainException
     */
    public function processarMatricula(array $dados, array $arquivos = []): Matricula
    {
        return DB::transaction(function () use ($dados, $arquivos) {
            // 1. Validação de status e de vagas: trava a turma na transação, então duas matrículas
            //    simultâneas para a última vaga passam uma de cada vez.
            app(TurmaVagasService::class)->garantirVaga((int) $dados['turma_id']);

            $turma = Turma::with(['serie.curso', 'periodoLetivo'])->findOrFail($dados['turma_id']);

            // 2. Busca ou criação da Pessoa Responsável
            [$responsavel, $responsavelPreexistente] = $this->buscarOuCriarPessoaResponsavel($dados['responsavel']);

            // 3. Busca ou criação da Pessoa Aluno
            [$aluno, $alunoPreexistente] = $this->buscarOuCriarPessoaAluno($dados['aluno']);

            // Este fluxo é público e não autenticado: o CPF digitado não prova identidade. Um aluno que já é conhecido pela
            // escola (matrícula, responsável vinculado ou conta de acesso) só pode ser reaproveitado pela própria família;
            // senão o formulário viraria um atalho para se tornar "responsável" de qualquer aluno cujo CPF se conheça e ver
            // o boletim, os documentos e o financeiro dele. Cadastro que só existe como contato de CRM segue permitido.
            if ($alunoPreexistente && $this->alunoJaConhecido($aluno) && ! $this->ehResponsavelDoAluno($responsavel, $aluno)) {
                throw new \DomainException('Já existe um cadastro com os dados informados do aluno. Para concluir esta matrícula, procure a secretaria da escola.');
            }

            // 4. Criação e vinculação do endereço (só em cadastros novos ou ainda sem endereço)
            if (! empty($dados['responsavel']['cep']) || ! empty($dados['responsavel']['logradouro'])) {
                $this->vincularEndereco($responsavel, $aluno, $dados['responsavel']);
            }

            // 5. Vinculação familiar Aluno x Responsável
            $tipoVinculo = TipoVinculo::find($dados['responsavel']['tipo_vinculo_id'] ?? null)
                ?? TipoVinculo::firstOrCreate(['nome' => 'Responsável Legal']);

            AlunoResponsavel::firstOrCreate([
                'aluno_id' => $aluno->id,
                'responsavel_id' => $responsavel->id,
            ], [
                'tipo_vinculo_id' => $tipoVinculo->id,
            ]);

            // 6. Criação da Matrícula
            $matricula = Matricula::create([
                'pessoa_id' => $aluno->id,
                'turma_id' => $turma->id,
                'situacao' => SituacaoMatricula::PENDENTE,
                'data_ativacao' => now()->toDateString(),
            ]);

            // 7. Criação do Contrato Escolar com Termo de Aceite Eletrônico
            $templateContrato = TemplateContrato::where('is_padrao', true)->first()
                ?? TemplateContrato::first();

            $ipOrigem = request()->ip() ?? '127.0.0.1';
            $userAgent = request()->userAgent() ?? 'Navegador Web';
            $hashAssinatura = hash('sha256', "MATRICULA-{$matricula->id}-{$responsavel->cpf}-".now()->timestamp);

            $logAceite = sprintf(
                'Matrícula 100%% Online — Aceite dos Termos Contratuais e LGPD realizado por %s (CPF: %s) em %s. IP: %s, Dispositivo: %s, Hash: %s',
                $responsavel->nome,
                $responsavel->cpf ?? 'Não informado',
                now()->format('d/m/Y H:i:s'),
                $ipOrigem,
                $userAgent,
                $hashAssinatura
            );

            // Aluno ou responsável que já existiam: quem preencheu o formulário não teve a identidade verificada.
            $cadastroPreexistente = $responsavelPreexistente || $alunoPreexistente;

            if ($cadastroPreexistente) {
                $logAceite .= ' | ATENÇÃO: o aluno ou o responsável já tinha cadastro e a identidade de quem preencheu o formulário não foi verificada — conferir na secretaria.';
            }

            $contrato = Contrato::create([
                'matricula_id' => $matricula->id,
                'template_contrato_id' => $templateContrato?->id,
                'valor_total' => $turma->serie?->curso?->valor_anual ?? 0,
                'data_aceite' => now(),
                'log_assinatura' => $logAceite,
            ]);

            // 8. Responsável Financeiro
            ResponsavelFinanceiro::create([
                'pessoa_id' => $responsavel->id,
                'contrato_id' => $contrato->id,
                'percentual' => 100,
            ]);

            // 9. Armazenamento e vinculação de documentos enviados
            $this->processarArquivosEnviados($matricula, $arquivos);

            // 10. Criação ou vinculação da conta de usuário no Portal
            $this->garantirUsuarioPortal($responsavel, $responsavelPreexistente);

            // 11. Conversão automática no CRM se existir lead
            $this->marcarConversaoCrm($responsavel, $aluno, $matricula);

            // 12. Notificação interna para a equipe escolar
            $this->notificarEquipeEscolar($matricula, $aluno, $responsavel, $turma, $cadastroPreexistente);

            return $matricula;
        });
    }

    /**
     * @return array{0: Pessoa, 1: bool} A pessoa e se ela já existia antes deste envio.
     */
    private function buscarOuCriarPessoaAluno(array $dados): array
    {
        $cpf = ! empty($dados['cpf']) ? preg_replace('/\D/', '', $dados['cpf']) : null;

        if ($cpf) {
            $existente = Pessoa::where('cpf', $cpf)->first();
            if ($existente) {
                return [$existente, true];
            }
        }

        $aluno = Pessoa::create([
            'nome' => $dados['nome'],
            'cpf' => $cpf,
            'data_nascimento' => $dados['data_nascimento'] ?? null,
            'sexo' => $dados['sexo'] ?? null,
            'cor_raca' => $dados['cor_raca'] ?? null,
            'necessidades_especiais' => $dados['necessidades_especiais'] ?? false,
        ]);

        return [$aluno, false];
    }

    /**
     * Regra de ouro deste fluxo público: dado digitado por quem não está autenticado nunca altera um cadastro que já
     * existe. Em especial o e-mail, que é a chave da conta do Portal: gravá-lo num cadastro sem e-mail entregaria a
     * esse cadastro (e aos alunos vinculados) a quem só conhecia o CPF. Campos descritivos (CPF) só preenchem lacunas.
     *
     * @return array{0: Pessoa, 1: bool} A pessoa e se ela já existia antes deste envio.
     */
    private function buscarOuCriarPessoaResponsavel(array $dados): array
    {
        $cpf = ! empty($dados['cpf']) ? preg_replace('/\D/', '', $dados['cpf']) : null;
        $email = $dados['email'] ?? null;

        if ($cpf) {
            $existente = Pessoa::where('cpf', $cpf)->first();
            if ($existente) {
                return [$existente, true];
            }
        }

        if ($email) {
            $existente = Pessoa::where('email', $email)->first();
            if ($existente) {
                if ($cpf && empty($existente->cpf)) {
                    $existente->update(['cpf' => $cpf]);
                }

                return [$existente, true];
            }
        }

        $responsavel = Pessoa::create([
            'nome' => $dados['nome'],
            'cpf' => $cpf,
            'email' => $email,
            'telefone' => $dados['telefone'] ?? null,
            'data_nascimento' => $dados['data_nascimento'] ?? null,
        ]);

        return [$responsavel, false];
    }

    private function ehResponsavelDoAluno(Pessoa $responsavel, Pessoa $aluno): bool
    {
        // Aluno adulto que se matricula por conta própria: responsável e aluno são a mesma pessoa.
        if ($responsavel->is($aluno)) {
            return true;
        }

        return AlunoResponsavel::query()
            ->where('aluno_id', $aluno->id)
            ->where('responsavel_id', $responsavel->id)
            ->exists();
    }

    private function alunoJaConhecido(Pessoa $aluno): bool
    {
        return Matricula::where('pessoa_id', $aluno->id)->exists()
            || AlunoResponsavel::where('aluno_id', $aluno->id)->exists()
            || $aluno->users()->exists();
    }

    private function vincularEndereco(Pessoa $responsavel, Pessoa $aluno, array $dados): void
    {
        $cidadeId = $dados['cidade_id'] ?? null;

        if (! $cidadeId && ! empty($dados['cidade_nome'])) {
            $estadoId = null;
            if (! empty($dados['estado_sigla'])) {
                $estadoId = Estado::where('sigla', strtoupper($dados['estado_sigla']))->value('id');
            }

            $cidade = Cidade::firstOrCreate(
                ['nome' => $dados['cidade_nome'], 'estado_id' => $estadoId],
                ['codigo_ibge' => $dados['ibge'] ?? null]
            );
            $cidadeId = $cidade->id;
        }

        $endereco = Endereco::create([
            'cidade_id' => $cidadeId,
            'cep' => ! empty($dados['cep']) ? preg_replace('/\D/', '', $dados['cep']) : null,
            'logradouro' => $dados['logradouro'] ?? null,
            'numero' => $dados['numero'] ?? null,
            'complemento' => $dados['complemento'] ?? null,
            'bairro' => $dados['bairro'] ?? null,
        ]);

        // Pessoa que já tem endereço não recebe outro vindo de formulário público: a secretaria concilia.
        foreach ([$responsavel, $aluno] as $pessoa) {
            if (! $pessoa->enderecos()->exists()) {
                $pessoa->enderecos()->attach($endereco->id);
            }
        }
    }

    private function processarArquivosEnviados(Matricula $matricula, array $arquivos): void
    {
        $mapaTipos = [
            'documento_aluno' => 'Certidão de Nascimento / RG do Aluno',
            'documento_responsavel' => 'Documento de Identidade do Responsável',
            'comprovante_residencia' => 'Comprovante de Residência',
            'historico_anterior' => 'Histórico Escolar Anterior',
        ];

        foreach ($arquivos as $campo => $arquivo) {
            if (! $arquivo) {
                continue;
            }

            if (is_numeric($campo)) {
                $tipoDoc = TipoDocumento::find((int) $campo);
            } else {
                $nomeTipo = $mapaTipos[$campo] ?? 'Documento Comprobatório';
                $tipoDoc = TipoDocumento::firstOrCreate(['nome' => $nomeTipo]);
            }

            if (! $tipoDoc) {
                continue;
            }

            if ($arquivo instanceof UploadedFile) {
                $path = $arquivo->store('matriculas_online/'.$matricula->id, 'local');
                $nomeOriginal = $arquivo->getClientOriginalName();
                $hash = hash_file('sha256', $arquivo->getRealPath());

                $doc = DocumentoInserido::create([
                    'tipo_documento_id' => $tipoDoc->id,
                    'matricula_id' => $matricula->id,
                    'status' => SituacaoDocumento::EM_ANALISE,
                    'arquivo_path' => $path,
                    'nome_arquivo_original' => $nomeOriginal,
                    'hash_arquivo' => $hash,
                    'observacoes' => 'Enviado pelo responsável no processo de Matrícula 100% Online.',
                ]);

                ValidarDocumentoComIaJob::dispatch($doc->id);
            }
        }
    }

    /**
     * A conta do Portal só nasce do e-mail que consta no cadastro do responsável, nunca de um e-mail digitado agora
     * num cadastro que já existia. A senha é definida pelo próprio titular por um link enviado a esse e-mail.
     */
    private function garantirUsuarioPortal(Pessoa $responsavel, bool $responsavelPreexistente = false): ?User
    {
        if (empty($responsavel->email)) {
            return null;
        }

        $user = User::where('email', $responsavel->email)->first();

        // Já existe uma conta com esse e-mail e o cadastro é novo (veio deste formulário): não amarra a pessoa recém-criada
        // a uma conta que pode ser de outra família ou da equipe.
        if ($user && ! $responsavelPreexistente) {
            return null;
        }

        if (! $user) {
            $user = User::create([
                'name' => $responsavel->nome,
                'email' => $responsavel->email,
                // Senha aleatória e descartada: o acesso se dá pelo link de definição de senha do e-mail de boas-vindas.
                'password' => Hash::make(Str::random(64)),
                'activated_at' => now(),
            ]);

            try {
                $user->notify(new WelcomeUserMail);
            } catch (\Throwable $e) {
                Log::warning('Erro ao enviar e-mail de boas-vindas: '.$e->getMessage());
            }
        }

        if (! $user->hasRole('responsavel')) {
            $user->assignRole('responsavel');
        }

        if (! $user->pessoas()->where('pessoa.id', $responsavel->id)->exists()) {
            $user->pessoas()->attach($responsavel->id);
        }

        return $user;
    }

    /**
     * Converte o lead de origem (se houver) pelo mesmo caminho do Assistente de Matrícula: status de ganho,
     * data de conversão, indicação "Família Indica Família", migração dos documentos e limpeza do rascunho
     * de pré-matrícula. Antes só a data era preenchida, e o lead seguia ativo (recebendo régua e alertas).
     */
    private function marcarConversaoCrm(Pessoa $responsavel, Pessoa $aluno, Matricula $matricula): void
    {
        $interessado = $this->localizarLeadParaConversao($responsavel, $aluno);

        if (! $interessado) {
            return;
        }

        // O vínculo dos documentos com a matrícula usa o nome do aluno: evita consulta preguiçosa.
        $matricula->setRelation('pessoa', $aluno);

        InteressadoMatriculaService::registrarConversao($interessado, [$matricula]);
    }

    /**
     * Lead que originou a matrícula. Procura pelo cadastro do responsável ou do aluno; se não achar, pelo
     * dependente de nome idêntico (sem caixa/acento) cujo contato é o mesmo responsável (e-mail ou CPF).
     * Nunca por nome parcial: "Ana" não pode converter o lead de uma família com "Mariana".
     */
    private function localizarLeadParaConversao(Pessoa $responsavel, Pessoa $aluno): ?Interessado
    {
        $candidatos = Interessado::query()
            ->whereIn('pessoa_id', [$responsavel->id, $aluno->id])
            ->orderByDesc('id')
            ->get();

        if ($candidatos->isEmpty()) {
            $nomeAluno = InteressadoDependente::nomeNormalizado($aluno->nome);

            $candidatos = Interessado::query()
                ->whereHas('pessoa', fn ($pessoa) => $pessoa->where(function ($q) use ($responsavel) {
                    $q->when(filled($responsavel->email), fn ($qq) => $qq->orWhereRaw('LOWER(email) = ?', [mb_strtolower($responsavel->email)]))
                        ->when(filled($responsavel->cpf), fn ($qq) => $qq->orWhere('cpf', $responsavel->cpf));
                }))
                ->whereHas('dependentes')
                ->with('dependentes')
                ->orderByDesc('id')
                ->get()
                ->filter(fn (Interessado $lead): bool => $lead->dependentes
                    ->contains(fn (InteressadoDependente $d): bool => InteressadoDependente::nomeNormalizado($d->nome_crianca) === $nomeAluno));
        }

        // Prefere um lead ainda não convertido; reconverter é idempotente, mas não deve "gastar" a conversão de outro irmão.
        return $candidatos->first(fn (Interessado $lead): bool => $lead->data_conversao === null) ?? $candidatos->first();
    }

    private function notificarEquipeEscolar(Matricula $matricula, Pessoa $aluno, Pessoa $responsavel, Turma $turma, bool $cadastroPreexistente = false): void
    {
        try {
            $destinatarios = User::permission('View:Matricula')->get();

            if ($destinatarios->isEmpty()) {
                $destinatarios = User::role(['admin', 'super_admin'])->get();
            }

            if ($destinatarios->isNotEmpty()) {
                Notification::make()
                    ->title('Nova Matrícula 100% Online Recebida!')
                    ->body(
                        "O aluno **{$aluno->nome}** foi matriculado na turma **{$turma->nome}** pelo responsável **{$responsavel->nome}** via auto-atendimento online."
                        .($cadastroPreexistente ? ' ⚠️ O aluno ou o responsável já tinha cadastro: confirme com a família que foi ela quem preencheu o formulário antes de ativar a matrícula.' : '')
                    )
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->actions([
                        Action::make('ver')
                            ->label('Ver Matrícula')
                            ->url(route('filament.admin.resources.matriculas.edit', $matricula->id))
                            ->button(),
                    ])
                    ->sendToDatabase($destinatarios);
            }
        } catch (\Throwable $e) {
            Log::warning('Erro ao notificar equipe escolar sobre nova matrícula: '.$e->getMessage());
        }
    }
}
