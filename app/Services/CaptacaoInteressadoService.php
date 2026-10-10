<?php

namespace App\Services;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\Unidade;
use App\Rules\Cpf;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cadastro de interessados pelo formulário público (`/quero-matricular`).
 *
 * Antes o controller fazia `updateOrCreate` por pessoa: quem reenviava o formulário tinha o lead voltado
 * para "Novo" (mesmo se Matriculado/Perdido), as observações do consultor sobrescritas e os dependentes
 * apagados e recriados (perdendo o vínculo com visitas e documentos). Agora:
 *
 *  - a pessoa é reconhecida por e-mail, CPF ou telefone (de quem já é lead) e só tem campos vazios completados;
 *  - lead novo: criado como antes (status inicial, próximo contato em 1 dia, origem, UTM, indicação);
 *  - lead existente: nada é sobrescrito. O reenvio vira um registro na linha do tempo (conta como interação da
 *    família), o próximo contato é antecipado (nunca adiado) e lead perdido é reaberto na etapa inicial;
 *  - dependentes são reconhecidos pelo nome e só ganham o que faltava; nenhum é apagado;
 *  - unidade e turno de preferência ficam em colunas do dependente, não só em texto livre;
 *  - tudo roda numa transação, serializada por e-mail para que um duplo clique não crie dois leads.
 */
class CaptacaoInteressadoService
{
    public function __construct(private readonly IndicacaoCaptacaoService $indicacoes) {}

