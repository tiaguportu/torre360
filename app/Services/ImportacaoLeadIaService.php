<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusVisitaInteressado;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\VisitaInteressado;
use App\Rules\Cpf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Grava no CRM o lead extraído pela IA (Gemini) de uma mensagem ou print de conversa.
 *
 * O que a IA devolve é texto livre de terceiros, então nada dele vira cadastro sem passar por aqui:
 * origem, tipo de contato e série só são aceitos se já existirem (antes a IA criava origens e tipos novos a
 * cada importação e, sem série, o aluno ia para `Serie::first()`); CPF, e-mail e datas são validados; quem já
 * tem lead ativo recebe o contato novo em vez de um segundo lead; e tudo acontece numa transação, para uma
 * falha no meio não deixar pessoa e lead pela metade.
 */
class ImportacaoLeadIaService
{
    /** Origem usada quando nem a IA nem o consultor indicam uma que exista. */
    public const ORIGEM_PADRAO = 'WhatsApp/IA';

    /** Tipo de contato quando a IA não informa o canal. */
    public const TIPO_CONTATO_PADRAO = 'WhatsApp';

    /** Tipo de contato quando a IA informa um canal que não existe no cadastro. */
    public const TIPO_CONTATO_OUTRO = 'Outro';

    /** Canais que o prompt de extração oferece à IA (`GeminiAgentService::extrairLead()`). */
    private const TIPOS_CONTATO_DA_IA = ['Ligação', 'WhatsApp', 'E-mail', 'Presencial'];

    /** Vínculos aceitos em `interessado_dependente.vinculo` (enum do banco). */
    private const VINCULOS = ['Pai', 'Mãe', 'Parente', 'Tutor'];

    /** Visitas aceitas por importação: o que a IA devolve é texto de terceiros, então a quantidade tem teto. */
    private const MAX_VISITAS = 5;

    /** Visita mais de um ano no passado ou no futuro é quase certamente ano errado da IA: não é registrada. */
    private const JANELA_VISITA_DIAS = 365;

    /** Horário assumido quando a conversa cita só o dia da visita. */
    private const HORARIO_VISITA_PADRAO = '09:00:00';

    /** Duas menções à visita do mesmo aluno no mesmo dia, com até este intervalo entre os horários, são a mesma visita. */
    private const TOLERANCIA_MESMA_VISITA_MINUTOS = 120;

    /**
     * @param  array<string, mixed>  $extraido  JSON devolvido por `GeminiAgentService::extrairLead()`
     * @return array{interessado: Interessado, reaproveitado: bool, avisos: list<string>, visitas: int}
     */
    public function importar(array $extraido, int $usuarioId, ?int $origemFallbackId = null): array
    {
        return DB::transaction(fn (): array => $this->gravar($extraido, $usuarioId, $origemFallbackId));
    }

