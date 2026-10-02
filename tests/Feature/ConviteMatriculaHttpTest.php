<?php

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Models\Curso;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\TipoVinculo;
use App\Models\Unidade;
use App\Models\User;
use App\Rules\Cpf;
use App\Services\ConviteMatriculaService;
use App\Services\InteressadoMatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConviteMatriculaHttpTest extends TestCase
{
    use RefreshDatabase;

    private function criarInteressadoComDependente(): Interessado
    {
        $status = StatusInteressado::query()->firstOrCreate(
            ['nome' => 'Novo'],
            ['is_final' => false, 'is_ganho' => false, 'ordem' => 1]
        );
        $origem = OrigemInteressado::firstOrCreate(['nome' => 'Site']);

        $unidade = Unidade::create(['nome' => 'Unidade Sede']);
        $curso = Curso::create(['nome_externo' => 'Fundamental', 'nome_interno' => 'Fundamental', 'unidade_id' => $unidade->id]);
        $serie = Serie::create(['nome' => '1º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);

        $pessoa = Pessoa::create(['nome' => 'Responsável HTTP Convite', 'email' => 'httpconvite@example.com', 'telefone' => '11988887777']);
        $interessado = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'origem_interessado_id' => $origem->id,
            'status_interessado_id' => $status->id,
        ]);

        InteressadoDependente::create([
            'interessado_id' => $interessado->id,
            'nome_crianca' => 'Criança HTTP Convite',
            'serie_id' => $serie->id,
        ]);

        return $interessado->fresh(['pessoa', 'dependentes']);
    }

    /**
     * Payload completo e válido do formulário de pré-matrícula para o interessado informado.
     *
     * @return array<string, mixed>
     */
    private function payloadValido(Interessado $interessado, array $sobrescrever = []): array
    {
        TipoVinculo::firstOrCreate(['nome' => 'Mãe']);
        $dependente = $interessado->dependentes->first();

        return array_replace_recursive([
            'responsavel' => [
                'nome' => 'Responsável HTTP Convite',
                'cpf' => '529.982.247-25',
                'data_nascimento' => '1985-04-12',
                'telefone' => '11911112222',
                'email' => 'atualizado@example.com',
                'tipo_vinculo_id' => TipoVinculo::where('nome', 'Mãe')->value('id'),
                'is_financeiro' => '1',
                'cep' => '01001-000',
                'logradouro' => 'Praça da Sé',
                'numero' => '100',
                'complemento' => 'Apto 2',
                'bairro' => 'Sé',
            ],
            'dependentes' => [
                [
                    'id' => $dependente->id,
                    'serie_id' => $dependente->serie_id,
                    'turno_preferencia' => 'Tarde',
                    'data_nascimento' => '2017-09-03',
                    'cpf' => '',
                    'sexo' => 'feminino',
                ],
            ],
            'lgpd_aceite' => '1',
        ], $sobrescrever);
    }

    public function test_pagina_do_convite_carrega_com_token_valido(): void
    {
        $interessado = $this->criarInteressadoComDependente();
        $link = app(ConviteMatriculaService::class)->gerarConvite($interessado);

        $this->get($link)->assertOk()->assertSee('Criança HTTP Convite');
    }

    public function test_pagina_do_convite_mostra_invalido_para_token_inexistente(): void
    {
        $this->get(route('captacao.interessado.convite', ['token' => 'nao-existe']))
            ->assertOk()
            ->assertSee('não é mais válido');
    }

    public function test_confirmar_convite_atualiza_dados_e_redireciona_para_sucesso(): void
    {
        $interessado = $this->criarInteressadoComDependente();
        app(ConviteMatriculaService::class)->gerarConvite($interessado);
        $dependente = $interessado->dependentes->first();

        $response = $this->post(
            route('captacao.interessado.convite.confirmar', $interessado->token_convite),
            $this->payloadValido($interessado)
        );

        $response->assertRedirect(route('captacao.interessado.convite.sucesso', $interessado->token_convite));
        $this->assertEquals('atualizado@example.com', $interessado->pessoa->fresh()->email);
        $this->assertEquals('2017-09-03', Carbon::parse($dependente->fresh()->data_nascimento)->toDateString());
        $this->assertNotNull($interessado->fresh()->token_convite_usado_em);
    }

    public function test_confirmar_convite_guarda_a_pre_matricula_completa(): void
    {
        $interessado = $this->criarInteressadoComDependente();
        app(ConviteMatriculaService::class)->gerarConvite($interessado);
        $dependente = $interessado->dependentes->first();

        $this->post(route('captacao.interessado.convite.confirmar', $interessado->token_convite), $this->payloadValido($interessado))
            ->assertSessionHasNoErrors();

        $dados = $interessado->fresh()->dados_pre_matricula;

        $this->assertSame('52998224725', $dados['responsaveis'][0]['cpf']);
        $this->assertTrue($dados['responsaveis'][0]['is_financeiro']);
        $this->assertSame(100, $dados['responsaveis'][0]['percentual']);
        $this->assertSame('Praça da Sé', $dados['responsaveis'][0]['logradouro']);
        $this->assertSame('feminino', $dados['alunos'][$dependente->id]['sexo']);
        $this->assertSame('Praça da Sé', $dados['alunos'][$dependente->id]['logradouro']);
        $this->assertNotEmpty($dados['lgpd_aceite_em']);
        $this->assertNotEmpty($dados['confirmado_em']);
    }

    public function test_segundo_responsavel_financeiro_divide_o_percentual(): void
    {
        $interessado = $this->criarInteressadoComDependente();
        app(ConviteMatriculaService::class)->gerarConvite($interessado);
        $pai = TipoVinculo::firstOrCreate(['nome' => 'Pai']);

        $this->post(route('captacao.interessado.convite.confirmar', $interessado->token_convite), $this->payloadValido($interessado, [
            'segundo_responsavel' => [
                'nome' => 'Segundo Responsável',
                'cpf' => '111.444.777-35',
                'tipo_vinculo_id' => $pai->id,
                'is_financeiro' => '1',
                'percentual' => '40',
            ],
        ]))->assertSessionHasNoErrors();

        $responsaveis = $interessado->fresh()->dados_pre_matricula['responsaveis'];

        $this->assertCount(2, $responsaveis);
        $this->assertSame(60, $responsaveis[0]['percentual']);
        $this->assertSame(40, $responsaveis[1]['percentual']);
        $this->assertSame('11144477735', $responsaveis[1]['cpf']);
    }

    public function test_confirmar_convite_exige_cpf_valido_e_aceite_lgpd(): void
    {
        $interessado = $this->criarInteressadoComDependente();
        app(ConviteMatriculaService::class)->gerarConvite($interessado);

        $payload = $this->payloadValido($interessado, ['responsavel' => ['cpf' => '111.111.111-11']]);
        unset($payload['lgpd_aceite']);

        $this->post(route('captacao.interessado.convite.confirmar', $interessado->token_convite), $payload)
            ->assertSessionHasErrors(['responsavel.cpf', 'lgpd_aceite']);

        $this->assertNull($interessado->fresh()->token_convite_usado_em);
        $this->assertNull($interessado->fresh()->dados_pre_matricula);
    }

    public function test_confirmar_convite_nao_aceita_dependente_fora_da_lista_permitida(): void
    {
        $interessado = $this->criarInteressadoComDependente();
        app(ConviteMatriculaService::class)->gerarConvite($interessado);

        $payload = $this->payloadValido($interessado);
        $payload['dependentes'][0]['id'] = 999999;

        $this->post(route('captacao.interessado.convite.confirmar', $interessado->token_convite), $payload)
            ->assertSessionHasErrors('dependentes.0.id');
    }

    public function test_reenvio_apos_uso_mostra_link_invalido(): void
    {
        $interessado = $this->criarInteressadoComDependente();
        app(ConviteMatriculaService::class)->gerarConvite($interessado);
        $token = $interessado->token_convite;

        $this->post(route('captacao.interessado.convite.confirmar', $token), $this->payloadValido($interessado));

        $this->get(route('captacao.interessado.convite', $token))
            ->assertOk()
            ->assertSee('não é mais válido');
    }

    public function test_assistente_de_matricula_recebe_os_dados_da_pre_matricula(): void
    {
        $interessado = $this->criarInteressadoComDependente();
        app(ConviteMatriculaService::class)->gerarConvite($interessado);

        $this->post(route('captacao.interessado.convite.confirmar', $interessado->token_convite), $this->payloadValido($interessado));

        $dados = InteressadoMatriculaService::dadosParaWizard($interessado->fresh());

        $this->assertSame('52998224725', $dados['responsaveis'][0]['cpf']);
        $this->assertSame($interessado->pessoa_id, $dados['responsaveis'][0]['pessoa_id_existente']);
        $this->assertSame('Praça da Sé', $dados['responsaveis'][0]['logradouro']);
        $this->assertSame('2017-09-03', $dados['alunos'][0]['data_nascimento']);
        $this->assertSame('feminino', $dados['alunos'][0]['sexo']);
        $this->assertSame('01001-000', $dados['alunos'][0]['cep']);

        InteressadoMatriculaService::registrarConversao($interessado->fresh());

        $this->assertNull($interessado->fresh()->dados_pre_matricula);
    }

    public function test_cpf_valida_digitos_verificadores(): void
    {
        $this->assertTrue(Cpf::valido('529.982.247-25'));
        $this->assertTrue(Cpf::valido('11144477735'));
        $this->assertFalse(Cpf::valido('529.982.247-24'));
        $this->assertFalse(Cpf::valido('00000000000'));
        $this->assertFalse(Cpf::valido('123'));
        $this->assertFalse(Cpf::valido(null));
    }

    public function test_admin_pode_gerar_link_de_convite_na_tabela_de_interessados(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:Interessado', 'guard_name' => 'web']);

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('super_admin');
        $this->actingAs($user);
        session(['active_role' => 'super_admin']);

        $interessado = $this->criarInteressadoComDependente();

        Livewire::test(ListInteressados::class)
            ->callTableAction('gerarConvite', $interessado)
            ->assertSuccessful();

        $this->assertNotNull($interessado->fresh()->token_convite);
    }
}