    /**
     * @param  array<string, mixed>  $dados  dados validados de `StoreCaptacaoInteressadoRequest`
     * @return array{interessado: Interessado, pessoa: Pessoa, indicador: ?Pessoa, novo: bool, reaberto: bool, ja_matriculado: bool, primeira_unidade_id: ?int}
     */
    public function registrar(array $dados, Request $request): array
    {
        $email = mb_strtolower(trim((string) $dados['responsavel_email']));

        $lock = Cache::lock('captacao:'.sha1($email), 15);
        $adquirido = false;

        try {
            $lock->block(3);
            $adquirido = true;
        } catch (LockTimeoutException) {
            // Segue sem a trava: o pior caso é o mesmo de antes (duas requisições simultâneas do mesmo e-mail).
        }

        try {
            return DB::transaction(fn (): array => $this->registrarEmTransacao($dados, $request, $email));
        } finally {
            if ($adquirido) {
                $lock->release();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{interessado: Interessado, pessoa: Pessoa, indicador: ?Pessoa, novo: bool, reaberto: bool, ja_matriculado: bool, primeira_unidade_id: ?int}
     */
    private function registrarEmTransacao(array $dados, Request $request, string $email): array
    {
        $alunos = $dados['alunos'] ?? [];

        $nomeContato = $dados['tipo_preenchimento'] === 'responsavel'
            ? $dados['responsavel_nome']
            : $alunos[0]['nome'];

        $cpf = Cpf::valido($dados['responsavel_cpf'] ?? null)
            ? preg_replace('/\D/', '', (string) $dados['responsavel_cpf'])
            : null;

        $pessoa = $this->localizarOuCriarPessoa((string) $nomeContato, $email, trim((string) $dados['responsavel_telefone']), $cpf);

        // Prova do aceite (data, versão do texto, origem e IP). Não religa `aceita_comunicacao` de quem já pediu
        // descadastro: o formulário é público e qualquer pessoa pode digitar o e-mail de outra.
        $pessoa->registrarConsentimento('formulario_captacao', $request->ip());

        // Link "Família Indica Família": o indicador é resolvido antes de gravar o lead (auto-indicação é ignorada).
        $codigoIndicacao = $this->indicacoes->codigoDaRequisicao($request);
        $indicador = $this->indicacoes->localizarIndicador($codigoIndicacao, $pessoa);

        $interessado = Interessado::query()->where('pessoa_id', $pessoa->id)->lockForUpdate()->first();
        $novo = $interessado === null;
        $reaberto = false;

        if ($novo) {
            $interessado = $this->criarLead($pessoa, $dados, $indicador);
        } else {
            $reaberto = $this->tratarReenvio($interessado, $dados);
        }

        $this->sincronizarDependentes($interessado, $alunos);

        // Primeira atribuição vale (first touch): um reenvio não reescreve a origem do lead.
        $this->registrarAtribuicao($interessado, UtmTracker::atribuicao($request));

        if ($indicador && $codigoIndicacao) {
            $this->indicacoes->registrar($interessado, $indicador, $codigoIndicacao);
        }

        $this->indicacoes->esquecer($request);

        LeadScoreService::recalcular($interessado);

        $interessado->loadMissing('status');

        return [
            'interessado' => $interessado,
            'pessoa' => $pessoa,
            'indicador' => $indicador,
            'novo' => $novo,
            'reaberto' => $reaberto,
            'ja_matriculado' => (bool) $interessado->status?->is_ganho,
            'primeira_unidade_id' => $alunos[0]['unidade_id'] ?? null,
        ];
    }

    /**
     * Reconhece a pessoa por e-mail, CPF ou telefone (este só entre quem já é lead, para não atrelar o lead a
     * um aluno/funcionário que compartilha o número). Pessoa existente só tem campos vazios completados.
     */
    private function localizarOuCriarPessoa(string $nome, string $email, string $telefone, ?string $cpf): Pessoa
    {
        $pessoa = Pessoa::query()->whereRaw('LOWER(email) = ?', [$email])->first()
            ?? ($cpf ? Pessoa::query()->where('cpf', $cpf)->first() : null)
            ?? $this->localizarLeadPorTelefone($telefone);

        if (! $pessoa) {
            return Pessoa::create([
                'nome' => $nome,
                'cpf' => $cpf,
                'email' => $email,
                'telefone' => $telefone,
            ]);
        }

        $complementos = [];

        if (blank($pessoa->telefone)) {
            $complementos['telefone'] = $telefone;
        }

        if (blank($pessoa->email)) {
            $complementos['email'] = $email;
        }

        if (blank($pessoa->cpf) && $cpf && ! Pessoa::query()->where('cpf', $cpf)->exists()) {
            $complementos['cpf'] = $cpf;
        }

        if ($complementos !== []) {
            $pessoa->update($complementos);
        }

        return $pessoa;
    }

    private function localizarLeadPorTelefone(string $telefone): ?Pessoa
    {
        $digitos = preg_replace('/\D/', '', $telefone) ?? '';

        if (strlen($digitos) < 10) {
            return null;
        }

        $final = substr($digitos, -11);

        return Pessoa::query()
            ->whereHas('interessado')
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', ''), '+', '') LIKE ?", ['%'.$final])
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function criarLead(Pessoa $pessoa, array $dados, ?Pessoa $indicador): Interessado
    {
        $status = StatusInteressado::inicial()
            ?? StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);

        // Quem veio por indicação e não informou outra origem entra como "Indicação" (e não como "Site").
        $origemPadraoId = $indicador
            ? OrigemInteressado::firstOrCreate(['nome' => 'Indicação'])->id
            : OrigemInteressado::firstOrCreate(['nome' => 'Site'])->id;

        return Interessado::create([
            'pessoa_id' => $pessoa->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $dados['como_conheceu'] ?? $origemPadraoId,
            // `data_primeiro_contato` é o primeiro contato da ESCOLA com a família: só o primeiro atendimento
            // registrado a preenche (antes era gravada aqui, no cadastro, o que zerava qualquer tempo de resposta).
            'data_proximo_contato' => now()->addDay(),
            'observacoes' => $this->montarObservacoes($dados, $indicador),
        ]);
    }

    /**
     * Lead que já existia voltou a preencher o formulário. Retorna true se era um lead perdido que foi reaberto.
     *
     * @param  array<string, mixed>  $dados
     */
    private function tratarReenvio(Interessado $interessado, array $dados): bool
    {
        $interessado->loadMissing('status');

        $status = $interessado->status;
        $jaMatriculado = (bool) $status?->is_ganho;
        $reaberto = false;
        $atualizacoes = [];

        if ($status?->isPerda() && ($inicial = StatusInteressado::inicial())) {
            $atualizacoes['status_interessado_id'] = $inicial->id;
            $atualizacoes['motivo_perda'] = null;
            $reaberto = true;
        }

        // Antecipa o retorno (a família acabou de procurar a escola), mas nunca adia um já marcado.
        if (! $jaMatriculado) {
            $proximo = now()->addDay();
            $atual = $interessado->data_proximo_contato;

            if ($atual === null || $atual->isPast() || $atual->gt($proximo)) {
                $atualizacoes['data_proximo_contato'] = $proximo;
            }
        }

        if ($atualizacoes !== []) {
            $interessado->update($atualizacoes);
        }

        HistoricoContato::create([
            'interessado_id' => $interessado->id,
            'tipo_contato_interessado_id' => TipoContatoInteressado::porNome(TipoContatoInteressado::FORMULARIO_SITE)->id,
            'data_contato' => now(),
            'usuario_id' => null,
            'relato' => $this->relatoDoReenvio($dados, $reaberto, $jaMatriculado),
            'resultado' => 'retornar',
        ]);

        return $reaberto;
    }

    /**
     * Dependentes: reconhece o aluno pelo nome (sem caixa/acento) e data de nascimento (quando as duas existem),
     * completa só o que estava vazio e cria os que são novos. Nunca apaga.
     *
     * @param  array<int, array<string, mixed>>  $alunos
     */
    private function sincronizarDependentes(Interessado $interessado, array $alunos): void
    {
        $existentes = $interessado->dependentes()->get();

        foreach ($alunos as $aluno) {
            $nome = trim((string) ($aluno['nome'] ?? ''));

            if ($nome === '') {
                continue;
            }

            $nascimento = filled($aluno['data_nascimento'] ?? null) ? Carbon::parse($aluno['data_nascimento'])->toDateString() : null;
            $nomeNormalizado = InteressadoDependente::nomeNormalizado($nome);

            /** @var InteressadoDependente|null $dependente */
            $dependente = $existentes->first(fn (InteressadoDependente $d): bool => InteressadoDependente::nomeNormalizado($d->nome_crianca) === $nomeNormalizado
                && ($nascimento === null || $d->data_nascimento === null || $d->data_nascimento->toDateString() === $nascimento));

            $informados = array_filter([
                'serie_id' => $aluno['serie_id'] ?? null,
                'unidade_id' => $aluno['unidade_id'] ?? null,
                'turno_preferencia' => $aluno['turno_preferencia'] ?? null,
                'data_nascimento' => $nascimento,
            ], fn ($valor) => filled($valor));

            if ($dependente) {
                $complementos = array_filter(
                    $informados,
                    fn ($valor, string $campo): bool => blank($dependente->{$campo}),
                    ARRAY_FILTER_USE_BOTH
                );

                if ($complementos !== []) {
                    $dependente->update($complementos);
                }

                continue;
            }

            $existentes->push($interessado->dependentes()->create($informados + [
                'nome_crianca' => $nome,
                'vinculo' => $aluno['vinculo'] ?? 'Parente',
            ]));
        }
    }

    /**
     * Grava a atribuição de campanha/UTM apenas se o lead ainda não tiver uma
     * (first touch: um novo envio do mesmo contato não reescreve a origem).
     *
     * @param  array<string, mixed>  $atribuicao
     */
    private function registrarAtribuicao(Interessado $interessado, array $atribuicao): void
    {
        if ($atribuicao === [] || filled($interessado->utm_source) || filled($interessado->utm_campaign) || filled($interessado->campanha_marketing_id)) {
            return;
        }

        $interessado->update($atribuicao);
    }

    /**
     * Observações do lead novo, consolidando os dados do formulário sem N+1.
     *
     * @param  array<string, mixed>  $dados
     */
    private function montarObservacoes(array $dados, ?Pessoa $indicador = null): string
    {
        $obs = $this->linhasDosAlunos($dados['alunos'] ?? []);

        if (! empty($dados['observacoes'])) {
            $obs[] = 'Observações: '.$dados['observacoes'];
        }

        if ($indicador) {
            $obs[] = "Indicação: família de {$indicador->nome} (Família Indica Família)";
        }

        $obs[] = 'Origem: Formulário público (site)';

        return implode("\n", $obs);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function relatoDoReenvio(array $dados, bool $reaberto, bool $jaMatriculado): string
    {
        $linhas = [
            $reaberto
                ? 'A família, que estava como perdida, preencheu novamente o formulário do site: lead reaberto na etapa inicial.'
                : ($jaMatriculado
                    ? 'Família já matriculada preencheu novamente o formulário do site (possível novo aluno).'
                    : 'A família preencheu novamente o formulário do site.'),
            ...$this->linhasDosAlunos($dados['alunos'] ?? []),
        ];

        if (! empty($dados['observacoes'])) {
            $linhas[] = 'Observações: '.$dados['observacoes'];
        }

        return implode("\n", $linhas);
    }

    /**
     * @param  array<int, array<string, mixed>>  $alunos
     * @return list<string>
     */
    private function linhasDosAlunos(array $alunos): array
    {
        $unidades = Unidade::query()->whereIn('id', array_filter(array_column($alunos, 'unidade_id')))->pluck('nome', 'id');
        $series = Serie::query()->whereIn('id', array_filter(array_column($alunos, 'serie_id')))->pluck('nome', 'id');

        $linhas = [];

        foreach ($alunos as $i => $aluno) {
            $linha = 'Aluno '.($i + 1).': '.($aluno['nome'] ?? '-');

            if (! empty($aluno['unidade_id'])) {
                $linha .= ' | Unidade: '.($unidades[$aluno['unidade_id']] ?? '-');
            }

            if (! empty($aluno['serie_id'])) {
                $linha .= ' | Série: '.($series[$aluno['serie_id']] ?? '-');
            }

            if (! empty($aluno['turno_preferencia'])) {
                $linha .= ' | Turno: '.$aluno['turno_preferencia'];
            }

            $linhas[] = $linha;
        }

        return $linhas;
    }
}