    /**
     * @param  array<string, mixed>  $extraido
     * @return array{interessado: Interessado, reaproveitado: bool, avisos: list<string>, visitas: int}
     */
    private function gravar(array $extraido, int $usuarioId, ?int $origemFallbackId): array
    {
        $avisos = [];

        $nome = $this->texto($extraido['responsavel_nome'] ?? null, 255) ?? 'Interessado (Via IA)';
        $email = $this->email($extraido['responsavel_email'] ?? null, $avisos);
        $telefone = $this->texto($extraido['responsavel_telefone'] ?? null, 30);
        $cpf = $this->cpf($extraido['responsavel_cpf'] ?? null, $avisos);

        $pessoa = $this->localizarPessoa($email, $cpf, $telefone)
            ?? Pessoa::create(['nome' => $nome, 'email' => $email, 'telefone' => $telefone, 'cpf' => $cpf]);

        if (! $pessoa->wasRecentlyCreated) {
            $pessoa->fill(array_filter([
                'email' => blank($pessoa->email) ? $email : null,
                'telefone' => blank($pessoa->telefone) ? $telefone : null,
            ]))->save();
        }

        $temperatura = in_array($extraido['temperatura'] ?? null, ['quente', 'morno', 'frio'], true) ? $extraido['temperatura'] : null;
        $valorEstimado = isset($extraido['valor_estimado']) && is_numeric($extraido['valor_estimado']) ? (float) $extraido['valor_estimado'] : null;
        $redes = self::normalizarRedesSociais($extraido['redes_sociais'] ?? []);
        $observacaoIa = filled($extraido['observacoes'] ?? null) ? self::normalizarDatas((string) $extraido['observacoes']) : null;

        $existente = Interessado::query()->ativos()->where('pessoa_id', $pessoa->id)->latest('id')->first();

        [$alunos, $avisosAlunos] = $this->resolverAlunos($extraido['alunos'] ?? []);
        [$visitas, $avisosVisitas] = $this->planejarVisitas($extraido['visitas'] ?? [], $existente);
        $avisosCadastro = array_merge($avisosAlunos, $avisosVisitas);

        if ($existente) {
            $interessado = $existente;
            $avisos = array_merge($avisos, $avisosCadastro);
            $this->complementarLead($interessado, $temperatura, $valorEstimado, $redes, $observacaoIa, $avisos);
        } else {
            $origemId = $this->resolverOrigem($this->texto($extraido['origem_sugerida'] ?? null, 100), $origemFallbackId, $avisos);
            $avisos = array_merge($avisos, $avisosCadastro);

            $interessado = Interessado::create([
                'pessoa_id' => $pessoa->id,
                'usuario_id' => $usuarioId,
                'origem_interessado_id' => $origemId,
                'status_interessado_id' => (StatusInteressado::inicial() ?? StatusInteressado::firstOrCreate(['nome' => 'Novo'], ['cor' => 'info', 'ordem' => 1]))->id,
                'temperatura' => $temperatura,
                'valor_estimado' => $valorEstimado,
                'data_proximo_contato' => now()->addDay(),
                'observacoes' => implode("\n\n", array_filter([
                    $observacaoIa,
                    ...array_map(fn (string $aviso): string => "⚠️ Conferir: {$aviso}", $avisos),
                    '✨ Lead importado via IA (Google Gemini) em '.now()->format('d/m/Y H:i').'.',
                ])),
                'redes_sociais' => $redes,
            ]);
        }

        $this->gravarAlunos($interessado, $alunos);
        $registradas = $this->gravarVisitas($interessado, $visitas, $usuarioId);
        $this->registrarContato($interessado, $extraido, $usuarioId, $existente !== null);

        LeadScoreService::recalcular($interessado);

        return ['interessado' => $interessado->fresh(), 'reaproveitado' => $existente !== null, 'avisos' => array_values($avisos), 'visitas' => $registradas];
    }

    /**
     * Pessoa já cadastrada, por e-mail, CPF ou telefone (nessa ordem). Antes só o e-mail e o telefone idêntico
     * (com a mesma máscara) eram comparados, e a mesma família entrava duas vezes.
     */
    private function localizarPessoa(?string $email, ?string $cpf, ?string $telefone): ?Pessoa
    {
        if ($email !== null && ($pessoa = Pessoa::query()->whereRaw('LOWER(email) = ?', [$email])->first())) {
            return $pessoa;
        }

        if ($cpf !== null && ($pessoa = Pessoa::query()->where('cpf', $cpf)->first())) {
            return $pessoa;
        }

        return $telefone !== null ? Pessoa::query()->comTelefone($telefone)->first() : null;
    }

