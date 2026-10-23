<?php

declare(strict_types=1);

namespace App\Services\Documentos;

use App\Enums\ContextoDocumento;
use App\Models\CancelamentoMatricula;
use App\Models\ContratoTrabalho;
use App\Models\Fatura;
use App\Models\Funcionario;
use App\Models\ItemProcessoFuncionario;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\ProcessoFuncionario;
use App\Models\SolicitacaoDocumento;
use App\Models\TemplateDocumento;
use App\Models\Unidade;
use App\Services\DocumentoService;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Variáveis dos modelos de documento (Onda 69): o catálogo de cada contexto, o preenchimento e a conferência de variável
 * que não existe.
 *
 * O catálogo é a única lista do sistema. O guia do editor, a validação ao salvar e a prévia leem dele; para o contexto de
 * matrícula, quem preenche é o `DocumentoService` (que já emitia as declarações) e um teste confere que as duas listas
 * não se afastam. Variável escrita errada ou fora do contexto sairia impressa do jeito que está ({{ALUNO_NOME}} sem
 * troca) num documento que alguém vai assinar, por isso o editor recusa.
 */
class MacrosDocumentoService
{
    public const BLOCO_ASSINATURAS = '{{BLOCO_ASSINATURAS}}';

    public function __construct(private readonly DocumentoService $documentos) {}

