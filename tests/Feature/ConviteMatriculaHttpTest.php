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
use App\Models\Unidade;
use App\Models\User;
use App\Services\ConviteMatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $response = $this->post(route('captacao.interessado.convite.confirmar', $interessado->token_convite), [
            'telefone' => '11911112222',
            'email' => 'atualizado@example.com',
            'dependentes' => [
                ['id' => $dependente->id, 'serie_id' => $dependente->serie_id, 'turno_preferencia' => 'Tarde'],
            ],
        ]);

        $response->assertRedirect(route('captacao.interessado.convite.sucesso', $interessado->token_convite));
        $this->assertEquals('atualizado@example.com', $interessado->pessoa->fresh()->email);
        $this->assertNotNull($interessado->fresh()->token_convite_usado_em);
    }

    public function test_confirmar_convite_nao_aceita_dependente_fora_da_lista_permitida(): void
    {
        $interessado = $this->criarInteressadoComDependente();
        app(ConviteMatriculaService::class)->gerarConvite($interessado);

        $response = $this->post(route('captacao.interessado.convite.confirmar', $interessado->token_convite), [
            'dependentes' => [
                ['id' => 999999],
            ],
        ]);

        $response->assertSessionHasErrors('dependentes.0.id');
    }

    public function test_reenvio_apos_uso_mostra_link_invalido(): void
    {
        $interessado = $this->criarInteressadoComDependente();
        app(ConviteMatriculaService::class)->gerarConvite($interessado);
        $dependente = $interessado->dependentes->first();
        $token = $interessado->token_convite;

        $this->post(route('captacao.interessado.convite.confirmar', $token), [
            'dependentes' => [['id' => $dependente->id]],
        ]);

        $this->get(route('captacao.interessado.convite', $token))
            ->assertOk()
            ->assertSee('não é mais válido');
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
