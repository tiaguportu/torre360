<?php

declare(strict_types=1);

namespace App\Services\Documentos;

use App\Enums\TipoTemplateDocumento;
use App\Models\TemplateDocumento;

/**
 * Textos de partida dos modelos que vão para assinatura (Onda 69): distrato de matrícula, contrato de trabalho e termo de
 * confidencialidade. São rascunhos para a escola ajustar com o jurídico e o contador: nascem **inativos**, e o modelo só
 * passa a valer depois que alguém lê, edita e ativa.
 */
class ModelosPadraoDocumento
{
    /**
     * @return list<array{tipo: TipoTemplateDocumento, nome: string, descricao: string, conteudo: string}>
     */
    public function modelos(): array
    {
        return [
            [
                'tipo' => TipoTemplateDocumento::DistratoMatricula,
                'nome' => 'Distrato de Matrícula',
                'descricao' => 'Modelo de partida. Revise com o jurídico antes de ativar. Depois de ativo, é este texto que sai no PDF do distrato e na assinatura eletrônica.',
                'conteudo' => $this->distrato(),
            ],
            [
                'tipo' => TipoTemplateDocumento::ContratoTrabalho,
                'nome' => 'Contrato Individual de Trabalho',
                'descricao' => 'Modelo de partida para contratação pela CLT. Confira com o contador e o jurídico (piso da categoria, jornada de professor, contrato de experiência) antes de ativar.',
                'conteudo' => $this->contratoDeTrabalho(),
            ],
            [
                'tipo' => TipoTemplateDocumento::TermoConfidencialidade,
                'nome' => 'Termo de Confidencialidade e Proteção de Dados',
                'descricao' => 'Modelo de partida para a admissão (LGPD, Lei nº 13.709/2018). Revise com o jurídico antes de ativar.',
                'conteudo' => $this->termoDeConfidencialidade(),
            ],
        ];
    }

    /**
     * Cria o que ainda não existe (um modelo por tipo), inativo. Não mexe em tipo que já tem modelo.
     *
     * @return int quantos modelos foram criados
     */
    public function criarFaltantes(): int
    {
        $criados = 0;

        foreach ($this->modelos() as $modelo) {
            if (TemplateDocumento::query()->where('tipo', $modelo['tipo']->value)->exists()) {
                continue;
            }

            TemplateDocumento::create($modelo + ['validade_dias' => 0, 'is_ativo' => false]);
            $criados++;
        }

        return $criados;
    }

    private function distrato(): string
    {
        return <<<'HTML'
<h2 style="text-align: center; text-transform: uppercase;">Distrato de contrato de prestação de serviços educacionais</h2>
<p><strong>CONTRATADA:</strong> {{ESCOLA_NOME}}, unidade {{UNIDADE_NOME}}, inscrita no CNPJ sob o nº {{UNIDADE_CNPJ}}, em {{UNIDADE_CIDADE}}/{{UNIDADE_ESTADO}}.</p>
<p><strong>CONTRATANTE(S):</strong> {{RESPONSAVEIS}}.</p>
<p><strong>ALUNO(A):</strong> {{ALUNO_NOME}}, turma {{TURMA_NOME}} ({{SERIE_NOME}}), período letivo {{PERIODO_LETIVO}}.</p>

<h3>1. Do objeto</h3>
<p>As partes resolvem, de comum acordo, rescindir o contrato de prestação de serviços educacionais firmado em {{DATA_CONTRATO}}, referente à matrícula acima, com efeito a partir de <strong>{{DATA_SAIDA}}</strong>.</p>

<h3>2. Das obrigações até a saída</h3>
<p>Permanecem devidas as parcelas com vencimento até o último dia do mês da saída, inclusive. O mês da saída é cobrado integralmente. A situação de cada fatura na data de aprovação deste distrato é a seguinte:</p>
{{TABELA_FATURAS}}

<h3>3. Dos valores</h3>
<p>Valor devido pela CONTRATANTE nesta data, incluídos a multa de mora e os juros calculados até a aprovação deste distrato: <strong>{{VALOR_DEVIDO}}</strong>. Multa e juros continuam correndo sobre o que estiver vencido e não for pago.</p>
<p>{{CLAUSULA_DEVOLUCAO}}</p>
<p>{{CLAUSULA_MULTA}}</p>

<h3>4. Da quitação</h3>
<p>Cumpridas as obrigações acima, as partes dão-se mútua quitação quanto ao contrato rescindido, nada mais tendo a reclamar uma da outra a esse título.</p>

<h3>5. Dos documentos escolares</h3>
<p>A CONTRATADA entregará os documentos de transferência e demais documentos escolares do(a) aluno(a) quando solicitados, independentemente de a CONTRATANTE estar adimplente, conforme o art. 6º, § 2º, da Lei nº 9.870/1999.</p>

<p style="margin-top: 14px;">{{DATA_EXTENSO}}.</p>
{{BLOCO_ASSINATURAS}}
HTML;
    }

