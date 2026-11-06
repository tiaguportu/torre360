<?php

declare(strict_types=1);

namespace App\Services\Qualificacao;

use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\InteressadoQualificacao;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Services\TermometroVagasService;
use App\Support\Qualificacao\CampoQualificacao;
use App\Support\Qualificacao\Roteiro;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

/**
 * Roteiro de qualificação do lead (SDR): o que a família já disse, o que falta perguntar e se o lead está pronto para a
 * visita. Regras:
 *
 *  - O PHP decide o que falta e o que perguntar a seguir; quem redige (consultor, Copiloto IA) só reescreve a pergunta.
 *  - Quem grava por aqui (IA, importação) **nunca sobrescreve** uma resposta que já existe: devolve `conflito`.
 *  - "Qualificado" é o estado em que todos os campos do `gate` do roteiro foram respondidos (ou a família preferiu não
 *    responder). Mensalidade nunca é exigida, e nada aqui move o lead de etapa nem o dá por perdido.
 *
 * @see Roteiro
 */
final class QualificacaoService
{
    public const GRAVADO = 'gravado';

    public const CONFLITO = 'conflito';

    public const INVALIDO = 'invalido';

    public const SEM_ALUNO = 'sem_aluno';

    /** Ano letivo de referência do corte etário (31/03) é o do próximo ingresso possível. */
    private const CORTE_ETARIO_MES = 3;

    private const CORTE_ETARIO_DIA = 31;

    // ─── Leitura ────────────────────────────────────────────────

    public function qualificacaoDe(Interessado $lead, bool $recarregar = false): ?InteressadoQualificacao
    {
        if ($recarregar || ! $lead->relationLoaded('qualificacao')) {
            $lead->load('qualificacao');
        }

        return $lead->qualificacao;
    }

    /**
     * @return Collection<int, InteressadoDependente>
     */
    private function dependentesDe(Interessado $lead, bool $recarregar = false): Collection
    {
        if ($recarregar || ! $lead->relationLoaded('dependentes')) {
            $lead->load('dependentes');
        }

        return $lead->dependentes;
    }

    /**
     * Situação de cada campo do roteiro, na ordem do roteiro.
     *
     * @return array<string, array{estado: string, fonte: ?string, evidencia: ?string, atualizado_em: ?string}>
     */
    public function estados(Interessado $lead): array
    {
        $qualificacao = $this->qualificacaoDe($lead);
        $dependentes = $this->dependentesDe($lead);
        $estados = [];

        foreach (Roteiro::campos() as $campo) {
            if (! $this->seAplica($campo, $dependentes)) {
                $estados[$campo->chave] = $this->estado(Roteiro::ESTADO_NAO_APLICAVEL);

                continue;
            }

            $estados[$campo->chave] = $campo->ehDoAluno()
                ? $this->estadoDoAluno($campo, $dependentes)
                : $this->estadoDaFamilia($campo, $qualificacao);
        }

        return $estados;
    }

    /**
     * Campos que ainda esperam resposta (não respondidos nem recusados), na ordem do roteiro.
     *
     * @return list<CampoQualificacao>
     */
    public function lacunas(Interessado $lead): array
    {
        $estados = $this->estados($lead);

        return array_values(array_filter(
            Roteiro::campos(),
            fn (CampoQualificacao $campo): bool => in_array($estados[$campo->chave]['estado'], [Roteiro::ESTADO_NAO_PERGUNTADO, Roteiro::ESTADO_PERGUNTADO], true),
        ));
    }