    /**
     * Lead que já existia: só preenche o que estava vazio e acrescenta o resumo da nova conversa. Consultor,
     * etapa e demais dados do lead não são alterados pela importação. Os avisos da importação entram nas observações
     * como "Conferir" (como num lead novo): quem abrir o lead depois não depende de ter visto a notificação.
     *
     * @param  array<int, array{rede: string, url: string}>|null  $redes
     * @param  list<string>  $avisos
     */
    private function complementarLead(Interessado $lead, ?string $temperatura, ?float $valorEstimado, ?array $redes, ?string $observacaoIa, array $avisos): void
    {
        $dados = array_filter([
            'temperatura' => blank($lead->temperatura) ? $temperatura : null,
            'valor_estimado' => blank($lead->valor_estimado) ? $valorEstimado : null,
        ], fn ($valor) => $valor !== null);

        if ($lead->data_proximo_contato === null || $lead->data_proximo_contato->isPast()) {
            $dados['data_proximo_contato'] = now()->addDay();
        }

        if ($redes !== null) {
            $atuais = collect($lead->redes_sociais ?? []);
            $novas = collect($redes)->reject(fn (array $rede): bool => $atuais->contains('url', $rede['url']));

            if ($novas->isNotEmpty()) {
                $dados['redes_sociais'] = $atuais->concat($novas)->values()->all();
            }
        }

        $nota = implode("\n", array_filter([
            '✨ Nova conversa importada via IA (Google Gemini) em '.now()->format('d/m/Y H:i').'.',
            $observacaoIa,
            ...array_map(fn (string $aviso): string => "⚠️ Conferir: {$aviso}", $avisos),
        ]));
        $dados['observacoes'] = trim(($lead->observacoes ? $lead->observacoes."\n\n" : '').$nota);

        $lead->update($dados);
    }

    /**
     * Origem que a IA sugeriu, se existir no cadastro; senão a escolhida pelo consultor; senão a padrão.
     *
     * @param  list<string>  $avisos
     */
    private function resolverOrigem(?string $sugerida, ?int $fallbackId, array &$avisos): int
    {
        if ($sugerida !== null) {
            $origem = $this->encontrarPorNome(OrigemInteressado::query()->get(['id', 'nome']), $sugerida);

            if ($origem) {
                return (int) $origem->getKey();
            }

            $avisos[] = "A origem \"{$sugerida}\" sugerida pela IA não existe no cadastro de origens.";
        }

        return $fallbackId ?? OrigemInteressado::firstOrCreate(['nome' => self::ORIGEM_PADRAO])->id;
    }

    /**
     * Registra a conversa no histórico do lead. O canal só vira tipo de contato se já existir; um canal
     * desconhecido usa "Outro" e fica citado no relato.
     *
     * @param  array<string, mixed>  $extraido
     */
    private function registrarContato(Interessado $interessado, array $extraido, int $usuarioId, bool $reaproveitado): void
    {
        $relato = self::normalizarDatas(
            filled($extraido['relato_contato'] ?? null)
                ? trim((string) $extraido['relato_contato'])
                : trim((string) ($extraido['observacoes'] ?? ''))
        );

        if ($relato === '') {
            $relato = 'Contato inicial registrado via importação com IA.';
        }

        $canalInformado = $this->texto($extraido['tipo_contato'] ?? null, 60);
        $tipoContato = $canalInformado === null
            ? TipoContatoInteressado::porNome(self::TIPO_CONTATO_PADRAO)
            : $this->resolverTipoContato($canalInformado);

        try {
            $dataContato = filled($extraido['data_contato'] ?? null) ? Carbon::parse((string) $extraido['data_contato']) : now();
        } catch (\Throwable) {
            $dataContato = now();
        }

        if ($dataContato->isFuture()) {
            $dataContato = now();
        }

        $cabecalho = 'Contato em '.$dataContato->format('d/m/Y H:i').' via '.$tipoContato->nome
            .($canalInformado !== null && $tipoContato->nome === self::TIPO_CONTATO_OUTRO ? " (canal informado pela IA: {$canalInformado})" : '').'.';

        if ($reaproveitado) {
            $cabecalho .= ' Conversa importada via IA para um lead que já existia.';
        }

        HistoricoContato::create([
            'interessado_id' => $interessado->id,
            'usuario_id' => $usuarioId,
            'tipo_contato_interessado_id' => $tipoContato->id,
            'relato' => $cabecalho."\n".$relato,
            'data_contato' => $dataContato,
        ]);
    }