    /**
     * Variáveis do contexto, por grupo: grupo => [variável => descrição].
     *
     * @return array<string, array<string, string>>
     */
    public function catalogo(ContextoDocumento $contexto): array
    {
        $assinatura = ['Assinatura eletrônica' => [self::BLOCO_ASSINATURAS => 'Linhas de assinatura com o nome e o papel de cada assinante']];

        return match ($contexto) {
            ContextoDocumento::Matricula => [
                'Dados do estudante' => [
                    '{{ALUNO_NOME}}' => 'Nome completo',
                    '{{ALUNO_CPF}}' => 'CPF formatado',
                    '{{ALUNO_RG}}' => 'Identidade / RG',
                    '{{ALUNO_NASCIMENTO}}' => 'Data de nascimento',
                    '{{ALUNO_MAE}}' => 'Nome da mãe',
                    '{{ALUNO_PAI}}' => 'Nome do pai',
                    '{{ALUNO_ENDERECO}}' => 'Endereço residencial completo',
                ],
                'Dados acadêmicos' => [
                    '{{MATRICULA_ID}}' => 'Código da matrícula',
                    '{{SITUACAO_MATRICULA}}' => 'Situação da matrícula',
                    '{{DATA_MATRICULA}}' => 'Data da matrícula',
                    '{{CURSO_NOME}}' => 'Nome do curso',
                    '{{SERIE_NOME}}' => 'Série / ano escolar',
                    '{{TURMA_NOME}}' => 'Turma',
                    '{{TURNO_NOME}}' => 'Turno',
                    '{{HORARIO_AULAS}}' => 'Horário das aulas',
                    '{{PERIODO_LETIVO}}' => 'Período letivo',
                    '{{ANO_LETIVO}}' => 'Ano letivo',
                    '{{ANO_ANTERIOR}}' => 'Ano anterior',
                    '{{STATUS_FINANCEIRO}}' => 'Situação financeira (em dia ou com pendências)',
                    '{{TABELA_HISTORICO}}' => 'Tabela com as disciplinas e o resultado de cada ano',
                ],
                'Escola' => [
                    '{{UNIDADE_NOME}}' => 'Unidade escolar',
                    '{{UNIDADE_CNPJ}}' => 'CNPJ da unidade',
                    '{{UNIDADE_CIDADE}}' => 'Cidade da unidade',
                    '{{UNIDADE_ESTADO}}' => 'Estado da unidade',
                    '{{DATA_EXTENSO}}' => 'Cidade e data por extenso',
                ],
                'Autenticidade (só declaração emitida com QR Code)' => [
                    '{{PROTOCOLO}}' => 'Protocolo do documento',
                    '{{CODIGO_VERIFICACAO}}' => 'Código de verificação',
                    '{{URL_VERIFICACAO}}' => 'Endereço da verificação pública',
                    '{{DATA_EMISSAO}}' => 'Data de emissão',
                    '{{DATA_VALIDADE}}' => 'Data de validade',
                ],
            ] + $assinatura,

            ContextoDocumento::CancelamentoMatricula => [
                'Aluno e contrato' => [
                    '{{ALUNO_NOME}}' => 'Nome do aluno',
                    '{{MATRICULA_ID}}' => 'Código da matrícula',
                    '{{SERIE_NOME}}' => 'Série / ano escolar',
                    '{{TURMA_NOME}}' => 'Turma',
                    '{{PERIODO_LETIVO}}' => 'Período letivo',
                    '{{RESPONSAVEIS}}' => 'Responsáveis financeiros (nomes)',
                    '{{DATA_CONTRATO}}' => 'Data de assinatura do contrato',
                ],
                'Saída e valores' => [
                    '{{DATA_SAIDA}}' => 'Último dia do aluno na escola',
                    '{{VALOR_DEVIDO}}' => 'Valor que a família ainda deve (com multa e juros até a aprovação)',
                    '{{VALOR_A_DEVOLVER}}' => 'Valor que a escola devolve',
                    '{{MULTA_RESCISORIA}}' => 'Multa rescisória (R$ 0,00 quando não há)',
                    '{{PRAZO_MULTA}}' => 'Data limite para pagar a multa rescisória',
                    '{{CLAUSULA_DEVOLUCAO}}' => 'Frase sobre o valor a devolver (ou que não há)',
                    '{{CLAUSULA_MULTA}}' => 'Frase sobre a multa rescisória (ou que não há)',
                    '{{TABELA_FATURAS}}' => 'Tabela com cada fatura e o destino dado a ela',
                ],
                'Escola' => $this->grupoEscola(),
            ] + $assinatura,

            ContextoDocumento::Funcionario => [
                'Dados do funcionário' => [
                    '{{FUNCIONARIO_NOME}}' => 'Nome completo',
                    '{{FUNCIONARIO_CPF}}' => 'CPF formatado',
                    '{{FUNCIONARIO_RG}}' => 'Identidade / RG',
                    '{{FUNCIONARIO_NASCIMENTO}}' => 'Data de nascimento',
                    '{{FUNCIONARIO_ENDERECO}}' => 'Endereço residencial completo',
                ],
                'Contratação' => [
                    '{{CARGO}}' => 'Cargo',
                    '{{REGIME}}' => 'Regime de contratação',
                    '{{DATA_ADMISSAO}}' => 'Data de admissão',
                    '{{CARGA_HORARIA_SEMANAL}}' => 'Carga horária semanal',
                    '{{SALARIO}}' => 'Salário do contrato de trabalho vigente',
                    '{{VIGENCIA_INICIO}}' => 'Início do contrato de trabalho vigente',
                    '{{VIGENCIA_FIM}}' => 'Fim do contrato de trabalho vigente (ou prazo indeterminado)',
                ],
                'Escola' => $this->grupoEscola(),
            ] + $assinatura,
        };
    }

    /**
     * @return array<string, string>
     */
    private function grupoEscola(): array
    {
        return [
            '{{ESCOLA_NOME}}' => 'Nome da instituição de ensino',
            '{{UNIDADE_NOME}}' => 'Unidade escolar',
            '{{UNIDADE_CNPJ}}' => 'CNPJ da unidade',
            '{{UNIDADE_CIDADE}}' => 'Cidade da unidade',
            '{{UNIDADE_ESTADO}}' => 'Estado da unidade',
            '{{DATA_HOJE}}' => 'Data de hoje (dd/mm/aaaa)',
            '{{DATA_EXTENSO}}' => 'Cidade e data por extenso',
        ];
    }