    /**
     * As próximas perguntas da conversa: quem nunca foi perguntado vem antes de quem já foi perguntado e segue sem
     * resposta, e a mensalidade nunca entra sozinha. Poucas por mensagem: WhatsApp com questionário parece interrogatório.
     *
     * @return list<CampoQualificacao>
     */
    public function proximasPerguntas(Interessado $lead, ?int $maximo = null): array
    {
        $maximo ??= max(1, (int) config('crm.qualificacao.max_perguntas_por_mensagem', 2));
        $estados = $this->estados($lead);

        $perguntaveis = array_values(array_filter(
            $this->lacunas($lead),
            fn (CampoQualificacao $campo): bool => $campo->perguntarAutomaticamente,
        ));

        usort($perguntaveis, fn (CampoQualificacao $a, CampoQualificacao $b): int => ($estados[$a->chave]['estado'] === Roteiro::ESTADO_PERGUNTADO) <=> ($estados[$b->chave]['estado'] === Roteiro::ESTADO_PERGUNTADO));

        return array_slice($perguntaveis, 0, $maximo);
    }

    /**
     * Avaliação do roteiro, sem gravar nada.
     *
     * @return array{resultado: string, gate_ok: bool, respondidos: int, total: int, faltando: list<string>}
     */
    public function avaliar(Interessado $lead): array
    {
        $estados = $this->estados($lead);
        $faltando = [];
        $total = 0;
        $atendidos = 0;
        $algum = false;

        foreach (Roteiro::campos() as $campo) {
            $estado = $estados[$campo->chave]['estado'];

            if (in_array($estado, [Roteiro::ESTADO_RESPONDIDO, Roteiro::ESTADO_PERGUNTADO], true)) {
                $algum = true;
            }

            if (! $campo->gate || $estado === Roteiro::ESTADO_NAO_APLICAVEL) {
                continue;
            }

            $total++;

            // "Recusou" encerra a pergunta: a escola perguntou e a família preferiu não informar.
            if (in_array($estado, [Roteiro::ESTADO_RESPONDIDO, Roteiro::ESTADO_RECUSOU], true)) {
                $atendidos++;
            } else {
                $faltando[] = $campo->chave;
            }
        }

        // Sem nenhum campo necessário (a secretaria desligou todos), ninguém "se qualifica" sozinho: a configuração exige ao menos um.
        $gateOk = $total > 0 && $faltando === [];

        return [
            'resultado' => match (true) {
                $gateOk => InteressadoQualificacao::RESULTADO_QUALIFICADO,
                $algum => InteressadoQualificacao::RESULTADO_EM_QUALIFICACAO,
                default => InteressadoQualificacao::RESULTADO_NAO_INICIADA,
            },
            'gate_ok' => $gateOk,
            'respondidos' => $atendidos,
            'total' => $total,
            'faltando' => $faltando,
        ];
    }