    /**
     * Tipo de contato do canal informado: um que já exista; senão, um dos quatro canais que o prompt oferece à IA
     * (criado sob demanda, já que é taxonomia do sistema); qualquer outro texto vira "Outro".
     */
    private function resolverTipoContato(string $canal): TipoContatoInteressado
    {
        $existente = $this->encontrarPorNome(TipoContatoInteressado::query()->get(['id', 'nome']), $canal);

        if ($existente instanceof TipoContatoInteressado) {
            return $existente;
        }

        $alvo = InteressadoDependente::nomeNormalizado($canal);
        $canonico = collect(self::TIPOS_CONTATO_DA_IA)->first(fn (string $nome): bool => InteressadoDependente::nomeNormalizado($nome) === $alvo);

        return TipoContatoInteressado::porNome($canonico ?? self::TIPO_CONTATO_OUTRO);
    }

    /**
     * Alunos citados pela IA, já com série resolvida e dados validados. Sem série correspondente o aluno fica
     * sem série (nunca "a primeira série"), com um aviso para o consultor conferir.
     *
     * @return array{0: list<array{nome: string, serie_id?: int, data_nascimento?: string, vinculo?: string}>, 1: list<string>}
     */
    private function resolverAlunos(mixed $alunos): array
    {
        if (! is_array($alunos) || $alunos === []) {
            return [[], []];
        }

        $series = Serie::query()->get(['id', 'nome']);
        $resolvidos = [];
        $avisos = [];

        foreach ($alunos as $dados) {
            $nome = is_array($dados) ? $this->texto($dados['nome'] ?? null, 255) : null;

            if ($nome === null) {
                continue;
            }

            $serieTexto = $this->texto($dados['serie_pretendida'] ?? null, 100);
            $serie = $serieTexto === null ? null : $this->encontrarPorNome($series, $serieTexto);

            if ($serieTexto !== null && $serie === null) {
                $avisos[] = "A série \"{$serieTexto}\" informada para {$nome} não foi encontrada (ou é ambígua); o aluno ficou sem série.";
            }

            $resolvidos[] = array_filter([
                'nome' => $nome,
                'serie_id' => $serie?->getKey(),
                'data_nascimento' => $this->nascimento($dados['data_nascimento'] ?? null),
                'vinculo' => in_array($dados['vinculo'] ?? null, self::VINCULOS, true) ? $dados['vinculo'] : null,
            ], fn ($valor) => filled($valor));
        }

        return [$resolvidos, $avisos];
    }

    /**
     * Cadastra os alunos no lead. Se o lead já tinha o aluno (mesmo nome, ignorando caixa/acentos), só completa o
     * que faltava.
     *
     * @param  list<array{nome: string, serie_id?: int, data_nascimento?: string, vinculo?: string}>  $alunos
     */
    private function gravarAlunos(Interessado $interessado, array $alunos): void
    {
        if ($alunos === []) {
            return;
        }

        $existentes = $interessado->dependentes()->get();

        foreach ($alunos as $aluno) {
            $informados = Arr::except($aluno, 'nome');
            $nomeNormalizado = InteressadoDependente::nomeNormalizado($aluno['nome']);
            $dependente = $existentes->first(fn (InteressadoDependente $d): bool => InteressadoDependente::nomeNormalizado($d->nome_crianca) === $nomeNormalizado);

            if ($dependente) {
                $dependente->update(array_filter($informados, fn ($valor, string $campo): bool => blank($dependente->{$campo}), ARRAY_FILTER_USE_BOTH));

                continue;
            }

            $existentes->push($interessado->dependentes()->create($informados + ['nome_crianca' => $aluno['nome']]));
        }
    }