    /**
     * Todas as variáveis do contexto, sem o agrupamento.
     *
     * @return list<string>
     */
    public function variaveisDoContexto(ContextoDocumento $contexto): array
    {
        return array_values(array_merge(...array_map('array_keys', array_values($this->catalogo($contexto)))));
    }

    /**
     * Variáveis que aparecem no texto mas não existem no contexto (inclui as escritas com espaço dentro das chaves, que
     * o preenchimento não reconhece).
     *
     * @return list<string>
     */
    public function desconhecidas(?string $conteudo, ContextoDocumento $contexto): array
    {
        if (blank($conteudo)) {
            return [];
        }

        preg_match_all('/\{\{[^{}<>]*\}\}/u', $conteudo, $encontradas);

        $validas = $this->variaveisDoContexto($contexto);

        return array_values(array_unique(array_filter(
            $encontradas[0],
            fn (string $variavel): bool => ! in_array($variavel, $validas, true),
        )));
    }

    /**
     * O texto do modelo com as variáveis trocadas pelos dados do registro, já limpo de HTML perigoso.
     *
     * @param  list<array{nome: string, papel?: ?string}>  $assinantes  quem vai assinar (para o bloco de assinaturas)
     */
    public function preencher(TemplateDocumento $template, Model $registro, array $assinantes = [], ?SolicitacaoDocumento $solicitacao = null): string
    {
        return $this->substituir((string) $template->conteudo, $template, $registro, $assinantes, $solicitacao);
    }

    /**
     * Troca as variáveis de um trecho do modelo (texto principal, cabeçalho ou rodapé).
     *
     * @param  list<array{nome: string, papel?: ?string}>  $assinantes
     */
    public function substituir(string $trecho, TemplateDocumento $template, Model $registro, array $assinantes = [], ?SolicitacaoDocumento $solicitacao = null): string
    {
        $valores = $this->valores($template->contexto(), $registro, $template, $assinantes, $solicitacao);

        return HtmlSanitizer::clean(str_replace(array_keys($valores), array_values($valores), $trecho));
    }

    /**
     * Nome curto do registro para título de documento e listas: o aluno ou o funcionário.
     */
    public function rotulo(Model $registro): string
    {
        return match (true) {
            $registro instanceof Matricula => Matricula::query()->with('pessoa')->find($registro->id)?->pessoa?->nome ?? 'Aluno',
            $registro instanceof CancelamentoMatricula => Matricula::query()->with('pessoa')->find($registro->matricula_id)?->pessoa?->nome ?? 'Aluno',
            $registro instanceof Funcionario => Funcionario::query()->with('pessoa')->find($registro->id)?->pessoa?->nome ?? 'Funcionário',
            default => class_basename($registro),
        };
    }

    /**
     * Valor de cada variável do contexto para o registro.
     *
     * @param  list<array{nome: string, papel?: ?string}>  $assinantes
     * @return array<string, string>
     */
    public function valores(ContextoDocumento $contexto, Model $registro, ?TemplateDocumento $template = null, array $assinantes = [], ?SolicitacaoDocumento $solicitacao = null): array
    {
        if ($contexto->modelo() !== $registro::class) {
            throw new \InvalidArgumentException('O registro '.$registro::class." não é do contexto {$contexto->value}.");
        }

        $valores = match (true) {
            $registro instanceof Matricula => $this->documentos->variaveis($registro, $solicitacao, $template),
            $registro instanceof CancelamentoMatricula => $this->valoresDoCancelamento($registro),
            $registro instanceof Funcionario => $this->valoresDoFuncionario($registro),
            default => throw new \InvalidArgumentException('Registro sem variáveis de documento: '.$registro::class),
        };

        $valores[self::BLOCO_ASSINATURAS] = $this->blocoAssinaturas($assinantes);

        return $valores;
    }

