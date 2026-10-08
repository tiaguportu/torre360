<?php

namespace Tests\Feature;

use App\Enums\StatusFatura;
use App\Enums\StatusSolicitacaoDocumento;
use App\Enums\TipoTemplateDocumento;
use App\Filament\Portal\Pages\SolicitacoesDocumentos;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\SolicitacaoDocumento;
use App\Models\TemplateDocumento;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AutoSolicitacaoDocumentosPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    private function criarCenarioEstudanteEUsuario(bool $comDebitoVencido = false): array
    {
        $aluno = Pessoa::create([
            'nome' => 'Enzo Gabriel Pereira',
            'cpf' => '12345678909',
            'data_nascimento' => '2014-03-15',
        ]);

        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole('responsavel');
        $user->pessoas()->attach($aluno->id);

        $periodo = PeriodoLetivo::create([
            'nome' => '2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);

        $turno = Turno::firstOrCreate(
            ['nome' => 'Matutino'],
            [
                'hora_inicio' => '07:15:00',
                'hora_fim' => '12:35:00',
            ]
        );

        $turma = Turma::create([
            'nome' => '6º Ano B',
            'periodo_letivo_id' => $periodo->id,
            'turno_id' => $turno->id,
        ]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $contrato = Contrato::create([
            'matricula_id' => $matricula->id,
            'status' => 'ativo',
            'valor_total' => 12000.00,
        ]);

        if ($comDebitoVencido) {
            Fatura::create([
                'contrato_id' => $contrato->id,
                'vencimento' => now()->subDays(10),
                'status' => StatusFatura::Atrasado,
            ]);
        } else {
            Fatura::create([
                'contrato_id' => $contrato->id,
                'vencimento' => now()->subDays(10),
                'status' => StatusFatura::Pago,
            ]);
        }

        $templateMatricula = TemplateDocumento::firstOrCreate(
            ['nome' => 'Declaração de Matrícula Regular'],
            [
                'tipo' => TipoTemplateDocumento::DeclaracaoMatricula,
                'descricao' => 'Atesta vínculo ativo.',
                'conteudo' => '<p>Declaramos que {{ALUNO_NOME}} está regularmente matriculado na turma {{TURMA_NOME}} sob o protocolo {{PROTOCOLO}}.</p>',
                'validade_dias' => 30,
                'is_ativo' => true,
            ]
        );

        $templateQuitacao = TemplateDocumento::firstOrCreate(
            ['nome' => 'Declaração de Quitação de Débitos'],
            [
                'tipo' => TipoTemplateDocumento::DeclaracaoQuitacao,
                'descricao' => 'Atesta quitação financeira.',
                'conteudo' => '<p>Declaramos para os fins legais que {{ALUNO_NOME}} quitou suas obrigações financeiras.</p>',
                'validade_dias' => 60,
                'is_ativo' => true,
            ]
        );

        $templateTransporte = TemplateDocumento::firstOrCreate(
            ['nome' => 'Declaração para Passe Escolar e Transporte'],
            [
                'tipo' => TipoTemplateDocumento::DeclaracaoTransporte,
                'descricao' => 'Comprova horários para transporte.',
                'conteudo' => '<p>Declaramos que {{ALUNO_NOME}} frequenta aulas no turno {{TURNO_NOME}} ({{HORARIO_AULAS}}).</p>',
                'validade_dias' => 60,
                'is_ativo' => true,
            ]
        );

        return [
            'user' => $user,
            'aluno' => $aluno,
            'matricula' => $matricula,
            'contrato' => $contrato,
            'templateMatricula' => $templateMatricula,
            'templateQuitacao' => $templateQuitacao,
            'templateTransporte' => $templateTransporte,
        ];
    }

    public function test_responsavel_pode_carregar_pagina_de_declaracoes_no_portal(): void
    {
        $dados = $this->criarCenarioEstudanteEUsuario();

        $this->actingAs($dados['user']);

        Livewire::test(SolicitacoesDocumentos::class)
            ->assertSuccessful()
            ->assertSee('Enzo Gabriel Pereira')
            ->assertSee('Declarações Disponíveis para Emissão Imediata');
    }

    public function test_responsavel_pode_emitir_declaracao_de_matricula_instantaneamente(): void
    {
        Storage::fake('local');
        $dados = $this->criarCenarioEstudanteEUsuario();

        $this->actingAs($dados['user']);

        Livewire::test(SolicitacoesDocumentos::class)
            ->call('emitirInstantaneo', $dados['templateMatricula']->id)
            ->assertHasNoErrors();

        $solicitacao = SolicitacaoDocumento::where('matricula_id', $dados['matricula']->id)
            ->where('template_documento_id', $dados['templateMatricula']->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($solicitacao);
        $this->assertEquals(StatusSolicitacaoDocumento::Disponivel, $solicitacao->status);
        $this->assertNotEmpty($solicitacao->arquivo_path);
        Storage::disk('local')->assertExists($solicitacao->arquivo_path);
    }

    public function test_bloqueia_declaracao_de_quitacao_quando_ha_inadimplencia(): void
    {
        Storage::fake('local');
        $dados = $this->criarCenarioEstudanteEUsuario(comDebitoVencido: true);

        $this->actingAs($dados['user']);

        Livewire::test(SolicitacoesDocumentos::class)
            ->call('emitirInstantaneo', $dados['templateQuitacao']->id);

        $solicitacao = SolicitacaoDocumento::where('matricula_id', $dados['matricula']->id)
            ->where('template_documento_id', $dados['templateQuitacao']->id)
            ->first();

        // Não deve ter sido criado nenhum documento de quitação devido à inadimplência
        $this->assertNull($solicitacao);
    }

    public function test_permite_declaracao_de_quitacao_quando_aluno_esta_adimplente(): void
    {
        Storage::fake('local');
        $dados = $this->criarCenarioEstudanteEUsuario(comDebitoVencido: false);

        $this->actingAs($dados['user']);

        Livewire::test(SolicitacoesDocumentos::class)
            ->call('emitirInstantaneo', $dados['templateQuitacao']->id)
            ->assertHasNoErrors();

        $solicitacao = SolicitacaoDocumento::where('matricula_id', $dados['matricula']->id)
            ->where('template_documento_id', $dados['templateQuitacao']->id)
            ->first();

        $this->assertNotNull($solicitacao);
        $this->assertEquals(StatusSolicitacaoDocumento::Disponivel, $solicitacao->status);
    }

    public function test_emissao_de_declaracao_de_transporte_preenche_macros_de_horario(): void
    {
        Storage::fake('local');
        $dados = $this->criarCenarioEstudanteEUsuario();

        $this->actingAs($dados['user']);

        Livewire::test(SolicitacoesDocumentos::class)
            ->call('emitirInstantaneo', $dados['templateTransporte']->id)
            ->assertHasNoErrors();

        $solicitacao = SolicitacaoDocumento::where('matricula_id', $dados['matricula']->id)
            ->where('template_documento_id', $dados['templateTransporte']->id)
            ->first();

        $this->assertNotNull($solicitacao);
        Storage::disk('local')->assertExists($solicitacao->arquivo_path);
    }

    public function test_validacao_publica_por_qr_code_de_declaracao_emitida_no_portal(): void
    {
        Storage::fake('local');
        $dados = $this->criarCenarioEstudanteEUsuario();

        $this->actingAs($dados['user']);

        Livewire::test(SolicitacoesDocumentos::class)
            ->call('emitirInstantaneo', $dados['templateMatricula']->id);

        $solicitacao = SolicitacaoDocumento::where('matricula_id', $dados['matricula']->id)->first();
        $this->assertNotNull($solicitacao);

        $response = $this->get(route('documentos.validar-autenticidade', $solicitacao->codigo_verificacao));
        $response->assertSuccessful();
        $response->assertSee($solicitacao->protocolo);
        $response->assertSee('Documento Autêntico e Válido');
    }

    public function test_acao_ajuda_esta_presente_na_pagina(): void
    {
        $dados = $this->criarCenarioEstudanteEUsuario();

        $this->actingAs($dados['user']);

        Livewire::test(SolicitacoesDocumentos::class)
            ->assertActionExists('ajuda');
    }
}