    /**
     * Plano das visitas à escola citadas pela IA (já feitas ou marcadas): cada item é uma visita a criar
     * (`visita` nulo) ou uma visita do lead a atualizar (`visita` preenchido). Data e situação são validadas:
     * visita sem data legível ou fora de uma janela plausível é descartada com aviso; "realizada"/"faltou" numa data
     * futura vira "agendada"; e visita já passada que a conversa não confirma fica "agendada" (aparece como atrasada)
     * para o consultor dizer se aconteceu.
     *
     * Antes de planejar, cada visita é confrontada com as que o lead já tem e com as anteriores da mesma conversa
     * (ver `visitaJaConhecida()`): a repetida não é criada (reimportar a conversa, ou uma conversa nova sobre a mesma
     * visita, é comum) e só sai de "agendada" se a conversa agora confirma o desfecho. O que o consultor já alterou
     * à mão nunca é desfeito. Os avisos de horário assumido e de visita passada só saem para a visita que será criada.
     *
     * @return array{0: list<array{visita: ?VisitaInteressado, data_hora: Carbon, status: StatusVisitaInteressado, aluno: ?string, observacoes: ?string}>, 1: list<string>}
     */
    private function planejarVisitas(mixed $visitas, ?Interessado $lead): array
    {
        if (! is_array($visitas) || $visitas === []) {
            return [[], []];
        }

        $conhecidas = $lead === null ? [] : $lead->visitas()->with('dependente:id,nome_crianca')->get()
            ->map(fn (VisitaInteressado $visita): array => [
                'data_hora' => $visita->data_hora,
                'aluno' => self::nomeDeAluno($visita->dependente?->nome_crianca),
                'status' => $visita->status,
                'visita' => $visita,
            ])->all();

        $plano = [];
        $avisos = [];

        foreach (array_slice(array_values($visitas), 0, self::MAX_VISITAS) as $dados) {
            if (! is_array($dados)) {
                continue;
            }

            $quando = $this->dataHoraDaVisita($dados['data_hora'] ?? null, $avisos);

            if ($quando === null) {
                continue;
            }

            $dataHora = $quando['data_hora'];
            $aluno = $this->texto($dados['aluno'] ?? null, 255);
            [$status, $avisoSituacao] = $this->situacaoDaVisita($dados['situacao'] ?? null, $dataHora);

            $indice = $this->visitaJaConhecida($conhecidas, $dataHora, $quando['sem_horario'], self::nomeDeAluno($aluno), $status);

            if ($indice !== null) {
                $conhecida = $conhecidas[$indice];

                if ($conhecida['visita'] !== null && $conhecida['status'] === StatusVisitaInteressado::Agendada && $status !== StatusVisitaInteressado::Agendada) {
                    $conhecidas[$indice]['status'] = $status;
                    $plano[] = ['visita' => $conhecida['visita'], 'data_hora' => $dataHora, 'status' => $status, 'aluno' => $aluno, 'observacoes' => null];
                }

                continue;
            }

            if ($quando['sem_horario']) {
                $avisos[] = 'A conversa cita a visita de '.$dataHora->format('d/m/Y').' sem horário: foi registrada às '.$dataHora->format('H:i').'. Confirme o horário.';
            }

            if ($avisoSituacao !== null) {
                $avisos[] = $avisoSituacao;
            }

            $observacoes = $this->texto($dados['observacoes'] ?? null, 500);

            $conhecidas[] = ['data_hora' => $dataHora, 'aluno' => self::nomeDeAluno($aluno), 'status' => $status, 'visita' => null];
            $plano[] = [
                'visita' => null,
                'data_hora' => $dataHora,
                'status' => $status,
                'aluno' => $aluno,
                'observacoes' => $observacoes === null ? null : self::normalizarDatas($observacoes),
            ];
        }

        return [$plano, $avisos];
    }