    /**
     * O registro que alimenta as variáveis do contexto, a partir do registro a que o documento está ligado: o item de um
     * processo de RH vale pelo funcionário do processo.
     */
    public function registroDoContexto(Model $documentavel, ContextoDocumento $contexto): ?Model
    {
        if ($documentavel instanceof ItemProcessoFuncionario) {
            $funcionarioId = ProcessoFuncionario::query()->whereKey($documentavel->processo_id)->value('funcionario_id');
            $documentavel = $funcionarioId ? Funcionario::query()->find($funcionarioId) : null;
        }

        $modelo = $contexto->modelo();

        return $documentavel instanceof $modelo ? $documentavel : null;
    }

    /**
     * Linhas de assinatura para imprimir no documento. Sem assinantes (prévia), mostra duas linhas de exemplo.
     *
     * @param  list<array{nome: string, papel?: ?string}>  $assinantes
     */
    public function blocoAssinaturas(array $assinantes): string
    {
        $assinantes = $assinantes !== [] ? $assinantes : [['nome' => 'Nome do primeiro assinante', 'papel' => 'Papel'], ['nome' => 'Nome do segundo assinante', 'papel' => 'Papel']];

        $linhas = '';

        // Duas assinaturas por linha.
        foreach (array_chunk($assinantes, 2) as $par) {
            $linhas .= '<tr>';

            foreach ($par as $assinante) {
                $linhas .= '<td style="width: 50%; padding: 26px 10px 0; text-align: center; vertical-align: top;">'
                    .'<div style="border-top: 1px solid #111; padding-top: 3px;">'.e($assinante['nome']).'</div>'
                    .(filled($assinante['papel'] ?? null) ? '<div style="font-size: 10px; color: #555;">'.e((string) $assinante['papel']).'</div>' : '')
                    .'</td>';
            }

            $linhas .= '</tr>';
        }

        return '<table style="width: 100%; border-collapse: collapse; margin-top: 18px;">'.$linhas.'</table>';
    }

    // --- Cancelamento de matrícula -----------------------------------------------------------------------------------

    /**
     * @return array<string, string>
     */
    private function valoresDoCancelamento(CancelamentoMatricula $cancelamento): array
    {
        $matricula = Matricula::query()
            ->with(['pessoa', 'turma.serie.curso.unidade.instituicaoEnsino', 'turma.serie.curso.unidade.endereco.cidade.estado', 'turma.periodoLetivo', 'contrato.responsaveisFinanceiros.pessoa'])
            ->findOrFail($cancelamento->matricula_id);

        $unidade = $matricula->turma?->serie?->curso?->unidade;
        $responsaveis = $matricula->contrato?->responsaveisFinanceiros->map(fn ($r) => $r->pessoa?->nome)->filter()->implode(', ');
        $prazoMulta = today()->addDays((int) config('cancelamento.multa_vencimento_dias', 10));

        return [
            '{{ALUNO_NOME}}' => e($matricula->pessoa?->nome ?? '—'),
            '{{MATRICULA_ID}}' => (string) $matricula->id,
            '{{SERIE_NOME}}' => e($matricula->turma?->serie?->nome ?? '—'),
            '{{TURMA_NOME}}' => e($matricula->turma?->nome ?? '—'),
            '{{PERIODO_LETIVO}}' => e($matricula->turma?->periodoLetivo?->nome ?? '—'),
            '{{RESPONSAVEIS}}' => e($responsaveis ?: 'responsável financeiro do aluno'),
            '{{DATA_CONTRATO}}' => $matricula->contrato?->data_aceite ? Carbon::parse($matricula->contrato->data_aceite)->format('d/m/Y') : '—',
            '{{DATA_SAIDA}}' => $cancelamento->data_efetiva->format('d/m/Y'),
            '{{VALOR_DEVIDO}}' => $this->moeda((float) $cancelamento->valor_devido),
            '{{VALOR_A_DEVOLVER}}' => $this->moeda((float) $cancelamento->valor_a_devolver),
            '{{MULTA_RESCISORIA}}' => $this->moeda((float) $cancelamento->multa_rescisoria),
            '{{PRAZO_MULTA}}' => $prazoMulta->format('d/m/Y'),
            '{{CLAUSULA_DEVOLUCAO}}' => (float) $cancelamento->valor_a_devolver > 0
                ? 'Valor a ser devolvido pela CONTRATADA, referente a parcelas pagas antecipadamente e canceladas: <strong>'.$this->moeda((float) $cancelamento->valor_a_devolver).'</strong>.'
                : 'Não há valores a devolver.',
            '{{CLAUSULA_MULTA}}' => (float) $cancelamento->multa_rescisoria > 0
                ? 'Multa rescisória prevista no contrato: <strong>'.$this->moeda((float) $cancelamento->multa_rescisoria).'</strong>, com vencimento em até '.$prazoMulta->format('d/m/Y').'.'
                : 'Não há multa rescisória.',
            '{{TABELA_FATURAS}}' => $this->tabelaDeFaturasDoDistrato($cancelamento),
        ] + $this->valoresDaEscola($unidade);
    }

