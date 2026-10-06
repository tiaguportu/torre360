<?php

namespace Tests\Feature;

use App\Enums\StatusAcordoInadimplencia;
use App\Filament\Resources\AcordoInadimplencias\Pages\ListAcordoInadimplencias;
use App\Models\AcordoInadimplencia;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use App\Services\AcordoInadimplenciaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AcordoInadimplenciaTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComo(string $roleName = 'admin'): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $perms = [
            'ViewAny:AcordoInadimplencia',
            'View:AcordoInadimplencia',
            'Create:AcordoInadimplencia',
            'Update:AcordoInadimplencia',
            'Delete:AcordoInadimplencia',
            'DeleteAny:AcordoInadimplencia',
            'Aprovar:AcordoInadimplencia',
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

    private function criarMatriculaComResponsavel(): array
    {
        $aluno = Pessoa::create([
            'nome' => 'Aluno Inadimplente Teste',
            'data_nascimento' => '2012-05-10',
        ]);

        $responsavel = Pessoa::create([
            'nome' => 'Responsável Financeiro Teste',
            'cpf' => '12345678901',
            'telefone' => '11988887777',
            'email' => 'responsavel@exemplo.com',
        ]);

        $unidade = Unidade::create(['nome' => 'Unidade Teste', 'situacao_funcionamento' => '1']);
        $curso = Curso::create(['unidade_id' => $unidade->id, 'nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF']);
        $serie = Serie::create(['curso_id' => $curso->id, 'nome' => '7º Ano', 'sistema_avaliacao' => 'Nota']);
        $turno = Turno::create(['nome' => 'Manhã', 'hora_inicio' => '07:00:00', 'hora_fim' => '12:00:00']);
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'ano' => 2026, 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);

        $turma = Turma::create([
            'nome' => 'Turma 701',
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
            'vagas_maximas' => 30,
        ]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'serie_id' => $serie->id,
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $periodo->id,
            'situacao' => 'ativa',
        ]);

        return [$matricula, $responsavel];
    }

    public function test_simulacao_de_acordo_calcula_valores_e_parcelas_corretamente(): void
    {
        $service = app(AcordoInadimplenciaService::class);

        // R$ 3.000 original + R$ 300 juros/multa, com 100% de desconto sobre encargos e entrada de R$ 600 em 4x
        $simulacao = $service->simularAcordo(
            valorOriginal: 3000.00,
            valorMulta: 100.00,
            valorJuros: 200.00,
            percentualDesconto: 100.0,
            valorEntrada: 600.00,
            quantidadeParcelas: 4,
            diaVencimento: 10,
            primeiroVencimento: '2026-11-10'
        );

        // Desconto = 300 (100% dos encargos)
        $this->assertEquals(300.00, $simulacao['valor_desconto']);
        // Valor final = 3000
        $this->assertEquals(3000.00, $simulacao['valor_total_acordo']);
        // Saldo a parcelar = 3000 - 600 = 2400 / 4 parcelas = 600 cada
        $this->assertEquals(600.00, $simulacao['valor_parcela']);
        $this->assertCount(5, $simulacao['cronograma_parcelas']); // 1 entrada + 4 parcelas
    }

    public function test_criacao_de_acordo_persiste_parcelas_e_minuta_juridica(): void
    {
        $user = $this->autenticarComo('admin');
        [$matricula, $responsavel] = $this->criarMatriculaComResponsavel();

        $service = app(AcordoInadimplenciaService::class);

        $acordo = $service->criarAcordo([
            'matricula_id' => $matricula->id,
            'responsavel_pessoa_id' => $responsavel->id,
            'valor_original_total' => 2000.00,
            'valor_multa_original' => 100.00,
            'valor_juros_original' => 100.00,
            'percentual_desconto_concedido' => 50.0,
            'valor_entrada' => 500.00,
            'quantidade_parcelas' => 3,
            'dia_vencimento_parcelas' => 10,
            'primeiro_vencimento' => '2026-11-10',
        ], $user);

        $this->assertNotNull($acordo->id);
        $this->assertEquals(StatusAcordoInadimplencia::AguardandoAceite, $acordo->status);
        $this->assertStringContainsString('Art. 784, inciso III', $acordo->termo_confissao_texto);
        $this->assertStringContainsString($responsavel->nome, $acordo->termo_confissao_texto);

        // 1 entrada + 3 parcelas = 4 registros
        $this->assertCount(4, $acordo->parcelas);
    }

    public function test_aceite_online_do_acordo_pela_familia(): void
    {
        $user = $this->autenticarComo('admin');
        [$matricula, $responsavel] = $this->criarMatriculaComResponsavel();

        $service = app(AcordoInadimplenciaService::class);

        $acordo = $service->criarAcordo([
            'matricula_id' => $matricula->id,
            'responsavel_pessoa_id' => $responsavel->id,
            'valor_original_total' => 1500.00,
            'quantidade_parcelas' => 2,
            'primeiro_vencimento' => '2026-11-10',
        ], $user);

        // Página pública carrega com sucesso
        $this->get(route('acordo.publico.show', ['token' => $acordo->token_publico]))
            ->assertSuccessful()
            ->assertSee($acordo->codigo)
            ->assertSee($responsavel->nome);

        // Aceite do acordo
        $response = $this->post(route('acordo.publico.aceitar', ['token' => $acordo->token_publico]), [
            'concordo' => '1',
        ]);

        $response->assertRedirect(route('acordo.publico.show', ['token' => $acordo->token_publico]));

        $acordo->refresh();
        $this->assertEquals(StatusAcordoInadimplencia::Ativo, $acordo->status);
        $this->assertNotNull($acordo->aceito_em);
    }

    public function test_baixa_de_parcelas_e_quitacao_integral_do_acordo(): void
    {
        $user = $this->autenticarComo('admin');
        [$matricula, $responsavel] = $this->criarMatriculaComResponsavel();

        $service = app(AcordoInadimplenciaService::class);

        $acordo = $service->criarAcordo([
            'matricula_id' => $matricula->id,
            'responsavel_pessoa_id' => $responsavel->id,
            'valor_original_total' => 1000.00,
            'valor_entrada' => 0.00,
            'quantidade_parcelas' => 2,
            'primeiro_vencimento' => '2026-11-10',
        ], $user);

        $parcelas = $acordo->parcelas;
        $this->assertCount(2, $parcelas);

        // Paga a primeira parcela
        $service->registrarPagamentoParcela($parcelas[0], (float) $parcelas[0]->valor, 'pix');
        $acordo->refresh();
        $this->assertNotEquals(StatusAcordoInadimplencia::Cumprido, $acordo->status);

        // Paga a segunda parcela
        $service->registrarPagamentoParcela($parcelas[1], (float) $parcelas[1]->valor, 'pix');
        $acordo->refresh();
        $this->assertEquals(StatusAcordoInadimplencia::Cumprido, $acordo->status);
    }

    public function test_listagem_de_acordos_no_filament_carrega_com_sucesso(): void
    {
        $user = $this->autenticarComo('admin');
        [$matricula, $responsavel] = $this->criarMatriculaComResponsavel();

        $acordo = AcordoInadimplencia::create([
            'codigo' => AcordoInadimplencia::gerarCodigo(),
            'matricula_id' => $matricula->id,
            'responsavel_pessoa_id' => $responsavel->id,
            'criado_por_user_id' => $user->id,
            'valor_original_total' => 1200.00,
            'valor_total_acordo' => 1200.00,
            'quantidade_parcelas' => 3,
            'valor_parcela' => 400.00,
            'primeiro_vencimento' => '2026-11-10',
            'token_publico' => AcordoInadimplencia::gerarTokenPublico(),
            'status' => StatusAcordoInadimplencia::AguardandoAceite,
        ]);

        Livewire::test(ListAcordoInadimplencias::class)
            ->assertSuccessful()
            ->assertSee($acordo->codigo);
    }
}
