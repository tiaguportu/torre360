<?php

namespace Tests\Feature;

use App\Enums\NivelAlcadaComercial;
use App\Enums\StatusPropostaComercial;
use App\Filament\Resources\PropostaComercials\Pages\ListPropostaComercials;
use App\Models\Curso;
use App\Models\PropostaComercial;
use App\Models\Serie;
use App\Models\Unidade;
use App\Models\User;
use App\Services\RevenueManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PropostaComercialRevenueManagementTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComo(string $roleName = 'admin'): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $perms = [
            'ViewAny:PropostaComercial',
            'View:PropostaComercial',
            'Create:PropostaComercial',
            'Update:PropostaComercial',
            'Delete:PropostaComercial',
            'DeleteAny:PropostaComercial',
            'Aprovar:PropostaComercial',
        ];

        foreach ($perms as $p) {
            $perm = Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $role->givePermissionTo($perm);
        }

        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $user->assignRole($roleName);
        $this->actingAs($user);
        session(['active_role' => $roleName]);

        return $user;
    }

    private function criarEstrutura(): array
    {
        $unidade = Unidade::create([
            'nome' => 'Unidade Central',
            'situacao_funcionamento' => '1',
        ]);

        $curso = Curso::create([
            'unidade_id' => $unidade->id,
            'nome_externo' => 'Ensino Fundamental',
            'nome_interno' => 'Ensino Fundamental',
        ]);

        $serie = Serie::create([
            'curso_id' => $curso->id,
            'nome' => '6º Ano',
            'sistema_avaliacao' => 'Nota',
        ]);

        return [$unidade, $curso, $serie];
    }

    public function test_determinacao_de_alcada_comercial(): void
    {
        $service = app(RevenueManagementService::class);

        $this->assertEquals(NivelAlcadaComercial::Consultor, $service->determinarAlcada(5.0));
        $this->assertEquals(NivelAlcadaComercial::Consultor, $service->determinarAlcada(7.0));
        $this->assertEquals(NivelAlcadaComercial::Coordenacao, $service->determinarAlcada(8.0));
        $this->assertEquals(NivelAlcadaComercial::Coordenacao, $service->determinarAlcada(15.0));
        $this->assertEquals(NivelAlcadaComercial::Diretoria, $service->determinarAlcada(15.1));
        $this->assertEquals(NivelAlcadaComercial::Diretoria, $service->determinarAlcada(30.0));
    }

    public function test_calculo_financeiro_proposta_comercial(): void
    {
        $service = app(RevenueManagementService::class);

        $calc = $service->calcularValores(
            valorTabelaMensal: 1000.00,
            tipoDesconto: 'percentual',
            descontoSolicitado: 10.00,
            quantidadeAlunos: 2,
            quantidadeParcelas: 12
        );

        $this->assertEquals(100.00, $calc['valor_desconto_mensal']);
        $this->assertEquals(900.00, $calc['valor_liquido_mensal']);
        $this->assertEquals(21600.00, $calc['valor_total_anual']); // 900 * 2 alunos * 12 parcelas
        $this->assertEquals(NivelAlcadaComercial::Coordenacao, $calc['nivel_alcada']);
    }

    public function test_criacao_com_alcada_consultor_aprova_automaticamente(): void
    {
        $user = $this->autenticarComo('secretaria');
        [$unidade, $curso, $serie] = $this->criarEstrutura();
        $service = app(RevenueManagementService::class);

        $proposta = new PropostaComercial([
            'codigo' => PropostaComercial::gerarCodigo(),
            'responsavel_nome' => 'Maria Silva',
            'unidade_id' => $unidade->id,
            'curso_id' => $curso->id,
            'serie_id' => $serie->id,
            'valor_tabela_mensal' => 1000.00,
            'tipo_desconto' => 'percentual',
            'desconto_solicitado' => 5.00,
            'valor_desconto_mensal' => 50.00,
            'valor_liquido_mensal' => 950.00,
            'valor_total_anual' => 11400.00,
            'nivel_alcada_necessario' => NivelAlcadaComercial::Consultor,
            'solicitado_por_user_id' => $user->id,
            'validade' => now()->addDays(7),
        ]);

        $processada = $service->processarCriacao($proposta, $user);

        $this->assertEquals(StatusPropostaComercial::AprovadaAutomatica, $processada->status);
        $this->assertEquals($user->id, $processada->aprovado_por_user_id);
        $this->assertNotNull($processada->aprovado_em);
    }

    public function test_criacao_com_desconto_alto_aguarda_aprovacao_de_alcada(): void
    {
        $user = $this->autenticarComo('secretaria');
        [$unidade, $curso, $serie] = $this->criarEstrutura();
        $service = app(RevenueManagementService::class);

        $proposta = new PropostaComercial([
            'codigo' => PropostaComercial::gerarCodigo(),
            'responsavel_nome' => 'Carlos Souza',
            'unidade_id' => $unidade->id,
            'curso_id' => $curso->id,
            'serie_id' => $serie->id,
            'valor_tabela_mensal' => 1000.00,
            'tipo_desconto' => 'percentual',
            'desconto_solicitado' => 20.00,
            'valor_desconto_mensal' => 200.00,
            'valor_liquido_mensal' => 800.00,
            'valor_total_anual' => 9600.00,
            'nivel_alcada_necessario' => NivelAlcadaComercial::Diretoria,
            'solicitado_por_user_id' => $user->id,
            'validade' => now()->addDays(7),
        ]);

        $processada = $service->processarCriacao($proposta, $user);

        $this->assertEquals(StatusPropostaComercial::AguardandoAprovacao, $processada->status);
        $this->assertNull($processada->aprovado_em);
    }

    public function test_aprovacao_e_recusa_de_proposta_comercial(): void
    {
        $admin = $this->autenticarComo('admin');
        [$unidade, $curso, $serie] = $this->criarEstrutura();
        $service = app(RevenueManagementService::class);

        $proposta = PropostaComercial::create([
            'codigo' => PropostaComercial::gerarCodigo(),
            'responsavel_nome' => 'Paula Lima',
            'unidade_id' => $unidade->id,
            'curso_id' => $curso->id,
            'serie_id' => $serie->id,
            'valor_tabela_mensal' => 1200.00,
            'tipo_desconto' => 'percentual',
            'desconto_solicitado' => 12.00,
            'valor_desconto_mensal' => 144.00,
            'valor_liquido_mensal' => 1056.00,
            'valor_total_anual' => 12672.00,
            'status' => StatusPropostaComercial::AguardandoAprovacao,
            'nivel_alcada_necessario' => NivelAlcadaComercial::Coordenacao,
            'solicitado_por_user_id' => $admin->id,
            'validade' => now()->addDays(5),
        ]);

        $this->assertTrue($proposta->isPendente());
        $this->assertTrue($proposta->podeSerAprovadaPor($admin));

        // Aprovação
        $service->aprovar($proposta, $admin, 'Aprovado pelo comitê de matrículas.');
        $proposta->refresh();
        $this->assertEquals(StatusPropostaComercial::Aprovada, $proposta->status);
        $this->assertEquals($admin->id, $proposta->aprovado_por_user_id);

        // Recusa
        $service->recusar($proposta, $admin, 'Desconto incompatível com a capacidade da sala.');
        $proposta->refresh();
        $this->assertEquals(StatusPropostaComercial::Recusada, $proposta->status);
        $this->assertEquals('Desconto incompatível com a capacidade da sala.', $proposta->motivo_recusa);
    }

    public function test_geracao_de_texto_para_whatsapp(): void
    {
        $admin = $this->autenticarComo('admin');
        [$unidade, $curso, $serie] = $this->criarEstrutura();
        $service = app(RevenueManagementService::class);

        $proposta = PropostaComercial::create([
            'codigo' => 'PROP-2026-99999',
            'responsavel_nome' => 'Lucas Rocha',
            'responsavel_telefone' => '11999998888',
            'aluno_nome' => 'Enzo Rocha',
            'unidade_id' => $unidade->id,
            'curso_id' => $curso->id,
            'serie_id' => $serie->id,
            'valor_tabela_mensal' => 1500.00,
            'tipo_desconto' => 'percentual',
            'desconto_solicitado' => 10.00,
            'valor_desconto_mensal' => 150.00,
            'valor_liquido_mensal' => 1350.00,
            'valor_total_anual' => 16200.00,
            'status' => StatusPropostaComercial::Aprovada,
            'nivel_alcada_necessario' => NivelAlcadaComercial::Coordenacao,
            'solicitado_por_user_id' => $admin->id,
            'validade' => now()->addDays(5),
        ]);

        $texto = $service->gerarTextoWhatsapp($proposta);

        $this->assertStringContainsString('Lucas Rocha', $texto);
        $this->assertStringContainsString('PROP-2026-99999', $texto);
        $this->assertStringContainsString('Enzo Rocha', $texto);
        $this->assertStringContainsString('1.350,00', $texto);
        $this->assertStringContainsString('10,0%', $texto);
    }

    public function test_listagem_de_propostas_comerciais_carrega(): void
    {
        $this->autenticarComo('admin');
        [$unidade, $curso, $serie] = $this->criarEstrutura();

        PropostaComercial::create([
            'codigo' => PropostaComercial::gerarCodigo(),
            'responsavel_nome' => 'Cliente Teste',
            'unidade_id' => $unidade->id,
            'curso_id' => $curso->id,
            'serie_id' => $serie->id,
            'valor_tabela_mensal' => 1000.00,
            'tipo_desconto' => 'percentual',
            'desconto_solicitado' => 0.0,
            'valor_desconto_mensal' => 0.0,
            'valor_liquido_mensal' => 1000.00,
            'valor_total_anual' => 12000.00,
            'status' => StatusPropostaComercial::AprovadaAutomatica,
            'nivel_alcada_necessario' => NivelAlcadaComercial::Consultor,
            'solicitado_por_user_id' => auth()->id(),
            'validade' => now()->addDays(7),
        ]);

        Livewire::test(ListPropostaComercials::class)
            ->assertSuccessful()
            ->assertSee('Cliente Teste');
    }
}
