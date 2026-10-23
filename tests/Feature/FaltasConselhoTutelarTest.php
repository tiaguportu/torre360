<?php

namespace Tests\Feature;

use App\Filament\Resources\NotificacoesConselhoTutelar\Pages\ListNotificacoesConselhoTutelar;
use App\Models\CronogramaAula;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\NotificacaoConselhoTutelar;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use App\Services\FaltasConselhoTutelarService;
use Database\Factories\PeriodoLetivoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FaltasConselhoTutelarTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComoAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $perms = [
            'ViewAny:NotificacaoConselhoTutelar',
            'View:NotificacaoConselhoTutelar',
            'Create:NotificacaoConselhoTutelar',
            'Update:NotificacaoConselhoTutelar',
            'Delete:NotificacaoConselhoTutelar',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $role->syncPermissions(Permission::all());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function criarCenarioAluno(): array
    {
        $unidade = Unidade::create(['nome' => 'Unidade Teste', 'situacao_funcionamento' => '1']);
        $curso = Curso::create(['unidade_id' => $unidade->id, 'nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF']);
        $serie = Serie::create(['curso_id' => $curso->id, 'nome' => '8º Ano', 'sistema_avaliacao' => 'Nota']);
        $turno = Turno::create(['nome' => 'Tarde', 'hora_inicio' => '13:00:00', 'hora_fim' => '17:30:00']);
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'ano' => 2026, 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);

        $turma = Turma::create([
            'periodo_letivo_id' => PeriodoLetivoFactory::idPadrao(),
            'nome' => 'Turma 801',
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
            'vagas_maximas' => 30,
        ]);

        $aluno = Pessoa::create([
            'nome' => 'Aluno Infrequente Teste',
            'data_nascimento' => '2012-08-20',
        ]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $disciplina = Disciplina::create(['nome' => 'Língua Portuguesa', 'sigla' => 'PORT']);
        $professor = Pessoa::create(['nome' => 'Professora Silva', 'data_nascimento' => '1980-01-15']);

        return [$matricula, $turma, $disciplina, $professor];
    }

    public function test_calculo_de_atingimento_de_limite_de_faltas_ldb(): void
    {
        $service = app(FaltasConselhoTutelarService::class);
        [$matricula, $turma, $disciplina, $professor] = $this->criarCenarioAluno();

        // Cria 100 aulas previstas no cronograma para facilitar a checagem percentual (1 falta = 1%)
        $aulas = [];
        for ($i = 1; $i <= 100; $i++) {
            $aulas[] = CronogramaAula::create([
                'turma_id' => $turma->id,
                'disciplina_id' => $disciplina->id,
                'pessoa_id' => $professor->id,
                'data' => now()->subDays(105 - $i)->toDateString(),
                'hora_inicio' => '13:00:00',
                'hora_fim' => '13:50:00',
            ]);
        }

        // Registra 8 faltas (8.0% > 7.5% da LDB art. 12, VIII)
        for ($i = 0; $i < 8; $i++) {
            FrequenciaEscolar::create([
                'matricula_id' => $matricula->id,
                'cronograma_aula_id' => $aulas[$i]->id,
                'situacao' => 'ausente',
                'justificada' => false,
            ]);
        }

        // Registra presenças nas demais
        for ($i = 8; $i < 20; $i++) {
            FrequenciaEscolar::create([
                'matricula_id' => $matricula->id,
                'cronograma_aula_id' => $aulas[$i]->id,
                'situacao' => 'presente',
            ]);
        }

        $resultado = $service->calcularAtingimentoLimite($matricula);

        $this->assertEquals(100, $resultado['total_aulas_previstas']);
        $this->assertEquals(8, $resultado['faltas_totais']);
        $this->assertEquals(8.0, $resultado['percentual_faltas']);
        $this->assertTrue($resultado['atingiu_gatilho_conselho'], 'Deve atingir o gatilho da LDB de 7,5%');
        $this->assertTrue($resultado['atingiu_alerta_preventivo'], 'Deve atingir o alerta preventivo de 5,0%');
        $this->assertFalse($resultado['em_risco_reprovacao'], 'Não deve estar em risco de reprovação direta (25%)');
    }

    public function test_geracao_e_fluxo_completo_da_notificacao_conselho_tutelar(): void
    {
        $user = $this->autenticarComoAdmin();
        $service = app(FaltasConselhoTutelarService::class);
        [$matricula, $turma, $disciplina, $professor] = $this->criarCenarioAluno();

        // 1. Gera notificação
        $notificacao = $service->gerarNotificacao($matricula, $user, [
            'nome_conselho_tutelar' => 'Conselho Tutelar Centro',
            'municipio_conselho' => 'Curitiba / PR',
        ]);

        $this->assertNotNull($notificacao->id);
        $this->assertStringStartsWith('FICAI-', $notificacao->numero_oficio);
        $this->assertEquals('em_analise', $notificacao->status);

        // 2. Registra tentativas de contato prévias (ECA art. 56, II)
        $service->registrarTentativaContato($notificacao, [
            'data' => '2026-04-10',
            'meio' => 'telefone',
            'responsavel_contatado' => 'Mãe',
            'resultado' => 'Prometeu retorno do aluno, mas este não compareceu.',
        ], $user);

        $service->registrarTentativaContato($notificacao, [
            'data' => '2026-04-14',
            'meio' => 'whatsapp',
            'responsavel_contatado' => 'Mãe',
            'resultado' => 'Mensagem lida sem resposta.',
        ], $user);

        $this->assertTrue($notificacao->fresh()->temTentativasSuficientes());

        // 3. Gera texto formal do ofício FICAI
        $textoOficio = $service->gerarTextoOficio($notificacao->fresh());
        $this->assertStringContainsString('OFÍCIO DE COMUNICAÇÃO DE INFREQUÊNCIA ESCOLAR (FICAI)', $textoOficio);
        $this->assertStringContainsString('Aluno Infrequente Teste', $textoOficio);
        $this->assertStringContainsString('Artigo 12, inciso VIII da Lei de Diretrizes e Bases', $textoOficio);
        $this->assertStringContainsString('Prometeu retorno do aluno', $textoOficio);

        // 4. Registra protocolo de envio
        $service->registrarProtocoloEnvio($notificacao, 'PROT-CT-2026/9981', '2026-04-16');
        $this->assertEquals('notificado', $notificacao->fresh()->status);
        $this->assertEquals('PROT-CT-2026/9981', $notificacao->fresh()->protocolo_conselho);
    }

    public function test_listagem_notificacoes_no_filament_com_ajuda(): void
    {
        $this->autenticarComoAdmin();
        [$matricula, $turma] = $this->criarCenarioAluno();

        $notificacao = NotificacaoConselhoTutelar::create([
            'matricula_id' => $matricula->id,
            'turma_id' => $turma->id,
            'ano_letivo' => 2026,
            'numero_oficio' => NotificacaoConselhoTutelar::gerarNumeroOficio(),
            'total_aulas_previstas' => 800,
            'total_presencas' => 50,
            'total_faltas' => 70,
            'percentual_faltas' => 8.75,
            'status' => 'em_analise',
        ]);

        Livewire::test(ListNotificacoesConselhoTutelar::class)
            ->assertCanSeeTableRecords([$notificacao])
            ->assertActionExists('ajuda');
    }
}