    /**
     * Cada fatura do distrato e o destino dado a ela, lidas pelas decisões gravadas (continua certo depois que as faturas
     * canceladas somem das listas).
     */
    private function tabelaDeFaturasDoDistrato(CancelamentoMatricula $cancelamento): string
    {
        $decisoes = $cancelamento->decisoes ?? [];

        if ($decisoes === []) {
            return '<p><em>Não há faturas vinculadas a este contrato.</em></p>';
        }

        $destinos = ['manter' => 'Mantida', 'cancelar' => 'Cancelada', 'devolver' => 'Cancelada, com devolução do valor pago'];
        $linhas = '';

        foreach (Fatura::query()->whereIn('id', array_keys($decisoes))->with(['itens', 'transacoes'])->orderBy('vencimento')->get() as $fatura) {
            $descricao = $fatura->itens->pluck('descricao')->filter()->implode(' + ') ?: 'Fatura #'.$fatura->id;
            $linhas .= '<tr>'
                .'<td style="border: 1px solid #bbb; padding: 3px 5px;">'.e($descricao).'</td>'
                .'<td style="border: 1px solid #bbb; padding: 3px 5px;">'.$fatura->vencimento->format('d/m/Y').'</td>'
                .'<td style="border: 1px solid #bbb; padding: 3px 5px; text-align: right;">'.$this->moeda((float) $fatura->valor).'</td>'
                .'<td style="border: 1px solid #bbb; padding: 3px 5px; text-align: right;">'.$this->moeda((float) $fatura->valor_pago).'</td>'
                .'<td style="border: 1px solid #bbb; padding: 3px 5px;">'.e($destinos[$decisoes[$fatura->id] ?? 'manter'] ?? 'Mantida').'</td>'
                .'</tr>';
        }

        return '<table style="width: 100%; border-collapse: collapse; font-size: 10px;"><thead><tr>'
            .'<th style="border: 1px solid #bbb; padding: 3px 5px; background: #f3f4f6; text-align: left;">Fatura</th>'
            .'<th style="border: 1px solid #bbb; padding: 3px 5px; background: #f3f4f6; text-align: left;">Vencimento</th>'
            .'<th style="border: 1px solid #bbb; padding: 3px 5px; background: #f3f4f6; text-align: right;">Valor</th>'
            .'<th style="border: 1px solid #bbb; padding: 3px 5px; background: #f3f4f6; text-align: right;">Pago</th>'
            .'<th style="border: 1px solid #bbb; padding: 3px 5px; background: #f3f4f6; text-align: left;">Destino</th>'
            .'</tr></thead><tbody>'.$linhas.'</tbody></table>';
    }

    // --- Funcionário -------------------------------------------------------------------------------------------------

