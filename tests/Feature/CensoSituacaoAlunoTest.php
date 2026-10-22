<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoAlunoCenso;
use App\Exceptions\CensoEscolarException;
use App\Models\CensoSituacaoAluno;
use App\Models\Cidade;
use App\Models\Estado;
use App\Models\Matricula;
use App\Models\Pais;
use App\Models\Pessoa;
use App\Models\TipoVinculo;
use App\Models\TransferenciaEscolar;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Educacenso\EducacensoRetornoImporter;
use App\Services\Educacenso\FormatoInep;
use App\Services\Educacenso\SituacaoAlunoCensoExporter;
use App\Services\Educacenso\SituacaoAlunoCensoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\MontaEscolaDoCenso;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Censo Escolar, 2ª etapa (Onda 58): apuração da situação do aluno, arquivos de importação do Educacenso (registros
 * 89/90/91 e identificação) e importação do retorno com os códigos do Inep.
 *
 * Escola "Sede" (Inep 12345678), período 2026 (data de referência: 27/05/2026, última quarta-feira de maio). "Hoje" é
 * 21/10/2026.
 */
class CensoSituacaoAlunoTest extends TestCase
{
    use MontaEscolaDoCenso;
    use ProibeLazyLoading;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));
        $this->montarEscolaDoCenso();
    }

    private function servico(): SituacaoAlunoCensoService
    {
        return app(SituacaoAlunoCensoService::class);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function linha(Matricula $matricula): ?array
    {
        return $this->servico()->apurar($this->unidade, $this->periodo)->first(fn (array $l): bool => $l['matricula']->id === $matricula->id);
    }

    public function test_data_de_referencia_e_a_ultima_quarta_feira_de_maio(): void
    {
        $this->assertSame('2026-05-27', $this->servico()->dataReferencia(2026)->toDateString());
        $this->assertSame('2025-05-28', $this->servico()->dataReferencia(2025)->toDateString());
        $this->assertSame('2023-05-31', $this->servico()->dataReferencia(2023)->toDateString());

        config(['educacenso.data_referencia.2026' => '2026-05-20']);

        $this->assertSame('2026-05-20', $this->servico()->dataReferencia(2026)->toDateString());
    }

    public function test_aprovado_em_todas_as_disciplinas(): void
    {
        $matricula = $this->aluno($this->turma());
        $this->resultado($matricula, 'aprovado');
        $this->resultado($matricula, 'aprovado');

        $linha = $this->linha($matricula);

        $this->assertSame(SituacaoAlunoCenso::Aprovado, $linha['situacao']);
        $this->assertSame(90, $linha['registro']);
        $this->assertSame([], $linha['pendencias']);
    }

    public function test_aprovado_na_etapa_final_vira_concluinte(): void
    {
        $matricula = $this->aluno($this->turma('9º A', '41'));
        $this->resultado($matricula, 'aprovado');

        $this->assertSame(SituacaoAlunoCenso::AprovadoConcluinte, $this->linha($matricula)['situacao']);
    }

    public function test_reprovado_em_uma_disciplina(): void
    {
        $matricula = $this->aluno($this->turma());
        $this->resultado($matricula, 'aprovado');
        $this->resultado($matricula, 'reprovado');

        $linha = $this->linha($matricula);

        $this->assertSame(SituacaoAlunoCenso::Reprovado, $linha['situacao']);
        $this->assertStringContainsString('1 disciplina', $linha['motivo']);
    }

    public function test_resultado_do_exame_final_prevalece_sobre_o_resultado_antes_do_exame(): void
    {
        $matricula = $this->aluno($this->turma());
        $this->resultado($matricula, 'reprovado', 'aprovado');

        $this->assertSame(SituacaoAlunoCenso::Aprovado, $this->linha($matricula)['situacao']);
    }

    public function test_recuperacao_em_aberto_e_pendencia_sem_situacao(): void
    {
        $matricula = $this->aluno($this->turma());
        $this->resultado($matricula, 'recuperacao');

        $linha = $this->linha($matricula);

        $this->assertNull($linha['situacao']);
        $this->assertStringContainsString('em aberto', implode(' ', $linha['pendencias']));
    }

    public function test_sem_resultado_final_pede_o_fechamento_do_ano(): void
    {
        $linha = $this->linha($this->aluno($this->turma()));

        $this->assertNull($linha['situacao']);
        $this->assertStringContainsString('Resultado final não calculado', implode(' ', $linha['pendencias']));
    }

    public function test_educacao_infantil_fica_sem_movimentacao(): void
    {
        $matricula = $this->aluno($this->turma('Maternal', '2'));

        $linha = $this->linha($matricula);

        $this->assertSame(SituacaoAlunoCenso::SemMovimentacao, $linha['situacao']);
        $this->assertSame([], $linha['pendencias']);
    }

    public function test_transferencia_de_saida_concluida_vira_transferido(): void
    {
        $matricula = $this->aluno($this->turma(), matricula: ['situacao' => 'cancelada', 'data_desativacao' => '2026-08-10']);
        TransferenciaEscolar::factory()->create(['matricula_id' => $matricula->id, 'status' => 'concluida', 'data' => '2026-08-10']);

        $linha = $this->linha($matricula);

        $this->assertSame(SituacaoAlunoCenso::Transferido, $linha['situacao']);
        $this->assertStringContainsString('10/08/2026', $linha['motivo']);
    }

    public function test_transferencia_em_andamento_ou_de_entrada_nao_conta(): void
    {
        $matricula = $this->aluno($this->turma());
        $this->resultado($matricula, 'aprovado');
        TransferenciaEscolar::factory()->create(['matricula_id' => $matricula->id, 'status' => 'em_andamento']);
        TransferenciaEscolar::factory()->create(['matricula_id' => $matricula->id, 'tipo' => 'entrada', 'status' => 'concluida']);

        $this->assertSame(SituacaoAlunoCenso::Aprovado, $this->linha($matricula)['situacao']);
    }

    public function test_evasao_e_cancelamento_sem_transferencia_deixaram_de_frequentar(): void
    {
        $evasao = $this->aluno($this->turma(), 'Bia', matricula: ['situacao' => 'evasao', 'data_desativacao' => '2026-09-01']);
        $cancelada = $this->aluno($this->turma('Turma 102', codigoInep: '9002', codigo: 'T102'), 'Caio', matricula: ['situacao' => 'cancelada', 'data_desativacao' => '2026-09-02']);

        $this->assertSame(SituacaoAlunoCenso::DeixouDeFrequentar, $this->linha($evasao)['situacao']);

        $linhaCancelada = $this->linha($cancelada);
        $this->assertSame(SituacaoAlunoCenso::DeixouDeFrequentar, $linhaCancelada['situacao']);
        $this->assertStringContainsString('sem transferência registrada', implode(' ', $linhaCancelada['avisos']));
    }

    public function test_quem_saiu_antes_da_data_de_referencia_sem_codigo_do_inep_nao_entra(): void
    {
        $matricula = $this->aluno($this->turma(), matricula: ['situacao' => 'cancelada', 'data_desativacao' => '2026-03-10', 'codigo_matricula_inep' => null]);

        $this->assertNull($this->linha($matricula));
    }

    public function test_registro_90_com_codigo_da_matricula_e_91_para_admitido_apos(): void
    {
        $turma = $this->turma();
        $admitido = $this->aluno($turma, 'Davi', matricula: ['data_ativacao' => '2026-08-10', 'codigo_matricula_inep' => null]);
        $this->resultado($admitido, 'aprovado');

        $linha = $this->linha($admitido);

        $this->assertSame(91, $linha['registro']);
        $this->assertSame([], $linha['pendencias']);
    }

    public function test_matricula_antiga_sem_codigo_e_pendencia_de_importar_o_retorno(): void
    {
        $matricula = $this->aluno($this->turma(), matricula: ['codigo_matricula_inep' => null]);
        $this->resultado($matricula, 'aprovado');

        $linha = $this->linha($matricula);

        $this->assertNull($linha['registro']);
        $this->assertStringContainsString('importe o retorno da matrícula inicial', implode(' ', $linha['pendencias']));
    }

    public function test_aluno_sem_id_inep_e_turma_sem_codigo_sao_pendencias(): void
    {
        $matricula = $this->aluno($this->turma(codigoInep: null), pessoa: ['codigo_inep' => null]);
        $this->resultado($matricula, 'aprovado');

        $pendencias = implode(' ', $this->linha($matricula)['pendencias']);

        $this->assertStringContainsString('sem ID Inep', $pendencias);
        $this->assertStringContainsString('Turma sem código Inep', $pendencias);
    }

    public function test_ajuste_manual_vale_no_lugar_do_calculo_e_a_justificativa_e_cifrada(): void
    {
        $matricula = $this->aluno($this->turma());
        $this->resultado($matricula, 'aprovado');

        $this->servico()->definirAjuste($matricula, SituacaoAlunoCenso::Falecido, 'Comunicado pela família em 12/08.', User::factory()->create());

        $linha = $this->linha($matricula);

        $this->assertSame(SituacaoAlunoCenso::Falecido, $linha['situacao']);
        $this->assertSame('ajuste', $linha['origem']);
        $this->assertStringContainsString('Comunicado pela família', $linha['motivo']);
        $this->assertStringContainsString('Cálculo do sistema: Aprovado', implode(' ', $linha['avisos']));
        $this->assertStringNotContainsString('Comunicado', (string) \DB::table('censo_situacoes_aluno')->value('justificativa'));
    }

    public function test_ajuste_respeita_as_regras_de_etapa_do_leiaute(): void
    {
        $infantil = $this->aluno($this->turma('Maternal', '2'));
        $fundamental = $this->aluno($this->turma('1º A', '14', '9002', 'T102'), 'Eva');

        foreach ([
            [$infantil, SituacaoAlunoCenso::Aprovado, 'Educação Infantil'],
            [$fundamental, SituacaoAlunoCenso::SemMovimentacao, 'só vale para a Educação Infantil'],
            [$fundamental, SituacaoAlunoCenso::AprovadoConcluinte, 'etapa final'],
            [$fundamental, SituacaoAlunoCenso::EmAndamento, 'EJA'],
        ] as [$matricula, $situacao, $trecho]) {
            try {
                $this->servico()->definirAjuste($matricula, $situacao, 'Justificativa suficiente.', null);
                $this->fail("Deveria recusar {$situacao->name}.");
            } catch (CensoEscolarException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage());
            }
        }

        $this->assertSame(0, CensoSituacaoAluno::count());
    }

    public function test_ajuste_exige_justificativa_e_pode_ser_removido(): void
    {
        $matricula = $this->aluno($this->turma());

        try {
            $this->servico()->definirAjuste($matricula, SituacaoAlunoCenso::Falecido, 'curta', null);
            $this->fail('Deveria exigir justificativa.');
        } catch (CensoEscolarException $e) {
            $this->assertStringContainsString('10 caracteres', $e->getMessage());
        }

        $this->servico()->definirAjuste($matricula, SituacaoAlunoCenso::Falecido, 'Comunicado pela família.', null);
        $this->servico()->definirAjuste($matricula, SituacaoAlunoCenso::DeixouDeFrequentar, 'Mudou de cidade sem aviso.', null);

        $this->assertSame(1, CensoSituacaoAluno::count(), 'um ajuste por matrícula');

        $this->servico()->removerAjuste($matricula);

        $this->assertSame(0, CensoSituacaoAluno::count());
    }

    public function test_ajuste_no_log_guarda_a_situacao_e_nao_a_justificativa(): void
    {
        $matricula = $this->aluno($this->turma());

        $this->servico()->definirAjuste($matricula, SituacaoAlunoCenso::Falecido, 'Segredo da família enlutada.', null);

        $log = Activity::query()->where('log_name', 'censo_escolar')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertStringNotContainsString('Segredo', json_encode($log->properties));
    }

    public function test_informar_codigos_valida_tamanho_e_duplicidade_do_id(): void
    {
        $turma = $this->turma(codigoInep: null);
        $a = $this->aluno($turma, 'Ana', pessoa: ['codigo_inep' => null], matricula: ['codigo_matricula_inep' => null]);
        $b = $this->aluno($turma, 'Bia', pessoa: ['codigo_inep' => '123456789012']);

        foreach ([['12345', null, null, '12 dígitos'], [null, '12345678901', null, 'até 10 dígitos'], [null, null, '1234567890123', 'até 12 dígitos'], ['123456789012', null, null, 'outro aluno']] as [$id, $turmaInep, $codigo, $trecho]) {
            try {
                $this->servico()->informarCodigos($a, $id, $turmaInep, $codigo, null);
                $this->fail("Deveria recusar: {$trecho}");
            } catch (CensoEscolarException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage());
            }
        }

        $this->servico()->informarCodigos($a, '999999999999', '777', '555555555555', null);

        $this->assertSame('999999999999', $a->pessoa->fresh()->codigo_inep);
        $this->assertSame('777', $turma->fresh()->codigo_inep);
        $this->assertSame('555555555555', $a->fresh()->codigo_matricula_inep);
        $this->assertSame('123456789012', $b->pessoa->fresh()->codigo_inep);
    }

    public function test_escola_sem_codigo_inep_ou_sem_gestor_tem_pendencias(): void
    {
        $this->assertSame([], $this->servico()->escola($this->unidade)['pendencias']);

        $semNada = Unidade::create(['nome' => 'Filial']);
        $pendencias = implode(' ', $this->servico()->escola($semNada)['pendencias']);

        $this->assertStringContainsString('código Inep da escola', $pendencias);
        $this->assertStringContainsString('gestor', $pendencias);
    }

    public function test_resumo_conta_prontas_pendencias_e_registros(): void
    {
        $turma = $this->turma();
        $pronta = $this->aluno($turma, 'Ana');
        $this->resultado($pronta, 'aprovado');
        $this->aluno($turma, 'Bia'); // sem resultado final: pendência
        $admitido = $this->aluno($turma, 'Caio', matricula: ['data_ativacao' => '2026-08-01', 'codigo_matricula_inep' => null]);
        $this->resultado($admitido, 'reprovado');

        $resumo = $this->servico()->resumo($this->servico()->apurar($this->unidade, $this->periodo));

        $this->assertSame(3, $resumo['total']);
        $this->assertSame(2, $resumo['prontas']);
        $this->assertSame(1, $resumo['com_pendencia']);
        $this->assertSame(2, $resumo['registro_90']);
        $this->assertSame(1, $resumo['registro_91']);
    }

    // --- Arquivo da Situação do Aluno ---------------------------------------------------------------------------------

    public function test_arquivo_tem_registro_89_depois_90_e_91_no_formato_do_inep(): void
    {
        $turma = $this->turma();
        $ana = $this->aluno($turma, 'Ana Lima');
        $this->resultado($ana, 'aprovado');
        $davi = $this->aluno($turma, 'Davi Reis', matricula: ['data_ativacao' => '2026-08-10', 'codigo_matricula_inep' => null]);
        $this->resultado($davi, 'reprovado');

        $arquivo = app(SituacaoAlunoCensoExporter::class)->situacao($this->unidade, $this->periodo);
        $linhas = explode("\r\n", rtrim($arquivo['conteudo'], "\r\n"));

        $this->assertSame('SA_12345678_2026.txt', $arquivo['arquivo']);
        $this->assertSame(2, $arquivo['enviados']);
        $this->assertCount(3, $linhas);
        $this->assertSame('89|12345678|52998224725|MARIA JOSE GESTORA', $linhas[0]);
        $this->assertSame('90|12345678|T101|9001|'.$ana->pessoa->codigo_inep.'|'.$ana->pessoa_id.'|'.$ana->codigo_matricula_inep.'|5', $linhas[1]);
        $this->assertSame('91|12345678|T101|9001|'.$davi->pessoa->codigo_inep.'|'.$davi->pessoa_id.'||||4', $linhas[2]);

        foreach ($linhas as $linha) {
            $campos = count(explode('|', $linha));
            $this->assertSame(['89' => 4, '90' => 8, '91' => 10][substr($linha, 0, 2)], $campos, "campos de {$linha}");
        }
    }

    public function test_registro_91_de_turma_sem_codigo_inep_leva_mediacao_e_etapa(): void
    {
        $turma = $this->turma(codigoInep: null);
        $davi = $this->aluno($turma, 'Davi Reis', matricula: ['data_ativacao' => '2026-08-10', 'codigo_matricula_inep' => null]);
        $this->resultado($davi, 'aprovado');

        $linhas = explode("\r\n", rtrim(app(SituacaoAlunoCensoExporter::class)->situacao($this->unidade, $this->periodo)['conteudo'], "\r\n"));

        $this->assertSame('91|12345678|T101||'.$davi->pessoa->codigo_inep.'|'.$davi->pessoa_id.'||1|14|5', $linhas[1]);
    }

    public function test_arquivo_e_iso_8859_1_com_crlf_e_sem_acentos_nem_minusculas(): void
    {
        $matricula = $this->aluno($this->turma());
        $this->resultado($matricula, 'aprovado');

        $conteudo = app(SituacaoAlunoCensoExporter::class)->situacao($this->unidade, $this->periodo)['conteudo'];

        $this->assertTrue(mb_check_encoding($conteudo, 'ASCII'));
        $this->assertStringContainsString("\r\n", $conteudo);
        $this->assertDoesNotMatchRegularExpression('/[a-z]/', $conteudo);
        $this->assertSame("A\xC7\xC3O\r\n", FormatoInep::arquivo(['AÇÃO']), 'ISO-8859-1 de verdade');
    }

    public function test_matriculas_com_pendencia_ficam_de_fora_com_o_motivo(): void
    {
        $turma = $this->turma();
        $pronta = $this->aluno($turma, 'Ana Lima');
        $this->resultado($pronta, 'aprovado');
        $this->aluno($turma, 'Bia Sem Resultado');

        $arquivo = app(SituacaoAlunoCensoExporter::class)->situacao($this->unidade, $this->periodo);

        $this->assertSame(1, $arquivo['enviados']);
        $this->assertCount(1, $arquivo['de_fora']);
        $this->assertSame('Bia Sem Resultado', $arquivo['de_fora'][0]['aluno']);
        $this->assertStringContainsString('Resultado final não calculado', $arquivo['de_fora'][0]['motivo']);
        $this->assertStringNotContainsString('BIA', $arquivo['conteudo'], 'o arquivo não leva nome de aluno');
    }

    public function test_nao_gera_arquivo_sem_codigo_da_escola_ou_sem_nenhuma_matricula_pronta(): void
    {
        $this->aluno($this->turma()); // sem resultado final

        try {
            app(SituacaoAlunoCensoExporter::class)->situacao($this->unidade, $this->periodo);
            $this->fail('Deveria recusar sem matrícula pronta.');
        } catch (CensoEscolarException $e) {
            $this->assertStringContainsString('Nenhuma matrícula está pronta', $e->getMessage());
        }

        $this->unidade->update(['codigo_inep' => null]);

        $this->expectException(CensoEscolarException::class);
        $this->expectExceptionMessage('código Inep da escola');
        app(SituacaoAlunoCensoExporter::class)->situacao($this->unidade->fresh(), $this->periodo);
    }

    public function test_gerar_o_arquivo_fica_no_log_sem_dados_de_aluno(): void
    {
        $matricula = $this->aluno($this->turma(), 'Ana Lima');
        $this->resultado($matricula, 'aprovado');

        app(SituacaoAlunoCensoExporter::class)->situacao($this->unidade, $this->periodo);

        $log = Activity::query()->where('log_name', 'censo_escolar')->latest('id')->first();

        $this->assertSame('Gerou o arquivo da Situação do Aluno do Censo Escolar', $log->description);
        $this->assertSame(1, $log->properties['enviados']);
        $this->assertStringNotContainsString('Ana', json_encode($log->properties));
    }

    // --- Arquivo de identificação -------------------------------------------------------------------------------------

    private function cidade(string $ibge = '3304557'): Cidade
    {
        $pais = Pais::firstOrCreate(['sigla' => 'BR'], ['nome' => 'Brasil', 'codigo' => '76']);
        $estado = Estado::firstOrCreate(['sigla' => 'RJ'], ['pais_id' => $pais->id, 'nome' => 'Rio de Janeiro']);

        return Cidade::firstOrCreate(['codigo_ibge' => $ibge], ['estado_id' => $estado->id, 'nome' => 'Rio de Janeiro']);
    }

    public function test_arquivo_de_identificacao_tem_9_campos_e_so_alunos_sem_id_inep(): void
    {
        $turma = $this->turma();
        $this->aluno($turma, 'Ana Com Id');
        $mae = Pessoa::create(['nome' => 'Joana Lima']);
        $pai = Pessoa::create(['nome' => 'Pedro Lima']);
        $semId = $this->aluno($turma, 'Bruno Ações Lima', pessoa: [
            'codigo_inep' => null,
            'cpf' => '11144477735',
            'data_nascimento' => '2014-03-15',
            'naturalidade_id' => $this->cidade()->id,
        ]);
        $semId->pessoa->responsaveis()->attach($mae->id, ['tipo_vinculo_id' => TipoVinculo::create(['nome' => 'Mãe'])->id]);
        $semId->pessoa->responsaveis()->attach($pai->id, ['tipo_vinculo_id' => TipoVinculo::create(['nome' => 'Pai'])->id]);

        $arquivo = app(SituacaoAlunoCensoExporter::class)->identificacao($this->unidade, $this->periodo);

        $this->assertSame('ID_12345678_2026.txt', $arquivo['arquivo']);
        $this->assertSame(1, $arquivo['enviados']);
        $this->assertSame($semId->pessoa_id.'|11144477735||BRUNO ACOES LIMA|15/03/2014|JOANA LIMA|PEDRO LIMA|3304557|', rtrim($arquivo['conteudo'], "\r\n"));
        $this->assertCount(9, explode('|', rtrim($arquivo['conteudo'], "\r\n")));
    }

    public function test_identificacao_deixa_de_fora_quem_nao_tem_municipio_de_nascimento(): void
    {
        $turma = $this->turma();
        $this->aluno($turma, 'Sem Cidade', pessoa: ['codigo_inep' => null, 'data_nascimento' => '2014-03-15']);
        $this->aluno($turma, 'Com Cidade', pessoa: ['codigo_inep' => null, 'data_nascimento' => '2014-03-15', 'naturalidade_id' => $this->cidade()->id]);

        $arquivo = app(SituacaoAlunoCensoExporter::class)->identificacao($this->unidade, $this->periodo);

        $this->assertSame(1, $arquivo['enviados']);
        $this->assertSame('Sem Cidade', $arquivo['de_fora'][0]['aluno']);
        $this->assertStringContainsString('município de nascimento', $arquivo['de_fora'][0]['motivo']);
    }

    public function test_identificacao_recusa_quando_todos_ja_tem_id(): void
    {
        $this->aluno($this->turma());

        $this->expectException(CensoEscolarException::class);
        $this->expectExceptionMessage('já têm o ID Inep');

        app(SituacaoAlunoCensoExporter::class)->identificacao($this->unidade, $this->periodo);
    }

    // --- Importação do retorno do Educacenso --------------------------------------------------------------------------

    /**
     * @return array{turma: Turma, matricula: Matricula}
     */
    private function alunoSemCodigos(): array
    {
        $turma = $this->turma(codigoInep: null);
        $matricula = $this->aluno($turma, 'Ana Lima', pessoa: ['codigo_inep' => null], matricula: ['codigo_matricula_inep' => null]);

        return ['turma' => $turma, 'matricula' => $matricula];
    }

    private function retorno(Turma $turma, Matricula $matricula, string $idInep = '123456789012', string $codigoTurma = '4455', string $codigoMatricula = '987654321098'): string
    {
        return implode("\r\n", [
            '00|12345678|1|||||',
            "20|12345678|{$turma->codigo}|{$codigoTurma}|TURMA 101|1|",
            "30|12345678|{$matricula->pessoa_id}|{$idInep}|11144477735|ANA LIMA|15/03/2014|",
            "60|12345678|{$matricula->pessoa_id}|{$idInep}|{$turma->codigo}|{$codigoTurma}|{$codigoMatricula}|0|",
            '99|',
        ]);
    }

    public function test_importa_o_retorno_da_matricula_inicial_e_grava_os_tres_codigos(): void
    {
        ['turma' => $turma, 'matricula' => $matricula] = $this->alunoSemCodigos();

        $resumo = app(EducacensoRetornoImporter::class)->importarMatriculaInicial($this->retorno($turma, $matricula));

        $this->assertSame(1, $resumo['turmas']);
        $this->assertSame(1, $resumo['pessoas']);
        $this->assertSame(1, $resumo['matriculas']);
        $this->assertSame([], $resumo['divergencias']);
        $this->assertSame('4455', $turma->fresh()->codigo_inep);
        $this->assertSame('123456789012', $matricula->pessoa->fresh()->codigo_inep);
        $this->assertSame('987654321098', $matricula->fresh()->codigo_matricula_inep);
    }

    public function test_importar_de_novo_o_mesmo_retorno_nao_muda_nada(): void
    {
        ['turma' => $turma, 'matricula' => $matricula] = $this->alunoSemCodigos();
        $importador = app(EducacensoRetornoImporter::class);
        $importador->importarMatriculaInicial($this->retorno($turma, $matricula));

        $resumo = $importador->importarMatriculaInicial($this->retorno($turma, $matricula));

        $this->assertSame([0, 0, 0], [$resumo['turmas'], $resumo['pessoas'], $resumo['matriculas']]);
        $this->assertSame([], $resumo['divergencias']);
    }

    public function test_codigo_diferente_do_cadastrado_nao_e_sobrescrito_e_vira_divergencia(): void
    {
        ['turma' => $turma, 'matricula' => $matricula] = $this->alunoSemCodigos();
        $importador = app(EducacensoRetornoImporter::class);
        $importador->importarMatriculaInicial($this->retorno($turma, $matricula));

        $resumo = $importador->importarMatriculaInicial($this->retorno($turma, $matricula, '999999999999', '1111', '555555555555'));

        $this->assertCount(3, $resumo['divergencias']);
        $this->assertSame('4455', $turma->fresh()->codigo_inep);
        $this->assertSame('123456789012', $matricula->pessoa->fresh()->codigo_inep);
        $this->assertSame('987654321098', $matricula->fresh()->codigo_matricula_inep);
    }

    public function test_id_inep_que_ja_pertence_a_outro_aluno_vira_divergencia(): void
    {
        ['turma' => $turma, 'matricula' => $matricula] = $this->alunoSemCodigos();
        $this->aluno($turma, 'Outro Aluno', pessoa: ['codigo_inep' => '123456789012']);

        $resumo = app(EducacensoRetornoImporter::class)->importarMatriculaInicial($this->retorno($turma, $matricula));

        $this->assertNull($matricula->pessoa->fresh()->codigo_inep);
        $this->assertStringContainsString('já está cadastrado para outro aluno', implode(' ', $resumo['divergencias']));
    }

    public function test_escola_desconhecida_e_ignorada_e_registro_sem_correspondencia_e_listado(): void
    {
        ['turma' => $turma, 'matricula' => $matricula] = $this->alunoSemCodigos();
        $conteudo = $this->retorno($turma, $matricula)."\r\n20|87654321|X1|777|OUTRA|1|\r\n30|12345678|999999|111111111111|";

        $resumo = app(EducacensoRetornoImporter::class)->importarMatriculaInicial($conteudo);

        $this->assertSame(['87654321'], $resumo['escolas_desconhecidas']);
        $this->assertSame(['Pessoa de código 999999 não encontrada.'], $resumo['nao_encontrados']);
    }

    public function test_aceita_arquivo_do_educacenso_em_iso_8859_1_com_bom_e_linhas_vazias(): void
    {
        ['turma' => $turma, 'matricula' => $matricula] = $this->alunoSemCodigos();
        $conteudo = "\xEF\xBB\xBF".mb_convert_encoding(str_replace('ANA LIMA', 'ANA LIMA ÇÃO', $this->retorno($turma, $matricula))."\r\n\r\n", 'ISO-8859-1', 'UTF-8');

        $resumo = app(EducacensoRetornoImporter::class)->importarMatriculaInicial($conteudo);

        $this->assertSame(1, $resumo['pessoas']);
    }

    public function test_arquivo_que_nao_e_do_educacenso_e_recusado(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(EducacensoRetornoImporter::class)->importarMatriculaInicial("qualquer coisa\noutra linha");
    }

    public function test_importa_o_resultado_da_identificacao_pelo_codigo_do_aluno(): void
    {
        ['matricula' => $matricula] = $this->alunoSemCodigos();
        $conteudo = "{$matricula->pessoa_id}|11144477735||ANA LIMA|15/03/2014|JOANA LIMA|PEDRO LIMA|3304557|424242424242\r\n8888|||FULANO|01/01/2010|||3304557|555555555555";

        $resumo = app(EducacensoRetornoImporter::class)->importarIdentificacao($conteudo);

        $this->assertSame(1, $resumo['pessoas']);
        $this->assertSame('424242424242', $matricula->pessoa->fresh()->codigo_inep);
        $this->assertSame(['Aluno de código 8888 não encontrado.'], $resumo['nao_encontrados']);
    }

    public function test_identificacao_fora_do_leiaute_e_recusada(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('9 campos');

        app(EducacensoRetornoImporter::class)->importarIdentificacao("90|12345678|T101|9001|1|2|3|5");
    }

    public function test_importar_fica_no_log_so_com_contagens(): void
    {
        ['turma' => $turma, 'matricula' => $matricula] = $this->alunoSemCodigos();

        app(EducacensoRetornoImporter::class)->importarMatriculaInicial($this->retorno($turma, $matricula));

        $log = Activity::query()->where('log_name', 'censo_escolar')->latest('id')->first();

        $this->assertSame('Importou o retorno da matrícula inicial do Educacenso', $log->description);
        $this->assertSame(1, $log->properties['pessoas']);
        $this->assertStringNotContainsString('123456789012', json_encode($log->properties));
    }
}
