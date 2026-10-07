<?php

namespace Tests\Feature;

use App\Enums\NivelAlcadaComercial;
use App\Enums\StatusPropostaComercial;
use App\Filament\Resources\PropostaComercials\Pages\CreatePropostaComercial;
use App\Filament\Resources\PropostaComercials\Pages\EditPropostaComercial;
use App\Filament\Resources\PropostaComercials\Pages\ListPropostaComercials;
use App\Models\Curso;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\PropostaComercial;
use App\Models\Serie;
use App\Models\StatusInteressado;
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

    public function test_pagina_de_criacao_de_proposta_comercial_carrega_com_sucesso(): void
    {
        $this->autenticarComo('admin');
        [$unidade, $curso, $serie] = $this->criarEstrutura();

        $pessoa = Pessoa::create([
            'nome' => 'Responsável Lead Teste',
            'email' => 'lead@exemplo.com',
            'telefone' => '11999998888',
        ]);

        $origem = OrigemInteressado::create(['nome' => 'Site']);
        $status = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1]);

        $lead = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'origem_interessado_id' => $origem->id,
            'status_interessado_id' => $status->id,
        ]);

        Livewire::test(CreatePropostaComercial::class)
            ->assertSuccessful();

        Livewire::withQueryParams(['interessado_id' => $lead->id])
            ->test(CreatePropostaComercial::class)
            ->assertSuccessful()
            ->assertSet('data.responsavel_nome', 'Responsável Lead Teste')
            ->assertSet('data.responsavel_email', 'lead@exemplo.com');
    }

    /**
     * Cria uma proposta de R$ 1.000,00/mês passando pela regra de alçada, como no formulário de criação.
     */
    private function criarPropostaProcessada(User $autor, float $desconto): PropostaComercial
    {
        [$unidade, $curso, $serie] = $this->criarEstrutura();
        $service = app(RevenueManagementService::class);
        $calc = $service->calcularValores(1000.0, 'percentual', $desconto, 1, 12);

        $proposta = new PropostaComercial([
            'codigo' => PropostaComercial::gerarCodigo(),
            'responsavel_nome' => 'Maria Silva',
            'unidade_id' => $unidade->id,
            'curso_id' => $curso->id,
            'serie_id' => $serie->id,
            'quantidade_alunos' => 1,
            'quantidade_parcelas' => 12,
            'valor_tabela_mensal' => 1000.00,
            'tipo_desconto' => 'percentual',
            'desconto_solicitado' => $desconto,
            'valor_desconto_mensal' => $calc['valor_desconto_mensal'],
            'valor_liquido_mensal' => $calc['valor_liquido_mensal'],
            'valor_total_anual' => $calc['valor_total_anual'],
            'nivel_alcada_necessario' => $calc['nivel_alcada'],
            'solicitado_por_user_id' => $autor->id,
            'validade' => now()->addDays(7),
        ]);

        return $service->processarCriacao($proposta, $autor);
    }

    public function test_aumentar_desconto_de_proposta_aprovada_automaticamente_exige_nova_aprovacao(): void
    {
        $consultor = $this->autenticarComo('secretaria');
        $proposta = $this->criarPropostaProcessada($consultor, 5.0);
        $this->assertSame(StatusPropostaComercial::AprovadaAutomatica, $proposta->status);

        $atualizada = app(RevenueManagementService::class)->atualizar($proposta, ['desconto_solicitado' => 40], $consultor);

        $this->assertSame(StatusPropostaComercial::AguardandoAprovacao, $atualizada->status);
        $this->assertSame(NivelAlcadaComercial::Diretoria, $atualizada->nivel_alcada_necessario);
        $this->assertNull($atualizada->aprovado_por_user_id);
        $this->assertNull($atualizada->aprovado_em);
        $this->assertEquals(600.00, (float) $atualizada->valor_liquido_mensal);
        $this->assertEquals(7200.00, (float) $atualizada->valor_total_anual);

        $this->assertSame(StatusPropostaComercial::AguardandoAprovacao, $proposta->fresh()->status);
    }

    public function test_aumentar_desconto_so_dentro_da_propria_alcada_reaprova_na_hora(): void
    {
        $admin = $this->autenticarComo('admin');
        $proposta = $this->criarPropostaProcessada($admin, 5.0);

        $atualizada = app(RevenueManagementService::class)->atualizar($proposta, ['desconto_solicitado' => 12], $admin);

        $this->assertSame(StatusPropostaComercial::Aprovada, $atualizada->status);
        $this->assertSame(NivelAlcadaComercial::Coordenacao, $atualizada->nivel_alcada_necessario);
        $this->assertSame($admin->id, $atualizada->aprovado_por_user_id);
    }

    public function test_editar_campos_sem_efeito_comercial_preserva_a_aprovacao(): void
    {
        $consultor = $this->autenticarComo('secretaria');
        $proposta = $this->criarPropostaProcessada($consultor, 5.0);
        $aprovadoEm = $proposta->aprovado_em;

        $atualizada = app(RevenueManagementService::class)->atualizar($proposta, [
            'responsavel_nome' => 'Maria Souza Silva',
            'responsavel_telefone' => '11988887777',
            // Mesmos valores, em outro formato (o formulário devolve números como string).
            'desconto_solicitado' => '5.00',
            'valor_tabela_mensal' => '1000',
        ], $consultor);

        $this->assertSame(StatusPropostaComercial::AprovadaAutomatica, $atualizada->status);
        $this->assertSame($consultor->id, $atualizada->aprovado_por_user_id);
        $this->assertTrue($aprovadoEm->equalTo($atualizada->fresh()->aprovado_em));
        $this->assertSame('Maria Souza Silva', $atualizada->fresh()->responsavel_nome);
    }

    public function test_reduzir_desconto_mantem_a_aprovacao_ja_concedida(): void
    {
        $admin = $this->autenticarComo('admin');
        $proposta = $this->criarPropostaProcessada($admin, 12.0);
        $this->assertSame(StatusPropostaComercial::Aprovada, $proposta->status);

        // O consultor reduz o desconto: a condição ficou menos generosa que a aprovada.
        $consultor = $this->autenticarComo('secretaria');
        $atualizada = app(RevenueManagementService::class)->atualizar($proposta, ['desconto_solicitado' => 8], $consultor);

        $this->assertSame(StatusPropostaComercial::Aprovada, $atualizada->status);
        $this->assertSame($admin->id, $atualizada->aprovado_por_user_id);
        $this->assertEquals(920.00, (float) $atualizada->valor_liquido_mensal);
    }

    public function test_reduzir_mensalidade_de_tabela_com_mesmo_percentual_conta_como_condicao_mais_generosa(): void
    {
        $admin = $this->autenticarComo('admin');
        $proposta = $this->criarPropostaProcessada($admin, 12.0);
        $this->assertSame(StatusPropostaComercial::Aprovada, $proposta->status);

        // Mesmos 12%, porém sobre uma tabela menor: a mensalidade líquida cai de 880 para 792.
        $consultor = $this->autenticarComo('secretaria');
        $atualizada = app(RevenueManagementService::class)->atualizar($proposta, ['valor_tabela_mensal' => 900], $consultor);

        $this->assertSame(StatusPropostaComercial::AguardandoAprovacao, $atualizada->status);
        $this->assertEquals(792.00, (float) $atualizada->valor_liquido_mensal);
        $this->assertNull($atualizada->aprovado_por_user_id);
    }

    public function test_proposta_recusada_reeditada_volta_para_a_fila_de_aprovacao(): void
    {
        $consultor = $this->autenticarComo('secretaria');
        $proposta = $this->criarPropostaProcessada($consultor, 30.0);
        $this->assertSame(StatusPropostaComercial::AguardandoAprovacao, $proposta->status);

        app(RevenueManagementService::class)->recusar($proposta, $consultor, 'Desconto acima da política');
        $this->assertSame(StatusPropostaComercial::Recusada, $proposta->fresh()->status);

        $atualizada = app(RevenueManagementService::class)->atualizar($proposta->fresh(), ['desconto_solicitado' => 20], $consultor);

        $this->assertSame(StatusPropostaComercial::AguardandoAprovacao, $atualizada->status);
        $this->assertNull($atualizada->motivo_recusa);
        $this->assertNull($atualizada->aprovado_por_user_id);
    }

    public function test_status_e_aprovacao_nao_podem_ser_forcados_pelos_dados_do_formulario(): void
    {
        $consultor = $this->autenticarComo('secretaria');
        $proposta = $this->criarPropostaProcessada($consultor, 30.0);

        $atualizada = app(RevenueManagementService::class)->atualizar($proposta, [
            'responsavel_nome' => 'Maria Silva',
            'status' => StatusPropostaComercial::Aprovada->value,
            'aprovado_por_user_id' => $consultor->id,
            'nivel_alcada_necessario' => NivelAlcadaComercial::Consultor->value,
        ], $consultor);

        $this->assertSame(StatusPropostaComercial::AguardandoAprovacao, $atualizada->fresh()->status);
        $this->assertNull($atualizada->fresh()->aprovado_por_user_id);
        $this->assertSame(NivelAlcadaComercial::Diretoria, $atualizada->fresh()->nivel_alcada_necessario);
    }

    public function test_proposta_aceita_ou_convertida_bloqueia_mudanca_de_condicao_mas_permite_dados_cadastrais(): void
    {
        $admin = $this->autenticarComo('admin');
        $service = app(RevenueManagementService::class);

        foreach ([StatusPropostaComercial::AceitaPelaFamilia, StatusPropostaComercial::Convertida] as $status) {
            $proposta = $this->criarPropostaProcessada($admin, 5.0);
            $proposta->update(['status' => $status]);

            $atualizada = $service->atualizar($proposta->fresh(), ['responsavel_telefone' => '11977776666'], $admin);
            $this->assertSame('11977776666', $atualizada->fresh()->responsavel_telefone);
            $this->assertSame($status, $atualizada->fresh()->status);

            try {
                $service->atualizar($proposta->fresh(), ['desconto_solicitado' => 40], $admin);
                $this->fail("A condição comercial de uma proposta {$status->value} não pode ser alterada.");
            } catch (\DomainException $e) {
                $this->assertStringContainsString('não pode ser alterada', $e->getMessage());
            }

            $this->assertEquals(5.00, (float) $proposta->fresh()->desconto_solicitado);
            $this->assertSame($status, $proposta->fresh()->status);
        }
    }

    public function test_pagina_de_edicao_devolve_proposta_para_aprovacao_e_avisa_o_consultor(): void
    {
        $consultor = $this->autenticarComo('secretaria');
        $proposta = $this->criarPropostaProcessada($consultor, 5.0);

        Livewire::test(EditPropostaComercial::class, ['record' => $proposta->getKey()])
            ->fillForm(['desconto_solicitado' => 40])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Proposta enviada para nova aprovação');

        $proposta->refresh();
        $this->assertSame(StatusPropostaComercial::AguardandoAprovacao, $proposta->status);
        $this->assertSame(NivelAlcadaComercial::Diretoria, $proposta->nivel_alcada_necessario);
        $this->assertNull($proposta->aprovado_por_user_id);
    }

    public function test_pagina_de_edicao_bloqueia_condicao_de_proposta_convertida(): void
    {
        $admin = $this->autenticarComo('admin');
        $proposta = $this->criarPropostaProcessada($admin, 5.0);
        $proposta->update(['status' => StatusPropostaComercial::Convertida]);

        Livewire::test(EditPropostaComercial::class, ['record' => $proposta->getKey()])
            ->fillForm(['desconto_solicitado' => 40])
            ->call('save')
            ->assertNotified('Condição comercial bloqueada');

        $proposta->refresh();
        $this->assertEquals(5.00, (float) $proposta->desconto_solicitado);
        $this->assertSame(StatusPropostaComercial::Convertida, $proposta->status);
    }
}
