<?php

declare(strict_types=1);

namespace App\Services\Educacenso;

use App\Enums\SituacaoAlunoCenso;
use App\Exceptions\CensoEscolarException;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\TipoVinculo;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Gera os arquivos da 2ª etapa do Censo Escolar (Situação do Aluno) no leiaute de importação do Inep (Censo 2025,
 * versão 3): texto em ISO-8859-1, campos separados por "|" e uma linha por registro.
 *
 * - Situação do aluno: registro 89 (escola e gestor) seguido dos registros 90 (alunos da matrícula inicial) e 91
 *   (admitidos após a data de referência). Só entram as matrículas sem pendência; as demais ficam em `de_fora`, com o
 *   motivo, para a secretaria corrigir ou informar direto no Educacenso (o Inep aceita arquivo parcial).
 * - Identificação: arquivo de 9 campos para obter o ID Inep de quem ainda não tem.
 */
class SituacaoAlunoCensoExporter
{
    public function __construct(private readonly SituacaoAlunoCensoService $servico) {}

    /**
     * @return array{conteudo: string, arquivo: string, enviados: int, registros_90: int, registros_91: int, de_fora: list<array{aluno: string, motivo: string}>}
     *
     * @throws CensoEscolarException
     */
    public function situacao(Unidade $unidade, PeriodoLetivo $periodo): array
    {
        $escola = $this->servico->escola($unidade);

        if ($escola['pendencias'] !== []) {
            throw new CensoEscolarException(implode(' ', $escola['pendencias']));
        }

        $linhas = $this->servico->apurar($unidade, $periodo);
        $prontas = $linhas->filter(fn (array $l): bool => $l['pendencias'] === [] && $l['registro'] !== null && $l['situacao'] !== null);

        if ($prontas->isEmpty()) {
            throw new CensoEscolarException('Nenhuma matrícula está pronta para o arquivo. Resolva as pendências da lista (códigos do Inep e resultado final) e tente de novo.');
        }

        $registros = [implode('|', ['89', $escola['inep'], $escola['gestor_cpf'], $escola['gestor_nome']])];

        // O registro 90 só pode vir depois de um 89 ou de outro 90; o 91, depois de qualquer um dos três. Todos os 90 e depois os 91 atende.
        foreach ($prontas->where('registro', 90) as $linha) {
            $registros[] = $this->registro90($escola['inep'], $linha);
        }

        foreach ($prontas->where('registro', 91) as $linha) {
            $registros[] = $this->registro91($escola['inep'], $linha);
        }

        activity('censo_escolar')
            ->causedBy(auth()->user())
            ->withProperties(['unidade_id' => $unidade->id, 'periodo_letivo_id' => $periodo->id, 'enviados' => $prontas->count(), 'de_fora' => $linhas->count() - $prontas->count()])
            ->log('Gerou o arquivo da Situação do Aluno do Censo Escolar');

        return [
            'conteudo' => FormatoInep::arquivo($registros),
            'arquivo' => 'SA_'.$escola['inep'].'_'.$this->servico->anoDoCenso($periodo).'.txt',
            'enviados' => $prontas->count(),
            'registros_90' => $prontas->where('registro', 90)->count(),
            'registros_91' => $prontas->where('registro', 91)->count(),
            'de_fora' => $linhas
                ->reject(fn (array $l): bool => $l['pendencias'] === [] && $l['registro'] !== null && $l['situacao'] !== null)
                ->map(fn (array $l): array => ['aluno' => (string) $l['matricula']->pessoa?->nome, 'motivo' => implode(' ', $l['pendencias']) ?: 'Sem situação definida.'])
                ->values()
                ->all(),
        ];
    }