    /**
     * @return array<string, string>
     */
    private function valoresDoFuncionario(Funcionario $funcionario): array
    {
        $funcionario = Funcionario::query()
            ->with(['pessoa.enderecos.cidade.estado', 'unidade.instituicaoEnsino', 'unidade.endereco.cidade.estado'])
            ->findOrFail($funcionario->id);

        $pessoa = $funcionario->pessoa;
        $contrato = ContratoTrabalho::query()->where('funcionario_id', $funcionario->id)->orderByDesc('vigencia_inicio')->first();

        return [
            '{{FUNCIONARIO_NOME}}' => e($pessoa?->nome ?? '—'),
            '{{FUNCIONARIO_CPF}}' => $this->cpf($pessoa),
            '{{FUNCIONARIO_RG}}' => e($pessoa?->identidade ?: 'Não informado'),
            '{{FUNCIONARIO_NASCIMENTO}}' => $pessoa?->data_nascimento ? Carbon::parse($pessoa->data_nascimento)->format('d/m/Y') : '—',
            '{{FUNCIONARIO_ENDERECO}}' => e($this->endereco($pessoa)),
            '{{CARGO}}' => e($funcionario->cargo),
            '{{REGIME}}' => e($funcionario->regime?->getLabel() ?? '—'),
            '{{DATA_ADMISSAO}}' => $funcionario->data_admissao->format('d/m/Y'),
            '{{CARGA_HORARIA_SEMANAL}}' => $funcionario->carga_horaria_semanal ? $funcionario->carga_horaria_semanal.' horas semanais' : '—',
            '{{SALARIO}}' => $contrato ? $this->moeda((float) $contrato->salario) : '—',
            '{{VIGENCIA_INICIO}}' => $contrato ? $contrato->vigencia_inicio->format('d/m/Y') : '—',
            '{{VIGENCIA_FIM}}' => $contrato?->vigencia_fim ? $contrato->vigencia_fim->format('d/m/Y') : 'prazo indeterminado',
        ] + $this->valoresDaEscola($funcionario->unidade);
    }

    // --- Apoio -------------------------------------------------------------------------------------------------------

    /**
     * @return array<string, string>
     */
    private function valoresDaEscola(?Unidade $unidade): array
    {
        $cidade = $unidade?->endereco?->cidade?->nome ?? 'Esta Cidade';

        return [
            '{{ESCOLA_NOME}}' => e($unidade?->instituicaoEnsino?->nome ?? $unidade?->nome ?? config('app.name')),
            '{{UNIDADE_NOME}}' => e($unidade?->nome ?? config('app.name')),
            '{{UNIDADE_CNPJ}}' => e((string) ($unidade?->cnpj ?? '')),
            '{{UNIDADE_CIDADE}}' => e($cidade),
            '{{UNIDADE_ESTADO}}' => e((string) ($unidade?->endereco?->cidade?->estado?->sigla ?? '')),
            '{{DATA_HOJE}}' => now()->format('d/m/Y'),
            '{{DATA_EXTENSO}}' => e($cidade).', '.now()->translatedFormat('d \d\e F \d\e Y'),
        ];
    }

    private function moeda(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }

    private function cpf(?Pessoa $pessoa): string
    {
        $cpf = (string) ($pessoa?->cpf ?? '');

        if ($cpf === '') {
            return 'Não informado';
        }

        return strlen($cpf) === 11 ? (string) preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf) : $cpf;
    }

    private function endereco(?Pessoa $pessoa): string
    {
        $endereco = $pessoa?->enderecos->first();

        if (! $endereco) {
            return 'Não informado';
        }

        return trim("{$endereco->logradouro}, {$endereco->numero} ".($endereco->complemento ? "- {$endereco->complemento} " : '')."- {$endereco->bairro}, {$endereco->cidade?->nome}/{$endereco->cidade?->estado?->sigla} - CEP: {$endereco->cep}");
    }
}