    /**
     * Avisos objetivos para o consultor conferir antes de marcar a visita. Nunca desqualificam o lead sozinhos (isso é
     * decisão de quem atende, pelo fluxo de "Perdido") e nunca olham para a capacidade de pagamento da família.
     *
     * @return list<string>
     */
    public function alertas(Interessado $lead): array
    {
        $alertas = [];
        $lead->loadMissing('dependentes.serie');
        $dependentes = $lead->dependentes;

        if ($dependentes->contains(fn (InteressadoDependente $d): bool => filled($d->serie_id))) {
            try {
                if ((TermometroVagasService::obterStatusParaLead($lead)['nivel_mais_critico'] ?? null) === 'esgotado') {
                    $alertas[] = 'Sem vagas na série pretendida: confira a lista de espera antes de marcar a visita.';
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        $corte = $this->dataDeCorteEtario();

        foreach ($dependentes as $dependente) {
            $minima = $dependente->serie?->idade_minima;

            if ($dependente->data_nascimento === null || $minima === null) {
                continue;
            }

            $idade = (int) floor($dependente->data_nascimento->diffInYears($corte));

            if ($idade < (int) $minima) {
                $alertas[] = sprintf(
                    '%s terá %d ano(s) em %s, e a série %s pede no mínimo %d: confira a idade.',
                    $dependente->nome_crianca,
                    $idade,
                    $corte->format('d/m/Y'),
                    $dependente->serie?->nome,
                    (int) $minima,
                );
            }
        }

        if ($lead->faixa_distancia_escola === 'mais_de_10km') {
            $alertas[] = 'Mora a mais de 10 km da escola.';
        }

        return $alertas;
    }

    /**
     * Dados para o painel do roteiro na ficha do lead.
     *
     * @return array<string, mixed>
     */
    public function painel(Interessado $lead): array
    {
        $estados = $this->estados($lead);
        $avaliacao = $this->avaliar($lead);
        $lead->loadMissing('dependentes.serie');
        $dimensoes = [];

        foreach (Roteiro::campos() as $campo) {
            $estado = $estados[$campo->chave]['estado'];

            if ($estado === Roteiro::ESTADO_NAO_APLICAVEL) {
                continue;
            }

            $dimensoes[$campo->dimensao]['rotulo'] = Roteiro::DIMENSOES[$campo->dimensao];
            $dimensoes[$campo->dimensao]['campos'][] = [
                'chave' => $campo->chave,
                'rotulo' => $campo->rotulo,
                'nivel' => $campo->nivel,
                'gate' => $campo->gate,
                'estado' => $estado,
                'valor' => $estado === Roteiro::ESTADO_RESPONDIDO ? $this->valorLegivel($campo, $lead) : null,
                'texto' => $this->textoDeEstado($lead, $campo->chave),
            ];
        }

        return $avaliacao + [
            'resultado_rotulo' => InteressadoQualificacao::RESULTADOS[$avaliacao['resultado']],
            'dimensoes' => $dimensoes,
            'alertas' => $this->alertas($lead),
            'etapa_sugerida' => $this->sugerirEtapa($lead)?->nome,
        ];
    }

    /**
     * Frase curta de procedência do campo para a ficha ("Respondido · Consultor, 05/11/2026"), ou null quando ninguém
     * perguntou nada ainda.
     */
    public function textoDeEstado(?Interessado $lead, string $chave): ?string
    {
        if ($lead === null || ! $lead->exists) {
            return null;
        }

        $estado = $this->estados($lead)[$chave] ?? null;

        return match ($estado['estado'] ?? null) {
            Roteiro::ESTADO_RESPONDIDO => $this->textoDeResposta($estado),
            Roteiro::ESTADO_PERGUNTADO => 'Já perguntado à família, aguardando resposta',
            Roteiro::ESTADO_RECUSOU => 'A família preferiu não informar',
            default => null,
        };
    }

    /**
     * A etapa do funil que o lead qualificado poderia ocupar, escolhida pela escola em `crm.qualificacao.etapa_qualificado`
     * (nome de uma etapa já cadastrada). Só sugere: quem move é o consultor, pelo fluxo de sempre. Nada é criado por aqui.
     */
    public function sugerirEtapa(Interessado $lead): ?StatusInteressado
    {
        $nome = mb_strtolower(trim((string) config('crm.qualificacao.etapa_qualificado')));

        if ($nome === '' || $this->qualificacaoDe($lead)?->resultado !== InteressadoQualificacao::RESULTADO_QUALIFICADO) {
            return null;
        }

        $alvo = StatusInteressado::query()
            ->where('is_final', false)
            ->where('is_ganho', false)
            ->whereRaw('LOWER(TRIM(nome)) = ?', [$nome])
            ->first();

        if ($alvo === null || (int) $lead->status_interessado_id === (int) $alvo->id) {
            return null;
        }

        $atual = $lead->status_interessado_id ? StatusInteressado::query()->find($lead->status_interessado_id) : null;

        // Só sugere avançar: nunca "voltar" um lead que já passou dessa etapa.
        return $atual !== null && (int) $alvo->ordem <= (int) $atual->ordem ? null : $alvo;
    }

    // ─── Escrita ────────────────────────────────────────────────

    /**
     * Grava a resposta de um campo com a procedência (quem disse, de onde veio, trecho que a justifica). Não sobrescreve
     * uma resposta existente a menos que `$sobrescrever` seja pedido (confirmação explícita de quem revisa).
     *
     * @param  InteressadoDependente|null  $aluno  Obrigatório para campos de aluno quando o lead tem mais de um aluno.
     * @return string Uma das constantes GRAVADO, CONFLITO, INVALIDO ou SEM_ALUNO.
     */
    public function registrar(
        Interessado $lead,
        string $chave,
        mixed $valor,
        string $fonte,
        ?string $evidencia = null,
        ?int $usuarioId = null,
        ?InteressadoDependente $aluno = null,
        bool $sobrescrever = false,
    ): string {
        $campo = Roteiro::campo($chave) ?? throw new InvalidArgumentException("Campo de qualificação desconhecido: {$chave}.");

        if (! array_key_exists($fonte, InteressadoQualificacao::FONTES)) {
            throw new InvalidArgumentException("Procedência desconhecida: {$fonte}.");
        }

        $valor = $this->normalizar($campo, $valor);

        if ($valor === null) {
            return self::INVALIDO;
        }

        $meta = [
            'estado' => Roteiro::ESTADO_RESPONDIDO,
            'fonte' => $fonte,
            'evidencia' => $evidencia !== null && trim($evidencia) !== '' ? mb_substr(trim($evidencia), 0, 500) : null,
            'atualizado_em' => now()->toIso8601String(),
            'atualizado_por' => $usuarioId,
        ];

        if ($campo->ehDoAluno()) {
            $dependentes = $this->dependentesDe($lead, true);
            $alvo = $aluno ?? ($dependentes->count() === 1 ? $dependentes->first() : null);

            if ($alvo === null || (int) $alvo->interessado_id !== (int) $lead->id) {
                return self::SEM_ALUNO;
            }

            if (filled($alvo->getAttribute($chave)) && ! $sobrescrever) {
                return self::CONFLITO;
            }

            $metas = $alvo->qualificacao_meta ?? [];
            $metas[$chave] = $meta;
            $alvo->forceFill([$chave => $valor, 'qualificacao_meta' => $metas])->save();
        } else {
            $qualificacao = $this->qualificacaoDe($lead, true) ?? $this->novaQualificacao($lead);

            if (filled($qualificacao->getAttribute($chave)) && ! $sobrescrever) {
                return self::CONFLITO;
            }

            $campos = $qualificacao->campos ?? [];
            $campos[$chave] = $meta;
            $qualificacao->forceFill([$chave => $valor, 'campos' => $campos])->save();
        }

        $this->reavaliar($lead);

        return self::GRAVADO;
    }

    /**
     * Anota que a pergunta foi feita à família (quando o consultor abre o WhatsApp com ela), sem tocar em quem já
     * respondeu ou recusou.
     *
     * @param  list<string>  $chaves
     */
    public function marcarPerguntado(Interessado $lead, array $chaves, ?int $usuarioId = null): void
    {
        $this->anotar($lead, $chaves, Roteiro::ESTADO_PERGUNTADO, $usuarioId);
    }

    /**
     * Anota que a família preferiu não responder: o Copiloto para de insistir e o roteiro dá a pergunta por encerrada.
     */
    public function marcarRecusado(Interessado $lead, string $chave, ?int $usuarioId = null): void
    {
        $this->anotar($lead, [$chave], Roteiro::ESTADO_RECUSOU, $usuarioId);
    }

    /**
     * Recalcula e guarda o resultado do roteiro. Não cria a linha de qualificação de um lead que nem começou.
     */
    public function reavaliar(Interessado $lead): void
    {
        $this->dependentesDe($lead, true);
        $qualificacao = $this->qualificacaoDe($lead, true);
        $avaliacao = $this->avaliar($lead);

        if ($qualificacao === null) {
            if ($avaliacao['resultado'] === InteressadoQualificacao::RESULTADO_NAO_INICIADA) {
                return;
            }

            $qualificacao = $this->novaQualificacao($lead);
        }

        if ($qualificacao->exists && $qualificacao->resultado === $avaliacao['resultado']) {
            return;
        }

        $qualificacao->forceFill([
            'resultado' => $avaliacao['resultado'],
            'qualificado_em' => $avaliacao['resultado'] === InteressadoQualificacao::RESULTADO_QUALIFICADO ? ($qualificacao->qualificado_em ?? now()) : null,
        ])->save();

        $lead->setRelation('qualificacao', $qualificacao);
    }

    /**
     * Recalcula o resultado de todos os leads em andamento (ou de todos, com `$comEncerrados`). Usado pelo comando
     * `crm:reavaliar-qualificacao` e pelo job que roda quando a secretaria muda o que é necessário para a visita.
     *
     * @return int Quantos leads foram reavaliados.
     */
    public function reavaliarTodos(bool $comEncerrados = false): int
    {
        $total = 0;

        Interessado::query()
            ->when(! $comEncerrados, fn ($consulta) => $consulta->ativos())
            ->chunkById(200, function ($leads) use (&$total): void {
                foreach ($leads as $lead) {
                    $this->reavaliar($lead);
                    $total++;
                }
            });

        return $total;
    }

    /**
     * O que o Copiloto "Conhecer a família" pode saber do roteiro. Vão **só** os valores de campos de categoria fechada
     * (quem decide, motivo, prazo, situação atual): bairro, mensalidade e escolas em comparação seguem apenas como
     * "informado", porque a IA não precisa deles para escolher a próxima pergunta (minimização de dados, LGPD).
     *
     * @return array{conhecido: list<string>, recusado: list<string>}
     */
    public function resumoParaIa(Interessado $lead): array
    {
        $lead->loadMissing('dependentes.serie');
        $estados = $this->estados($lead);
        $conhecido = [];
        $recusado = [];

        foreach (Roteiro::campos() as $campo) {
            $estado = $estados[$campo->chave]['estado'];

            if ($estado === Roteiro::ESTADO_RECUSOU) {
                $recusado[] = $campo->rotulo;
            } elseif ($estado === Roteiro::ESTADO_RESPONDIDO) {
                $valor = in_array($campo->chave, self::VALORES_QUE_VAO_PARA_A_IA, true) ? $this->valorLegivel($campo, $lead) : null;
                $conhecido[] = $valor !== null ? "{$campo->rotulo}: {$valor}" : "{$campo->rotulo}: informado";
            }
        }

        return ['conhecido' => $conhecido, 'recusado' => $recusado];
    }

    // ─── Internos ───────────────────────────────────────────────

    /** Campos de categoria fechada cujo valor pode seguir ao Gemini. */
    private const VALORES_QUE_VAO_PARA_A_IA = ['decisor_tipo', 'decisor_vai_visita', 'motivo_busca', 'prazo_decisao', 'situacao_atual'];

    /**
     * @param  array{estado: string, fonte: ?string, evidencia: ?string, atualizado_em: ?string}  $estado
     */
    private function textoDeResposta(array $estado): string
    {
        $fonte = InteressadoQualificacao::FONTES[$estado['fonte'] ?? ''] ?? InteressadoQualificacao::FONTES[InteressadoQualificacao::FONTE_CONSULTOR];
        $data = filled($estado['atualizado_em']) ? ', '.Carbon::parse($estado['atualizado_em'])->format('d/m/Y') : '';

        return "Respondido · {$fonte}{$data}";
    }

    private function novaQualificacao(Interessado $lead): InteressadoQualificacao
    {
        $qualificacao = new InteressadoQualificacao;
        $qualificacao->forceFill(['interessado_id' => $lead->id]);

        return $qualificacao;
    }

    /**
     * @param  list<string>  $chaves
     */
    private function anotar(Interessado $lead, array $chaves, string $estadoNovo, ?int $usuarioId): void
    {
        $dependentes = $this->dependentesDe($lead, true);
        $qualificacao = $this->qualificacaoDe($lead, true) ?? $this->novaQualificacao($lead);
        $campos = $qualificacao->campos ?? [];
        $mudouFamilia = false;
        $agora = now()->toIso8601String();

        foreach ($chaves as $chave) {
            $campo = Roteiro::campo($chave);

            if ($campo === null) {
                continue;
            }

            $anotacao = ['estado' => $estadoNovo, 'fonte' => null, 'evidencia' => null, 'atualizado_em' => $agora, 'atualizado_por' => $usuarioId];

            if ($campo->ehDoAluno()) {
                foreach ($dependentes as $dependente) {
                    $metas = $dependente->qualificacao_meta ?? [];

                    if (filled($dependente->getAttribute($chave)) || ($metas[$chave]['estado'] ?? null) === Roteiro::ESTADO_RECUSOU) {
                        continue;
                    }

                    $metas[$chave] = $anotacao;
                    $dependente->forceFill(['qualificacao_meta' => $metas])->save();
                }

                continue;
            }

            if (filled($qualificacao->getAttribute($chave)) || ($campos[$chave]['estado'] ?? null) === Roteiro::ESTADO_RECUSOU) {
                continue;
            }

            $campos[$chave] = $anotacao;
            $mudouFamilia = true;
        }

        if ($mudouFamilia) {
            $qualificacao->forceFill(['campos' => $campos])->save();
        }

        $this->reavaliar($lead);
    }

    /**
     * Campo que só faz sentido para alguns leads: a escola atual só vale para quem estuda em outra escola.
     *
     * @param  Collection<int, InteressadoDependente>  $dependentes
     */
    private function seAplica(CampoQualificacao $campo, Collection $dependentes): bool
    {
        if ($campo->chave !== 'escola_atual') {
            return true;
        }

        return $dependentes->contains(fn (InteressadoDependente $d): bool => $d->situacao_atual === 'em_outra_escola');
    }

    /**
     * @return array{estado: string, fonte: ?string, evidencia: ?string, atualizado_em: ?string}
     */
    private function estado(string $estado, array $meta = []): array
    {
        return [
            'estado' => $estado,
            'fonte' => $meta['fonte'] ?? null,
            'evidencia' => $meta['evidencia'] ?? null,
            'atualizado_em' => $meta['atualizado_em'] ?? null,
        ];
    }

    /**
     * @return array{estado: string, fonte: ?string, evidencia: ?string, atualizado_em: ?string}
     */
    private function estadoDaFamilia(CampoQualificacao $campo, ?InteressadoQualificacao $qualificacao): array
    {
        $meta = ($qualificacao?->campos ?? [])[$campo->chave] ?? [];

        if (filled($qualificacao?->getAttribute($campo->chave))) {
            return $this->estado(Roteiro::ESTADO_RESPONDIDO, $meta + ['fonte' => InteressadoQualificacao::FONTE_CONSULTOR]);
        }

        $anotado = $meta['estado'] ?? null;

        return $this->estado(
            in_array($anotado, [Roteiro::ESTADO_PERGUNTADO, Roteiro::ESTADO_RECUSOU], true) ? $anotado : Roteiro::ESTADO_NAO_PERGUNTADO,
            $meta,
        );
    }

    /**
     * Campo de aluno: respondido só quando **todos** os alunos têm o valor; senão vale a situação dos que faltam.
     *
     * @param  Collection<int, InteressadoDependente>  $dependentes
     * @return array{estado: string, fonte: ?string, evidencia: ?string, atualizado_em: ?string}
     */
    private function estadoDoAluno(CampoQualificacao $campo, Collection $dependentes): array
    {
        if ($dependentes->isEmpty()) {
            return $this->estado(Roteiro::ESTADO_NAO_PERGUNTADO);
        }

        $faltam = $dependentes->filter(fn (InteressadoDependente $d): bool => blank($d->getAttribute($campo->chave)));

        if ($faltam->isEmpty()) {
            $meta = ($dependentes->first()->qualificacao_meta ?? [])[$campo->chave] ?? [];

            return $this->estado(Roteiro::ESTADO_RESPONDIDO, $meta + ['fonte' => InteressadoQualificacao::FONTE_CONSULTOR]);
        }

        $estados = $faltam->map(fn (InteressadoDependente $d): string => ($d->qualificacao_meta ?? [])[$campo->chave]['estado'] ?? Roteiro::ESTADO_NAO_PERGUNTADO);

        return $this->estado(match (true) {
            $estados->every(fn (string $e): bool => $e === Roteiro::ESTADO_RECUSOU) => Roteiro::ESTADO_RECUSOU,
            $estados->contains(Roteiro::ESTADO_PERGUNTADO) => Roteiro::ESTADO_PERGUNTADO,
            default => Roteiro::ESTADO_NAO_PERGUNTADO,
        });
    }

    /**
     * Valor aceito para o campo, já no formato de gravação, ou null quando não serve (fora da lista, data impossível...).
     */
    private function normalizar(CampoQualificacao $campo, mixed $valor): mixed
    {
        $opcoes = Roteiro::opcoes($campo->chave);

        if ($opcoes !== null) {
            return is_string($valor) && array_key_exists($valor, $opcoes) ? $valor : null;
        }

        switch ($campo->chave) {
            case 'serie_id':
                return is_numeric($valor) && Serie::query()->whereKey((int) $valor)->exists() ? (int) $valor : null;

            case 'data_nascimento':
                try {
                    $data = Carbon::parse((string) $valor)->startOfDay();
                } catch (Throwable) {
                    return null;
                }

                return $data->isFuture() || $data->lt(now()->subYears(25)) ? null : $data->format('Y-m-d');

            case 'escolas_em_comparacao':
                $nomes = collect(is_array($valor) ? $valor : [$valor])
                    ->map(fn ($nome): string => mb_substr(trim((string) $nome), 0, 80))
                    ->filter()
                    ->unique()
                    ->take(5)
                    ->values()
                    ->all();

                return $nomes === [] ? null : $nomes;

            default:
                $texto = mb_substr(trim((string) $valor), 0, 120);

                return $texto === '' ? null : $texto;
        }
    }

    /**
     * Data de corte etário (31/03) do próximo ingresso possível: até 31/03 vale a do ano em curso; depois dela, a do
     * ano seguinte, que é a do ano letivo para o qual a família procura vaga.
     */
    private function dataDeCorteEtario(): Carbon
    {
        $hoje = now();
        $corteDoAno = Carbon::create($hoje->year, self::CORTE_ETARIO_MES, self::CORTE_ETARIO_DIA)->startOfDay();

        return $hoje->copy()->startOfDay()->lte($corteDoAno) ? $corteDoAno : $corteDoAno->copy()->addYear();
    }

    /**
     * O que a família respondeu, em texto legível (rótulo da opção, nome da série, data).
     */
    private function valorLegivel(CampoQualificacao $campo, Interessado $lead): ?string
    {
        $opcoes = Roteiro::opcoes($campo->chave);

        if ($campo->ehDoAluno()) {
            $valores = $lead->dependentes
                ->map(fn (InteressadoDependente $d): ?string => match ($campo->chave) {
                    'serie_id' => $d->serie?->nome,
                    'data_nascimento' => $d->data_nascimento?->format('d/m/Y'),
                    default => filled($d->getAttribute($campo->chave)) ? (string) ($opcoes[$d->getAttribute($campo->chave)] ?? $d->getAttribute($campo->chave)) : null,
                })
                ->filter()
                ->unique()
                ->values();

            return $valores->isEmpty() ? null : $valores->implode(', ');
        }

        $valor = $this->qualificacaoDe($lead)?->getAttribute($campo->chave);

        return match (true) {
            is_array($valor) => implode(', ', $valor),
            $opcoes !== null && is_string($valor) => $opcoes[$valor] ?? $valor,
            default => filled($valor) ? (string) $valor : null,
        };
    }
}