    /**
     * Arquivo de identificação para os alunos da apuração que ainda não têm o ID Inep.
     *
     * @return array{conteudo: string, arquivo: string, enviados: int, de_fora: list<array{aluno: string, motivo: string}>}
     *
     * @throws CensoEscolarException
     */
    public function identificacao(Unidade $unidade, PeriodoLetivo $periodo): array
    {
        $escola = $this->servico->escola($unidade);
        $linhas = $this->servico->apurar($unidade, $periodo)
            ->filter(fn (array $l): bool => strlen(FormatoInep::digitos($l['matricula']->pessoa?->codigo_inep)) !== 12)
            ->unique(fn (array $l): int => (int) $l['matricula']->pessoa_id);

        if ($linhas->isEmpty()) {
            throw new CensoEscolarException('Todos os alunos da lista já têm o ID Inep.');
        }

        EloquentCollection::make($linhas->map(fn (array $l): ?Pessoa => $l['matricula']->pessoa)->filter()->values()->all())
            ->loadMissing(['naturalidade', 'responsaveis']);

        $nomesVinculo = TipoVinculo::query()->pluck('nome', 'id');
        $registros = [];
        $deFora = [];

        foreach ($linhas as $linha) {
            /** @var Pessoa $pessoa */
            $pessoa = $linha['matricula']->pessoa;
            $motivo = $this->semDadosParaIdentificar($pessoa);

            if ($motivo !== null) {
                $deFora[] = ['aluno' => (string) $pessoa->nome, 'motivo' => $motivo];

                continue;
            }

            [$mae, $pai] = $this->filiacao($pessoa, $nomesVinculo);

            $registros[] = implode('|', [
                FormatoInep::codigoLocal($pessoa->id),
                strlen(FormatoInep::digitos($pessoa->cpf)) === 11 ? FormatoInep::digitos($pessoa->cpf) : '',
                '',
                FormatoInep::letras($pessoa->nome),
                Carbon::parse($pessoa->data_nascimento)->format('d/m/Y'),
                FormatoInep::letras($mae),
                FormatoInep::letras($pai),
                FormatoInep::digitos($pessoa->naturalidade->codigo_ibge),
                '',
            ]);
        }

        if ($registros === []) {
            throw new CensoEscolarException('Nenhum aluno tem os dados mínimos para a identificação (nome, data de nascimento e município de nascimento com código IBGE).');
        }

        activity('censo_escolar')
            ->causedBy(auth()->user())
            ->withProperties(['unidade_id' => $unidade->id, 'periodo_letivo_id' => $periodo->id, 'enviados' => count($registros), 'de_fora' => count($deFora)])
            ->log('Gerou o arquivo de identificação de alunos do Censo Escolar');

        return [
            'conteudo' => FormatoInep::arquivo($registros),
            'arquivo' => 'ID_'.($escola['inep'] ?: 'ESCOLA').'_'.$this->servico->anoDoCenso($periodo).'.txt',
            'enviados' => count($registros),
            'de_fora' => $deFora,
        ];
    }

    /**
     * @param  array{matricula: Matricula, situacao: SituacaoAlunoCenso|null}  $linha
     */
    private function registro90(string $inep, array $linha): string
    {
        $matricula = $linha['matricula'];
        $turma = $matricula->turma;

        return implode('|', [
            '90',
            $inep,
            FormatoInep::codigoLocal($turma?->codigo ?: $turma?->id),
            FormatoInep::digitos($turma?->codigo_inep),
            FormatoInep::digitos($matricula->pessoa?->codigo_inep),
            FormatoInep::codigoLocal($matricula->pessoa_id),
            FormatoInep::digitos($matricula->codigo_matricula_inep),
            (string) $linha['situacao']->value,
        ]);
    }

    /**
     * No registro 91 o código da matrícula é nulo; sem o código Inep da turma, o Inep pede a mediação e a etapa.
     *
     * @param  array{matricula: Matricula, situacao: SituacaoAlunoCenso|null, etapa: string}  $linha
     */
    private function registro91(string $inep, array $linha): string
    {
        $matricula = $linha['matricula'];
        $turma = $matricula->turma;
        $codigoTurma = FormatoInep::digitos($turma?->codigo_inep);
        $semTurmaInep = $codigoTurma === '';

        return implode('|', [
            '91',
            $inep,
            FormatoInep::codigoLocal($turma?->codigo ?: $turma?->id),
            $codigoTurma,
            FormatoInep::digitos($matricula->pessoa?->codigo_inep),
            FormatoInep::codigoLocal($matricula->pessoa_id),
            '',
            $semTurmaInep ? (string) min(3, max(1, (int) ($turma?->tipo_mediacao_didatico_pedagogica ?: 1))) : '',
            $semTurmaInep ? $linha['etapa'] : '',
            (string) $linha['situacao']->value,
        ]);
    }

    private function semDadosParaIdentificar(Pessoa $pessoa): ?string
    {
        if (FormatoInep::letras($pessoa->nome) === '') {
            return 'Sem nome.';
        }

        if (! $pessoa->data_nascimento) {
            return 'Sem data de nascimento.';
        }

        if (strlen(FormatoInep::digitos($pessoa->naturalidade?->codigo_ibge)) !== 7) {
            return 'Sem município de nascimento com código IBGE (naturalidade).';
        }

        return null;
    }

    /**
     * @param  Collection<int, string>  $nomesVinculo
     * @return array{0: string, 1: string} mãe (filiação 1) e pai (filiação 2)
     */
    private function filiacao(Pessoa $pessoa, $nomesVinculo): array
    {
        $mae = '';
        $pai = '';

        foreach ($pessoa->responsaveis as $responsavel) {
            $vinculo = mb_strtolower((string) ($nomesVinculo[$responsavel->pivot->tipo_vinculo_id] ?? ''));

            if ($mae === '' && in_array($vinculo, ['mãe', 'mae'], true)) {
                $mae = (string) $responsavel->nome;
            }

            if ($pai === '' && $vinculo === 'pai') {
                $pai = (string) $responsavel->nome;
            }
        }

        return [$mae, $pai];
    }
}
