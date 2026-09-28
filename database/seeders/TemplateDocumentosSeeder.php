<?php

namespace Database\Seeders;

use App\Enums\TipoTemplateDocumento;
use App\Models\TemplateDocumento;
use Illuminate\Database\Seeder;

class TemplateDocumentosSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'nome' => 'Declaração de Matrícula Regular',
                'tipo' => TipoTemplateDocumento::DeclaracaoMatricula,
                'descricao' => 'Atesta que o estudante encontra-se regularmente matriculado e frequente.',
                'conteudo' => '<p>Declaramos, para os devidos fins de direito e a pedido da parte interessada, que o(a) estudante <strong>{{ALUNO_NOME}}</strong>, portador(a) do CPF nº <strong>{{ALUNO_CPF}}</strong> e RG nº <strong>{{ALUNO_RG}}</strong>, nascido(a) em <strong>{{ALUNO_NASCIMENTO}}</strong>, filho(a) de <strong>{{ALUNO_MAE}}</strong> e <strong>{{ALUNO_PAI}}</strong>, encontra-se regularmente matriculado(a) nesta instituição de ensino sob a Matrícula nº <strong>{{MATRICULA_ID}}</strong>, cursando o(a) <strong>{{SERIE_NOME}}</strong> do <strong>{{CURSO_NOME}}</strong>, Turma <strong>{{TURMA_NOME}}</strong>, Turno <strong>{{TURNO_NOME}}</strong>, no Período Letivo de <strong>{{PERIODO_LETIVO}}</strong>.</p><p>Por ser verdade, firmamos o presente documento para que produza seus regulares efeitos de direito.</p>',
                'validade_dias' => 30,
                'is_ativo' => true,
            ],
            [
                'nome' => 'Declaração de Frequência Escolar',
                'tipo' => TipoTemplateDocumento::DeclaracaoFrequencia,
                'descricao' => 'Comprova a frequência e assiduidade do estudante nas aulas do período letivo.',
                'conteudo' => '<p>Declaramos para os devidos fins que o(a) estudante <strong>{{ALUNO_NOME}}</strong>, portador(a) do CPF nº <strong>{{ALUNO_CPF}}</strong>, devidamente matriculado(a) no <strong>{{SERIE_NOME}}</strong> do <strong>{{CURSO_NOME}}</strong> (Turma {{TURMA_NOME}}), sob Matrícula nº <strong>{{MATRICULA_ID}}</strong>, vem cumprindo assiduamente a carga horária curricular do ano letivo de <strong>{{PERIODO_LETIVO}}</strong>, mantendo frequência regular de acordo com as normas regimentais da escola e as diretrizes da Lei de Diretrizes e Bases da Educação Nacional (LDB).</p><p>O referido estudante não possui impedimentos disciplinares ou frequência insuficiente até a presente data.</p>',
                'validade_dias' => 30,
                'is_ativo' => true,
            ],
            [
                'nome' => 'Declaração de Quitação de Débitos',
                'tipo' => TipoTemplateDocumento::DeclaracaoQuitacao,
                'descricao' => 'Atesta a adimplência financeira integral do estudante/responsável perante a instituição.',
                'conteudo' => '<p>Declaramos para os fins do disposto na Lei Federal nº 12.007/2009 que o(a) estudante <strong>{{ALUNO_NOME}}</strong>, inscrito(a) no CPF nº <strong>{{ALUNO_CPF}}</strong>, sob a Matrícula nº <strong>{{MATRICULA_ID}}</strong>, não possui quaisquer débitos ou pendências financeiras em aberto relativos às parcelas de anuidade/mensalidades escolares até o presente momento nesta instituição.</p><p>Damos a mais ampla, geral e irrevogável quitação pelas obrigações financeiras do período contratado.</p>',
                'validade_dias' => 60,
                'is_ativo' => true,
            ],
            [
                'nome' => 'Histórico Escolar Simplificado',
                'tipo' => TipoTemplateDocumento::HistoricoEscolar,
                'descricao' => 'Espelho do percurso acadêmico e componentes curriculares cursados pelo estudante.',
                'conteudo' => '<p>Certificamos que o(a) estudante <strong>{{ALUNO_NOME}}</strong>, nascido(a) em <strong>{{ALUNO_NASCIMENTO}}</strong>, natural de <strong>{{ALUNO_ENDERECO}}</strong>, portador(a) da cédula de identidade RG nº <strong>{{ALUNO_RG}}</strong> e CPF nº <strong>{{ALUNO_CPF}}</strong>, cursou nesta instituição a série <strong>{{SERIE_NOME}}</strong> do curso <strong>{{CURSO_NOME}}</strong>, tendo obtido os registros pedagógicos abaixo relacionados:</p>{{TABELA_HISTORICO}}<p>O presente histórico é emitido para efeito de comprovação acadêmica e transferência.</p>',
                'validade_dias' => 90,
                'is_ativo' => true,
            ],
        ];

        foreach ($templates as $tpl) {
            TemplateDocumento::firstOrCreate(
                ['nome' => $tpl['nome']],
                $tpl
            );
        }
    }
}
