<?php

namespace Tests\Feature;

use App\Enums\SituacaoFinal;
use App\Models\Disciplina;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\SituacaoFinalDisciplina;
use App\Models\SolicitacaoDocumento;
use App\Models\TemplateDocumento;
use App\Models\Turma;
use App\Models\User;
use App\Services\DocumentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentoServiceHistoricoTest extends TestCase
{
    use RefreshDatabase;

    private function criarSolicitacao(Matricula $matricula): SolicitacaoDocumento
    {
        $template = TemplateDocumento::create([
            'nome' => 'Histórico Escolar Teste',
            'tipo' => 'historico_escolar',
            'conteudo' => '<div>{{TABELA_HISTORICO}}</div>',
            'validade_dias' => 30,
            'is_ativo' => true,
        ]);

        $user = User::factory()->create();

        return SolicitacaoDocumento::create([
            'protocolo' => SolicitacaoDocumento::gerarProtocolo(),
            'matricula_id' => $matricula->id,
            'template_documento_id' => $template->id,
            'solicitado_por_user_id' => $user->id,
            'status' => 'solicitado',
            'codigo_verificacao' => SolicitacaoDocumento::gerarCodigoVerificacao(),
            'data_solicitacao' => now(),
        ]);
    }

    public function test_tabela_historico_usa_situacao_final_real_de_todas_as_matriculas_do_aluno(): void
    {
        $aluno = Pessoa::create(['nome' => 'Aluno Histórico Teste']);

        $periodoAnterior = PeriodoLetivo::create(['nome' => '2025', 'data_inicio' => '2025-01-01', 'data_fim' => '2025-12-31']);
        $periodoAtual = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-01-01', 'data_fim' => '2026-12-31']);

        $turmaAnterior = Turma::create(['nome' => 'Turma 2025', 'periodo_letivo_id' => $periodoAnterior->id]);
        $turmaAtual = Turma::create(['nome' => 'Turma 2026', 'periodo_letivo_id' => $periodoAtual->id]);

        $matematica = Disciplina::create(['nome' => 'Matemática', 'ordem_boletim' => 1]);
        $portugues = Disciplina::create(['nome' => 'Português', 'ordem_boletim' => 2]);

        $matriculaAnterior = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turmaAnterior->id,
            'periodo_letivo_id' => $periodoAnterior->id,
            'situacao' => 'concluido',
        ]);

        $matriculaAtual = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turmaAtual->id,
            'periodo_letivo_id' => $periodoAtual->id,
            'situacao' => 'ativa',
        ]);

        // Ano anterior: aprovado direto em Matemática
        SituacaoFinalDisciplina::create([
            'matricula_id' => $matriculaAnterior->id,
            'disciplina_id' => $matematica->id,
            'periodo_letivo_id' => $periodoAnterior->id,
            'media_final' => 8.5,
            'situacao' => SituacaoFinal::APROVADO,
            'calculado_em' => now(),
        ]);

        // Ano atual: Português ficou em recuperação e foi aprovado no exame final —
        // o histórico deve mostrar o resultado PÓS exame, não a recuperação.
        SituacaoFinalDisciplina::create([
            'matricula_id' => $matriculaAtual->id,
            'disciplina_id' => $portugues->id,
            'periodo_letivo_id' => $periodoAtual->id,
            'media_final' => 6.0,
            'situacao' => SituacaoFinal::RECUPERACAO,
            'nota_exame_final' => 8.0,
            'media_final_pos_exame' => 7.0,
            'situacao_final_pos_exame' => SituacaoFinal::APROVADO,
            'calculado_em' => now(),
        ]);

        $solicitacao = $this->criarSolicitacao($matriculaAtual);

        $html = app(DocumentoService::class)->preencherMacros(
            $solicitacao->templateDocumento,
            $matriculaAtual->fresh(),
            $solicitacao
        );

        // Ano anterior aparece com Matemática Aprovado (8,5)
        $this->assertStringContainsString('Matemática', $html);
        $this->assertStringContainsString('8,5', $html);

        // Ano atual aparece com Português usando o resultado PÓS exame (7,0 / Aprovado), não a recuperação
        $this->assertStringContainsString('Português', $html);
        $this->assertStringContainsString('7,0', $html);
        $this->assertStringContainsString('Aprovado', $html);
        $this->assertStringNotContainsString('Recuperação', $html);
    }

    public function test_tabela_historico_avisa_quando_periodo_ainda_nao_foi_fechado(): void
    {
        $aluno = Pessoa::create(['nome' => 'Aluno Sem Fechamento']);
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-01-01', 'data_fim' => '2026-12-31']);
        $turma = Turma::create(['nome' => 'Turma Sem Fechamento', 'periodo_letivo_id' => $periodo->id]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $periodo->id,
            'situacao' => 'ativa',
        ]);

        $solicitacao = $this->criarSolicitacao($matricula);

        $html = app(DocumentoService::class)->preencherMacros(
            $solicitacao->templateDocumento,
            $matricula->fresh(),
            $solicitacao
        );

        $this->assertStringContainsString('ainda não calculada', $html);
    }
}