    /**
     * Situação da visita já ajustada à data, e o aviso (se houver) do que foi ajustado ou precisa de conferência.
     *
     * @return array{0: StatusVisitaInteressado, 1: ?string}
     */
    private function situacaoDaVisita(mixed $informada, Carbon $dataHora): array
    {
        $status = StatusVisitaInteressado::tryFrom(mb_strtolower($this->texto($informada, 20) ?? '')) ?? StatusVisitaInteressado::Agendada;
        $rotulo = $dataHora->format('d/m/Y H:i');

        if ($dataHora->isFuture() && in_array($status, [StatusVisitaInteressado::Realizada, StatusVisitaInteressado::Faltou], true)) {
            return [StatusVisitaInteressado::Agendada, "A visita de {$rotulo} está no futuro, então foi registrada como Agendada (a IA a deu como {$status->getLabel()})."];
        }

        if ($dataHora->isPast() && $status === StatusVisitaInteressado::Agendada) {
            return [$status, "A visita de {$rotulo} já passou e a conversa não diz se aconteceu: ficou como Agendada (atrasada). Marque como Realizada ou Não compareceu."];
        }

        return [$status, null];
    }

    /**
     * Posição, em `$conhecidas`, da visita que a citada repete; null se for uma visita nova. É a mesma visita se for
     * no mesmo dia, do mesmo aluno (aluno omitido = família toda, que cobre qualquer aluno) e com até
     * `TOLERANCIA_MESMA_VISITA_MINUTOS` entre os horários, ou sem horário na conversa (só o dia). Alunos diferentes
     * no mesmo dia, ou horários distantes, são visitas diferentes. Uma visita cancelada nunca absorve uma que não
     * seja cancelamento: marcar de novo depois de cancelar é remarcação.
     *
     * @param  list<array{data_hora: Carbon, aluno: ?string, status: StatusVisitaInteressado, visita: ?VisitaInteressado}>  $conhecidas
     */
    private function visitaJaConhecida(array $conhecidas, Carbon $dataHora, bool $semHorario, ?string $aluno, StatusVisitaInteressado $status): ?int
    {
        foreach ($conhecidas as $indice => $conhecida) {
            $mesmoAluno = $conhecida['aluno'] === null || $aluno === null
                || $conhecida['aluno'] === $aluno
                || self::contemPalavra($conhecida['aluno'], $aluno)
                || self::contemPalavra($aluno, $conhecida['aluno']);

            $mesmoMomento = $conhecida['data_hora']->isSameDay($dataHora)
                && ($semHorario || abs($conhecida['data_hora']->diffInMinutes($dataHora)) <= self::TOLERANCIA_MESMA_VISITA_MINUTOS);

            $remarcacao = $conhecida['status'] === StatusVisitaInteressado::Cancelada && $status !== StatusVisitaInteressado::Cancelada;

            if ($mesmoAluno && $mesmoMomento && ! $remarcacao) {
                return $indice;
            }
        }

        return null;
    }

    private static function nomeDeAluno(?string $nome): ?string
    {
        $normalizado = InteressadoDependente::nomeNormalizado($nome);

        return $normalizado === '' ? null : $normalizado;
    }

