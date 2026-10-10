<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
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

    /**
     * @param  array<string, mixed>  $extraido  JSON devolvido por `GeminiAgentService::extrairLead()`
     * @return array{interessado: Interessado, reaproveitado: bool, avisos: list<string>}
     */
    public function importar(array $extraido, int $usuarioId, ?int $origemFallbackId = null): array
    {
        return DB::transaction(fn (): array => $this->gravar($extraido, $usuarioId, $origemFallbackId));
    }

    /**
     * @param  array<string, mixed>  $extraido
     * @return array{interessado: Interessado, reaproveitado: bool, avisos: list<string>}
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

        [$alunos, $avisosAlunos] = $this->resolverAlunos($extraido['alunos'] ?? []);

        $existente = Interessado::query()->ativos()->where('pessoa_id', $pessoa->id)->latest('id')->first();

        if ($existente) {
            $interessado = $existente;
            $avisos = array_merge($avisos, $avisosAlunos);
            $this->complementarLead($interessado, $temperatura, $valorEstimado, $redes, $observacaoIa);
        } else {
            $origemId = $this->resolverOrigem($this->texto($extraido['origem_sugerida'] ?? null, 100), $origemFallbackId, $avisos);
            $avisos = array_merge($avisos, $avisosAlunos);

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
        $this->registrarContato($interessado, $extraido, $usuarioId, $existente !== null);

        LeadScoreService::recalcular($interessado);

        return ['interessado' => $interessado->fresh(), 'reaproveitado' => $existente !== null, 'avisos' => array_values($avisos)];
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
     * etapa e demais dados do lead não são alterados pela importação.
     *
     * @param  array<int, array{rede: string, url: string}>|null  $redes
     */
    private function complementarLead(Interessado $lead, ?string $temperatura, ?float $valorEstimado, ?array $redes, ?string $observacaoIa): void
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

        $nota = '✨ Nova conversa importada via IA (Google Gemini) em '.now()->format('d/m/Y H:i').'.'
            .($observacaoIa ? "\n{$observacaoIa}" : '');
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