    private function contratoDeTrabalho(): string
    {
        return <<<'HTML'
<h2 style="text-align: center; text-transform: uppercase;">Contrato individual de trabalho</h2>
<p><strong>EMPREGADORA:</strong> {{ESCOLA_NOME}}, unidade {{UNIDADE_NOME}}, inscrita no CNPJ sob o nº {{UNIDADE_CNPJ}}, em {{UNIDADE_CIDADE}}/{{UNIDADE_ESTADO}}.</p>
<p><strong>EMPREGADO(A):</strong> {{FUNCIONARIO_NOME}}, nascido(a) em {{FUNCIONARIO_NASCIMENTO}}, CPF nº {{FUNCIONARIO_CPF}}, RG nº {{FUNCIONARIO_RG}}, residente em {{FUNCIONARIO_ENDERECO}}.</p>

<h3>Cláusula 1ª — Da função</h3>
<p>O(A) EMPREGADO(A) é contratado(a) para exercer a função de <strong>{{CARGO}}</strong>, sob o regime {{REGIME}}, e executará as tarefas inerentes à função e as que lhe forem determinadas, compatíveis com a sua condição pessoal.</p>

<h3>Cláusula 2ª — Do início e do prazo</h3>
<p>O contrato tem início em <strong>{{DATA_ADMISSAO}}</strong>. Vigência do contrato de trabalho: de {{VIGENCIA_INICIO}} até {{VIGENCIA_FIM}}.</p>

<h3>Cláusula 3ª — Da jornada</h3>
<p>A carga horária contratada é de {{CARGA_HORARIA_SEMANAL}}, distribuída conforme o horário informado pela EMPREGADORA, respeitados os limites e os intervalos da legislação. O registro de ponto segue as regras da EMPREGADORA.</p>

<h3>Cláusula 4ª — Da remuneração</h3>
<p>O(A) EMPREGADO(A) receberá o salário mensal de <strong>{{SALARIO}}</strong>, pago até o quinto dia útil do mês subsequente ao vencido (CLT, art. 459, § 1º), com os descontos autorizados em lei.</p>

<h3>Cláusula 5ª — Dos deveres</h3>
<p>O(A) EMPREGADO(A) obriga-se a cumprir o regimento da escola, zelar pelos alunos e pelos bens da EMPREGADORA e guardar sigilo sobre os dados pessoais a que tiver acesso, conforme o Termo de Confidencialidade e Proteção de Dados que integra este contrato.</p>

<h3>Cláusula 6ª — Da rescisão</h3>
<p>A rescisão observará a Consolidação das Leis do Trabalho, inclusive quanto ao aviso prévio e às verbas rescisórias.</p>

<h3>Cláusula 7ª — Do foro</h3>
<p>Fica eleito o foro da comarca de {{UNIDADE_CIDADE}} para dirimir dúvidas deste contrato.</p>

<p style="margin-top: 14px;">{{DATA_EXTENSO}}.</p>
{{BLOCO_ASSINATURAS}}
HTML;
    }

    private function termoDeConfidencialidade(): string
    {
        return <<<'HTML'
<h2 style="text-align: center; text-transform: uppercase;">Termo de confidencialidade e proteção de dados pessoais</h2>
<p>Pelo presente termo, <strong>{{FUNCIONARIO_NOME}}</strong>, CPF nº {{FUNCIONARIO_CPF}}, ocupante do cargo de {{CARGO}} na {{ESCOLA_NOME}} (unidade {{UNIDADE_NOME}}), declara estar ciente e de acordo com o que segue.</p>

<h3>1. Do objeto</h3>
<p>Este termo trata do sigilo e do uso adequado dos dados pessoais de alunos, famílias, colegas e candidatos a que o(a) declarante tiver acesso em razão da função, em cumprimento à Lei nº 13.709/2018 (Lei Geral de Proteção de Dados).</p>

<h3>2. Das obrigações</h3>
<ul>
<li>usar os dados somente para a finalidade da sua função;</li>
<li>não copiar, fotografar, enviar nem compartilhar dados fora dos canais da escola, nem por aplicativos pessoais;</li>
<li>usar imagens de alunos somente conforme a autorização das famílias e a política de imagem da escola;</li>
<li>não compartilhar a senha de acesso ao sistema e sair da conta ao terminar;</li>
<li>comunicar de imediato à direção qualquer perda, vazamento ou acesso indevido de que tomar conhecimento.</li>
</ul>

<h3>3. Da vigência</h3>
<p>As obrigações valem durante o contrato de trabalho e continuam depois do seu término, enquanto os dados forem sigilosos.</p>

<h3>4. Das consequências</h3>
<p>O descumprimento sujeita o(a) declarante às medidas disciplinares cabíveis e à responsabilização civil e legal pelos danos causados.</p>

<p style="margin-top: 14px;">{{DATA_EXTENSO}}.</p>
{{BLOCO_ASSINATURAS}}
HTML;
    }
}