    /**
     * Data e hora da visita (AAAA-MM-DD HH:MM[:SS], ou DD/MM/AAAA). Só o dia: assume o horário padrão (e `sem_horario`
     * avisa quem planeja, que decide se a visita será criada). Data inválida ou implausível: null, com aviso.
     *
     * @param  list<string>  $avisos
     * @return array{data_hora: Carbon, sem_horario: bool}|null
     */
    private function dataHoraDaVisita(mixed $valor, array &$avisos): ?array
    {
        $texto = $this->texto($valor, 40);

        if ($texto === null) {
            return null;
        }

        $iso = preg_replace('#^(\d{2})/(\d{2})/(\d{4})#', '$3-$2-$1', str_replace('T', ' ', $texto)) ?? $texto;

        if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $iso, $partes) !== 1) {
            $avisos[] = "A data de visita \"{$texto}\" extraída pela IA não é válida e a visita não foi registrada.";

            return null;
        }

        $semHorario = ! isset($partes[1]);

        try {
            $dataHora = Carbon::parse($semHorario ? $iso.' '.self::HORARIO_VISITA_PADRAO : $iso);

            // 31/02 "transborda" para março sem erro: só vale se o dia continua o mesmo.
            if ($dataHora->format('Y-m-d') !== substr($iso, 0, 10)) {
                throw new \InvalidArgumentException('Data inexistente.');
            }
        } catch (\Throwable) {
            $avisos[] = "A data de visita \"{$texto}\" extraída pela IA não é válida e a visita não foi registrada.";

            return null;
        }

        if ($dataHora->lt(now()->subDays(self::JANELA_VISITA_DIAS)) || $dataHora->gt(now()->addDays(self::JANELA_VISITA_DIAS))) {
            $avisos[] = 'A visita de '.$dataHora->format('d/m/Y').' está a mais de um ano de hoje (possível ano errado da IA) e não foi registrada.';

            return null;
        }

        return ['data_hora' => $dataHora, 'sem_horario' => $semHorario];
    }

    /**
     * Grava o plano de `planejarVisitas()`: cria as visitas novas e atualiza a situação das que a conversa confirmou.
     *
     * @param  list<array{visita: ?VisitaInteressado, data_hora: Carbon, status: StatusVisitaInteressado, aluno: ?string, observacoes: ?string}>  $visitas
     * @return int Visitas criadas ou atualizadas.
     */
    private function gravarVisitas(Interessado $interessado, array $visitas, int $usuarioId): int
    {
        if ($visitas === []) {
            return 0;
        }

        $dependentes = $interessado->dependentes()->get();
        $registradas = 0;

        foreach ($visitas as $visita) {
            if ($visita['visita'] !== null) {
                $visita['visita']->update(['status' => $visita['status']]);
                $registradas++;

                continue;
            }

            $alvo = $visita['aluno'] === null ? '' : InteressadoDependente::nomeNormalizado($visita['aluno']);
            $dependente = $alvo === '' ? null : $dependentes->first(fn (InteressadoDependente $d): bool => InteressadoDependente::nomeNormalizado($d->nome_crianca) === $alvo);

            VisitaInteressadoService::agendar(
                $interessado,
                $visita['data_hora'],
                $interessado->usuario_id ?? $usuarioId,
                $dependente?->id,
                implode("\n", array_filter([$visita['observacoes'], '✨ Visita registrada via IA (Google Gemini) em '.now()->format('d/m/Y H:i').'.'])),
                $visita['status'],
            );
            $registradas++;
        }

        return $registradas;
    }

    /**
     * Item pelo nome: igual sem caixa/acentos/espaços repetidos; na falta, o único cujo nome contém o termo (ou
     * está contido nele) como palavra inteira. Vários candidatos ou nenhum: null.
     *
     * @param  Collection<int, Model>  $itens
     */
    private function encontrarPorNome(Collection $itens, string $busca): ?Model
    {
        $alvo = InteressadoDependente::nomeNormalizado($busca);

        if ($alvo === '') {
            return null;
        }

        $exato = $itens->first(fn (Model $item): bool => InteressadoDependente::nomeNormalizado((string) $item->getAttribute('nome')) === $alvo);

        if ($exato) {
            return $exato;
        }

        $parecidos = $itens->filter(function (Model $item) use ($alvo): bool {
            $nome = InteressadoDependente::nomeNormalizado((string) $item->getAttribute('nome'));

            return $nome !== '' && (self::contemPalavra($nome, $alvo) || self::contemPalavra($alvo, $nome));
        });

        return $parecidos->count() === 1 ? $parecidos->first() : null;
    }

    /** `1º ano` está em `1º ano ensino fundamental`, mas não em `11º ano`. */
    private static function contemPalavra(string $texto, string $termo): bool
    {
        return preg_match('/(?<![a-z0-9])'.preg_quote($termo, '/').'(?![a-z0-9])/', $texto) === 1;
    }

    private function texto(mixed $valor, int $limite): ?string
    {
        if (! is_scalar($valor)) {
            return null;
        }

        $texto = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $valor));

        return $texto === '' ? null : mb_substr($texto, 0, $limite);
    }

    /**
     * @param  list<string>  $avisos
     */
    private function email(mixed $valor, array &$avisos): ?string
    {
        $email = $this->texto($valor, 255);

        if ($email === null) {
            return null;
        }

        $email = mb_strtolower($email);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $avisos[] = "O e-mail \"{$email}\" extraído pela IA não é válido e não foi salvo.";

            return null;
        }

        return $email;
    }

    /**
     * @param  list<string>  $avisos
     */
    private function cpf(mixed $valor, array &$avisos): ?string
    {
        $digitos = preg_replace('/\D/', '', is_scalar($valor) ? (string) $valor : '') ?? '';

        if ($digitos === '') {
            return null;
        }

        if (! Cpf::valido($digitos)) {
            $avisos[] = 'O CPF extraído pela IA não passou na validação dos dígitos verificadores e não foi salvo.';

            return null;
        }

        return $digitos;
    }

    /**
     * Data de nascimento no formato ISO (a IA devolve AAAA-MM-DD; DD/MM/AAAA também é aceito). Datas futuras,
     * anteriores a 1950 ou ilegíveis viram null: um valor inválido derrubaria o cadastro inteiro.
     */
    private function nascimento(mixed $valor): ?string
    {
        $texto = $this->texto($valor, 30);

        if ($texto === null) {
            return null;
        }

        try {
            $data = preg_match('#^\d{2}/\d{2}/\d{4}#', $texto)
                ? Carbon::createFromFormat('d/m/Y', substr($texto, 0, 10))
                : Carbon::parse($texto);
        } catch (\Throwable) {
            return null;
        }

        return $data->isFuture() || $data->year < 1950 ? null : $data->toDateString();
    }

    /**
     * Mantém só redes conhecidas com link http(s) válido; devolve null se não sobrar nenhuma.
     *
     * @return array<int, array{rede: string, url: string}>|null
     */
    public static function normalizarRedesSociais(mixed $redes): ?array
    {
        if (! is_array($redes)) {
            return null;
        }

        $resultado = [];
        foreach ($redes as $item) {
            $url = trim((string) ($item['url'] ?? ''));
            $rede = mb_strtolower(trim((string) ($item['rede'] ?? '')));

            if ($url !== '' && ! preg_match('#^https?://#i', $url) && str_contains($url, '.')) {
                $url = 'https://'.ltrim($url, '/');
            }

            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $resultado[] = [
                'rede' => array_key_exists($rede, Interessado::REDES_SOCIAIS) ? $rede : 'outra',
                'url' => $url,
            ];
        }

        return $resultado === [] ? null : $resultado;
    }

    /**
     * Converte datas em ISO (AAAA-MM-DD) ou com hífen/ponto (DD-MM-AAAA) para DD/MM/AAAA.
     */
    public static function normalizarDatas(string $texto): string
    {
        $texto = preg_replace('/(?<!\d)(\d{4})-(\d{2})-(\d{2})(?!\d)/', '$3/$2/$1', $texto) ?? $texto;

        return preg_replace('/(?<!\d)(\d{2})[-.](\d{2})[-.](\d{4})(?!\d)/', '$1/$2/$3', $texto) ?? $texto;
    }
}
