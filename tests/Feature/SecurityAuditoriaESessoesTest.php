<?php

namespace Tests\Feature;

use App\Enums\StatusFatura;
use App\Filament\Pages\ControleSessoes;
use App\Models\Avaliacao;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\Fatura;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

class SecurityAuditoriaESessoesTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'professor', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create([
            'activated_at' => now()->subDay(),
        ]);
        $this->adminUser->assignRole('super_admin');
    }

    public function test_nota_registra_activity_log_ao_ser_criada_e_atualizada(): void
    {
        $this->actingAs($this->adminUser);

        $unidade = Unidade::create(['nome' => 'Unidade Teste']);
        $pessoa = Pessoa::create(['nome' => 'Aluno Teste']);
        $curso = Curso::create([
            'nome_externo' => 'Ensino Médio',
            'nome_interno' => 'EM',
            'unidade_id' => $unidade->id,
        ]);
        $periodo = PeriodoLetivo::create([
            'nome' => '2026',
            'ano' => 2026,
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);
        $serie = Serie::create([
            'nome' => '1ª Série',
            'curso_id' => $curso->id,
            'sistema_avaliacao' => 'Nota',
        ]);
        $turma = Turma::create([
            'nome' => 'Turma 101',
            'serie_id' => $serie->id,
            'periodo_letivo_id' => $periodo->id,
        ]);
        $matricula = Matricula::create([
            'pessoa_id' => $pessoa->id,
            'turma_id' => $turma->id,
            'ano_letivo' => 2026,
            'status' => 'ativa',
            'data_matricula' => now(),
        ]);
        $etapa = EtapaAvaliativa::create([
            'nome' => '1º Bimestre',
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $periodo->id,
            'ordem' => 1,
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-04-30',
        ]);
        $disciplina = Disciplina::create(['nome' => 'Matemática']);
        $avaliacao = Avaliacao::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'etapa_avaliativa_id' => $etapa->id,
            'data_ocorrencia' => '2026-03-15',
            'data_prevista' => '2026-03-15',
            'data_limite_lancamento' => '2026-03-30',
            'nota_maxima' => 10,
        ]);

        $nota = Nota::create([
            'avaliacao_id' => $avaliacao->id,
            'matricula_id' => $matricula->id,
            'valor' => 7.5,
            'situacao' => null,
        ]);

        $logCriacao = Activity::where('subject_type', Nota::class)
            ->where('subject_id', $nota->id)
            ->first();

        $this->assertNotNull($logCriacao, 'A criação de uma Nota deve gerar registro no ActivityLog.');

        // Atualização da nota (ex: correção de nota por professor)
        $nota->update([
            'valor' => 9.0,
        ]);

        $logAtualizacao = Activity::where('subject_type', Nota::class)
            ->where('subject_id', $nota->id)
            ->where('description', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($logAtualizacao, 'A alteração do valor de uma Nota deve ser registrada em auditoria.');
        $this->assertEquals(7.5, $logAtualizacao->properties['old']['valor']);
        $this->assertEquals(9.0, $logAtualizacao->properties['attributes']['valor']);
    }

    public function test_fatura_registra_activity_log_ao_ser_criada_e_atualizada(): void
    {
        $this->actingAs($this->adminUser);

        $unidade = Unidade::create(['nome' => 'Unidade Fin']);
        $curso = Curso::create([
            'nome_externo' => 'Ensino Fundamental',
            'nome_interno' => 'EF',
            'unidade_id' => $unidade->id,
        ]);
        $periodo = PeriodoLetivo::create([
            'nome' => '2026',
            'ano' => 2026,
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);
        $serie = Serie::create([
            'nome' => '5º Ano',
            'curso_id' => $curso->id,
            'sistema_avaliacao' => 'Nota',
        ]);
        $turma = Turma::create([
            'nome' => 'Turma 501',
            'serie_id' => $serie->id,
            'periodo_letivo_id' => $periodo->id,
        ]);
        $pessoa = Pessoa::create(['nome' => 'Responsável Financeiro']);
        $matricula = Matricula::create([
            'pessoa_id' => $pessoa->id,
            'turma_id' => $turma->id,
            'ano_letivo' => 2026,
            'status' => 'ativa',
            'data_matricula' => now(),
        ]);
        $contrato = Contrato::create([
            'matricula_id' => $matricula->id,
            'valor_total' => 1200.00,
        ]);

        $fatura = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => '2026-03-10',
            'status' => StatusFatura::Pendente,
        ]);

        $logCriacao = Activity::where('subject_type', Fatura::class)
            ->where('subject_id', $fatura->id)
            ->first();

        $this->assertNotNull($logCriacao, 'A criação de Fatura deve gerar registro de atividade.');

        // Alteração de status (ex: baixa manual ou registro de gateway)
        $fatura->update([
            'status' => StatusFatura::Pago,
            'gateway_id' => 'GW-123456',
        ]);

        $logAtualizacao = Activity::where('subject_type', Fatura::class)
            ->where('subject_id', $fatura->id)
            ->where('description', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($logAtualizacao, 'Alterações de status e gateway na Fatura devem ser auditadas.');
        $this->assertEquals(StatusFatura::Pago->value, $logAtualizacao->properties['attributes']['status']);
    }

    public function test_user_session_parseia_dispositivo_e_navegador_corretamente(): void
    {
        DB::table('sessions')->insert([
            'id' => 'sessao_windows_desktop',
            'user_id' => $this->adminUser->id,
            'ip_address' => '192.168.1.10',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'payload' => 'dummy',
            'last_activity' => now()->timestamp,
        ]);

        DB::table('sessions')->insert([
            'id' => 'sessao_iphone_mobile',
            'user_id' => $this->adminUser->id,
            'ip_address' => '177.100.50.2',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            'payload' => 'dummy',
            'last_activity' => now()->timestamp,
        ]);

        $sessaoDesktop = UserSession::find('sessao_windows_desktop');
        $this->assertNotNull($sessaoDesktop);
        $this->assertEquals('Windows', $sessaoDesktop->platform);
        $this->assertEquals('Google Chrome', $sessaoDesktop->browser);
        $this->assertFalse($sessaoDesktop->is_mobile);
        $this->assertStringContainsString('Google Chrome no Windows (Desktop)', $sessaoDesktop->device_label);

        $sessaoMobile = UserSession::find('sessao_iphone_mobile');
        $this->assertNotNull($sessaoMobile);
        $this->assertEquals('iOS (iPhone)', $sessaoMobile->platform);
        $this->assertEquals('Apple Safari', $sessaoMobile->browser);
        $this->assertTrue($sessaoMobile->is_mobile);
        $this->assertStringContainsString('(Mobile)', $sessaoMobile->device_label);
    }

    public function test_usuario_pode_revogar_sua_sessao_remota(): void
    {
        $outroUsuario = User::factory()->create([
            'activated_at' => now()->subDay(),
        ]);

        DB::table('sessions')->insert([
            'id' => 'sessao_para_revogar',
            'user_id' => $outroUsuario->id,
            'ip_address' => '10.0.0.5',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'payload' => 'dummy',
            'last_activity' => now()->timestamp,
        ]);

        $sessao = UserSession::find('sessao_para_revogar');
        $this->assertNotNull($sessao);

        $revogada = $sessao->revogar($this->adminUser);
        $this->assertTrue($revogada);
        $this->assertNull(UserSession::find('sessao_para_revogar'));

        $logRevogacao = Activity::where('log_name', 'auth')
            ->where('description', 'like', '%foi revogada%')
            ->latest('id')
            ->first();

        $this->assertNotNull($logRevogacao, 'A revogação de uma sessão deve registrar auditoria no canal auth.');
        $this->assertEquals($this->adminUser->id, $logRevogacao->causer_id);
    }

    public function test_pagina_controle_sessoes_renderiza_e_permite_desconectar_outras_sessoes(): void
    {
        $this->actingAs($this->adminUser);

        DB::table('sessions')->insert([
            'id' => 'sessao_atual_teste',
            'user_id' => $this->adminUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'payload' => 'dummy',
            'last_activity' => now()->timestamp,
        ]);

        DB::table('sessions')->insert([
            'id' => 'sessao_remota_teste_1',
            'user_id' => $this->adminUser->id,
            'ip_address' => '192.168.0.20',
            'user_agent' => 'Mozilla/5.0 (Android 14; Mobile)',
            'payload' => 'dummy',
            'last_activity' => now()->subMinutes(10)->timestamp,
        ]);

        Livewire::test(ControleSessoes::class)
            ->assertSuccessful()
            ->callAction('desconectarOutrasSessoes')
            ->assertNotified();

        // A sessão remota deve ter sido deletada
        $this->assertDatabaseMissing('sessions', ['id' => 'sessao_remota_teste_1']);
    }
}
