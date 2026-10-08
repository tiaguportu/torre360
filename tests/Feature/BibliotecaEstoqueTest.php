<?php

namespace Tests\Feature;

use App\Enums\SituacaoMatricula;
use App\Enums\StatusEmprestimo;
use App\Filament\Pages\CirculacaoBiblioteca;
use App\Filament\Resources\Emprestimos\Pages\CreateEmprestimo;
use App\Filament\Resources\Emprestimos\Pages\ListEmprestimos;
use App\Filament\Resources\Livros\Pages\CreateLivro;
use App\Filament\Resources\Livros\Pages\EditLivro;
use App\Filament\Resources\Livros\Pages\ListLivros;
use App\Models\Emprestimo;
use App\Models\Livro;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integridade do estoque de exemplares: empréstimo atômico, devolução idempotente, exclusão que não "perde"
 * exemplares e disponibilidade derivada (total − empréstimos em aberto).
 */
class BibliotecaEstoqueTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComoAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['Livro', 'Emprestimo'] as $modelo) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
                $role->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:{$modelo}", 'guard_name' => 'web']));
            }
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('admin');
        $this->actingAs($user);
        session(['active_role' => 'admin']);

        return $user;
    }

    private function livro(int $total, ?int $disponivel = null): Livro
    {
        return Livro::factory()->create([
            'quantidade_total' => $total,
            'quantidade_disponivel' => $disponivel ?? $total,
        ]);
    }

    private function emprestimoEmAberto(Livro $livro, StatusEmprestimo $status = StatusEmprestimo::Emprestado): Emprestimo
    {
        return Emprestimo::factory()->create(['livro_id' => $livro->id, 'status' => $status->value]);
    }

    // ---------------------------------------------------------------- 1. empréstimo atômico

    public function test_emprestar_reserva_o_exemplar_e_cria_o_emprestimo(): void
    {
        $livro = $this->livro(2);
        $matricula = Matricula::factory()->create();

        $emprestimo = Emprestimo::emprestar($livro->id, $matricula->id, '2026-10-01', '2026-10-15');

        $this->assertSame(1, $livro->fresh()->quantidade_disponivel);
        $this->assertSame(StatusEmprestimo::Emprestado, $emprestimo->status);
        $this->assertSame('2026-10-15', $emprestimo->data_prevista_devolucao->toDateString());
    }

    public function test_ultimo_exemplar_so_pode_ser_emprestado_uma_vez_mesmo_com_tela_desatualizada(): void
    {
        $livro = $this->livro(1);
        $matricula = Matricula::factory()->create();
        $outraMatricula = Matricula::factory()->create();

        // Segundo balcão carregou o livro antes do primeiro empréstimo: a cópia em memória ainda diz "1 disponível".
        $copiaDesatualizada = Livro::find($livro->id);

        Emprestimo::emprestar($livro->id, $matricula->id);

        $this->assertSame(1, $copiaDesatualizada->quantidade_disponivel);

        try {
            Emprestimo::emprestar($copiaDesatualizada->id, $outraMatricula->id);
            $this->fail('O último exemplar não pode ser emprestado duas vezes.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('já estão emprestados', $e->getMessage());
        }

        $this->assertSame(0, $livro->fresh()->quantidade_disponivel);
        $this->assertSame(1, Emprestimo::where('livro_id', $livro->id)->count());
    }

    public function test_emprestimo_nao_e_criado_quando_a_reserva_falha(): void
    {
        $livro = $this->livro(1, 0);

        $this->expectException(\DomainException::class);

        try {
            Emprestimo::emprestar($livro->id, Matricula::factory()->create()->id);
        } finally {
            $this->assertSame(0, Emprestimo::count());
            $this->assertSame(0, $livro->fresh()->quantidade_disponivel);
        }
    }

    public function test_livro_inexistente_e_rejeitado(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Livro não encontrado');

        Emprestimo::emprestar(999999, Matricula::factory()->create()->id);
    }

    public function test_devolucao_prevista_anterior_ao_emprestimo_e_rejeitada_sem_mexer_no_estoque(): void
    {
        $livro = $this->livro(2);

        try {
            Emprestimo::emprestar($livro->id, Matricula::factory()->create()->id, '2026-10-10', '2026-10-01');
            $this->fail('Devolução anterior ao empréstimo deveria ser rejeitada.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('anterior à data do empréstimo', $e->getMessage());
        }

        $this->assertSame(2, $livro->fresh()->quantidade_disponivel);
        $this->assertSame(0, Emprestimo::count());
    }

    public function test_data_invalida_e_rejeitada(): void
    {
        $livro = $this->livro(2);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('inválida');

        Emprestimo::emprestar($livro->id, Matricula::factory()->create()->id, null, 'não é uma data');
    }

    public function test_formulario_de_emprestimo_exige_devolucao_posterior_ao_emprestimo(): void
    {
        $this->autenticarComoAdmin();
        $livro = $this->livro(2);
        $matricula = Matricula::factory()->create();

        Livewire::test(CreateEmprestimo::class)
            ->fillForm([
                'livro_id' => $livro->id,
                'matricula_id' => $matricula->id,
                'data_emprestimo' => '2026-10-10',
                'data_prevista_devolucao' => '2026-10-01',
            ])
            ->call('create')
            ->assertHasFormErrors(['data_prevista_devolucao']);

        $this->assertSame(0, Emprestimo::count());
        $this->assertSame(2, $livro->fresh()->quantidade_disponivel);
    }

    public function test_balcao_de_circulacao_nao_cria_emprestimo_com_data_invalida(): void
    {
        $this->autenticarComoAdmin();
        $matricula = Matricula::factory()->create([
            'pessoa_id' => Pessoa::factory()->create()->id,
            'situacao' => SituacaoMatricula::ATIVA,
        ]);
        $livro = $this->livro(2);

        Livewire::test(CirculacaoBiblioteca::class)
            ->set('matricula_id', $matricula->id)
            ->set('codigo_livro_emprestimo', $livro->codigo)
            ->set('data_prevista_devolucao', '2020-01-01')
            ->call('realizarEmprestimo')
            ->assertNotified('Empréstimo não registrado');

        $this->assertSame(0, Emprestimo::count());
        $this->assertSame(2, $livro->fresh()->quantidade_disponivel);
    }

    // ---------------------------------------------------------------- 3. devolução idempotente

    public function test_devolucao_e_idempotente(): void
    {
        $livro = $this->livro(3, 1);
        $emprestimo = $this->emprestimoEmAberto($livro);

        $this->assertTrue($emprestimo->registrarDevolucao('2026-10-05'));
        $this->assertSame(2, $livro->fresh()->quantidade_disponivel);

        // Segunda chamada (duplo clique): não devolve o exemplar de novo nem mexe na data da devolução.
        $this->assertFalse($emprestimo->registrarDevolucao('2026-10-09'));
        $this->assertSame(2, $livro->fresh()->quantidade_disponivel);
        $this->assertSame('2026-10-05', $emprestimo->fresh()->data_devolucao->toDateString());
    }

    public function test_devolucao_duplicada_por_copias_desatualizadas_do_mesmo_emprestimo(): void
    {
        $livro = $this->livro(3, 1);
        $criado = $this->emprestimoEmAberto($livro);
        $balcaoA = Emprestimo::find($criado->id);
        $balcaoB = Emprestimo::find($criado->id);

        $this->assertTrue($balcaoA->registrarDevolucao());
        // O balcão B ainda tem o status "emprestado" em memória, mas o banco já diz "devolvido".
        $this->assertSame(StatusEmprestimo::Emprestado, $balcaoB->status);
        $this->assertFalse($balcaoB->registrarDevolucao());

        $this->assertSame(2, $livro->fresh()->quantidade_disponivel);
        $this->assertSame(StatusEmprestimo::Devolvido, $balcaoB->status);
    }

    public function test_devolucao_nunca_deixa_o_disponivel_passar_do_total(): void
    {
        // Estoque já divergente (disponível = total mesmo com um empréstimo em aberto).
        $livro = $this->livro(2, 2);
        $emprestimo = $this->emprestimoEmAberto($livro);

        $emprestimo->registrarDevolucao();

        $this->assertSame(2, $livro->fresh()->quantidade_disponivel);
    }

    public function test_acao_devolver_some_quando_outra_aba_ja_devolveu_e_o_estoque_fica_intacto(): void
    {
        $this->autenticarComoAdmin();
        $livro = $this->livro(3, 1);
        $emprestimo = $this->emprestimoEmAberto($livro);

        $component = Livewire::test(ListEmprestimos::class)
            ->assertTableActionVisible('devolver', $emprestimo);

        // Outra aba devolve no meio do caminho.
        Emprestimo::find($emprestimo->id)->registrarDevolucao();

        $component->assertTableActionHidden('devolver', $emprestimo);
        $this->assertSame(2, $livro->fresh()->quantidade_disponivel);
    }

    // ---------------------------------------------------------------- 2. exclusões

    public function test_excluir_emprestimo_em_aberto_devolve_o_exemplar_ao_acervo(): void
    {
        $livro = $this->livro(3, 2);
        $emAberto = $this->emprestimoEmAberto($livro);

        $emAberto->delete();

        $this->assertSame(3, $livro->fresh()->quantidade_disponivel);
    }

    public function test_excluir_emprestimo_atrasado_tambem_devolve_o_exemplar(): void
    {
        $livro = $this->livro(3, 2);
        $atrasado = $this->emprestimoEmAberto($livro, StatusEmprestimo::Atrasado);

        $atrasado->delete();

        $this->assertSame(3, $livro->fresh()->quantidade_disponivel);
    }

    public function test_excluir_emprestimo_ja_devolvido_nao_altera_o_estoque(): void
    {
        $livro = $this->livro(3, 3);
        $devolvido = $this->emprestimoEmAberto($livro, StatusEmprestimo::Devolvido);

        $devolvido->delete();

        $this->assertSame(3, $livro->fresh()->quantidade_disponivel);
    }

    public function test_exclusao_em_lote_de_emprestimos_restaura_apenas_os_em_aberto(): void
    {
        $this->autenticarComoAdmin();
        $livro = $this->livro(5, 2);
        $a = $this->emprestimoEmAberto($livro);
        $b = $this->emprestimoEmAberto($livro, StatusEmprestimo::Atrasado);
        $c = $this->emprestimoEmAberto($livro, StatusEmprestimo::Devolvido);

        Livewire::test(ListEmprestimos::class)
            ->callTableBulkAction('delete', [$a, $b, $c]);

        $this->assertSame(0, Emprestimo::count());
        $this->assertSame(4, $livro->fresh()->quantidade_disponivel);
    }

    public function test_livro_com_emprestimo_em_aberto_nao_pode_ser_excluido(): void
    {
        $livro = $this->livro(2, 1);
        $this->emprestimoEmAberto($livro);

        $this->assertFalse($livro->delete());

        $this->assertNotNull(Livro::find($livro->id));
        $this->assertSame(1, Emprestimo::count());
    }

    public function test_livro_so_com_emprestimos_devolvidos_pode_ser_excluido(): void
    {
        $livro = $this->livro(2);
        $this->emprestimoEmAberto($livro, StatusEmprestimo::Devolvido);

        $this->assertTrue($livro->delete());
        $this->assertNull(Livro::find($livro->id));
    }

    public function test_acao_excluir_do_livro_informa_o_bloqueio(): void
    {
        $this->autenticarComoAdmin();
        $livro = $this->livro(2, 1);
        $this->emprestimoEmAberto($livro);

        Livewire::test(EditLivro::class, ['record' => $livro->getKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified('Não foi possível excluir: há empréstimos em aberto desta obra. Registre as devoluções antes.');

        $this->assertNotNull(Livro::find($livro->id));
    }

    public function test_exclusao_em_lote_de_livros_mantem_os_que_tem_emprestimo_em_aberto(): void
    {
        $this->autenticarComoAdmin();
        $comEmprestimo = $this->livro(2, 1);
        $this->emprestimoEmAberto($comEmprestimo);
        $livre = $this->livro(2);

        Livewire::test(ListLivros::class)
            ->callTableBulkAction('delete', [$comEmprestimo, $livre]);

        $this->assertNotNull(Livro::find($comEmprestimo->id));
        $this->assertNull(Livro::find($livre->id));
    }

    // ---------------------------------------------------------------- 4. disponibilidade derivada

    public function test_cadastrar_livro_deixa_todos_os_exemplares_disponiveis(): void
    {
        $this->autenticarComoAdmin();

        Livewire::test(CreateLivro::class)
            ->fillForm([
                'titulo' => 'Dom Casmurro',
                'autor' => 'Machado de Assis',
                'quantidade_total' => 10,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $livro = Livro::where('titulo', 'Dom Casmurro')->firstOrFail();
        $this->assertSame(10, $livro->quantidade_total);
        $this->assertSame(10, $livro->quantidade_disponivel);
    }

    public function test_editar_o_total_reajusta_a_disponibilidade_pelos_emprestimos_em_aberto(): void
    {
        $this->autenticarComoAdmin();
        $livro = $this->livro(10, 7);
        $this->emprestimoEmAberto($livro);
        $this->emprestimoEmAberto($livro, StatusEmprestimo::Atrasado);
        $this->emprestimoEmAberto($livro);
        $this->emprestimoEmAberto($livro, StatusEmprestimo::Devolvido); // não conta

        Livewire::test(EditLivro::class, ['record' => $livro->getKey()])
            ->fillForm(['quantidade_total' => 12])
            ->call('save')
            ->assertHasNoFormErrors();

        $livro->refresh();
        $this->assertSame(12, $livro->quantidade_total);
        $this->assertSame(9, $livro->quantidade_disponivel);
    }

    public function test_salvar_o_livro_corrige_disponibilidade_divergente(): void
    {
        $this->autenticarComoAdmin();
        // Duas cópias emprestadas, mas o campo gravado diz que todas estão disponíveis.
        $livro = $this->livro(5, 5);
        $this->emprestimoEmAberto($livro);
        $this->emprestimoEmAberto($livro);

        Livewire::test(EditLivro::class, ['record' => $livro->getKey()])
            ->fillForm(['titulo' => 'Título ajustado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(3, $livro->fresh()->quantidade_disponivel);
    }

    public function test_total_nao_pode_ser_menor_que_os_emprestimos_em_aberto(): void
    {
        $this->autenticarComoAdmin();
        $livro = $this->livro(5, 2);
        $this->emprestimoEmAberto($livro);
        $this->emprestimoEmAberto($livro);
        $this->emprestimoEmAberto($livro);

        Livewire::test(EditLivro::class, ['record' => $livro->getKey()])
            ->fillForm(['quantidade_total' => 2])
            ->call('save')
            ->assertHasFormErrors(['quantidade_total']);

        $this->assertSame(5, $livro->fresh()->quantidade_total);
    }

    public function test_disponibilidade_digitada_a_mao_e_ignorada_na_edicao(): void
    {
        $this->autenticarComoAdmin();
        $livro = $this->livro(4, 4);
        $this->emprestimoEmAberto($livro);

        Livewire::test(EditLivro::class, ['record' => $livro->getKey()])
            ->fillForm(['quantidade_disponivel' => 99])
            ->call('save');

        $this->assertSame(3, $livro->fresh()->quantidade_disponivel);
    }

    public function test_recalcular_disponibilidade_do_modelo(): void
    {
        $livro = $this->livro(5, 5);
        $this->emprestimoEmAberto($livro);

        $this->assertTrue($livro->recalcularDisponibilidade());
        $this->assertSame(4, $livro->fresh()->quantidade_disponivel);
        $this->assertFalse($livro->recalcularDisponibilidade());
    }

    public function test_comando_reconciliar_lista_sem_gravar_e_corrige_com_aplicar(): void
    {
        $divergente = $this->livro(5, 5);
        $this->emprestimoEmAberto($divergente);
        $this->emprestimoEmAberto($divergente);
        $correto = $this->livro(3, 3);

        $this->artisan('biblioteca:reconciliar-disponibilidade')
            ->expectsOutputToContain('1 divergência(s)')
            ->assertSuccessful();

        $this->assertSame(5, $divergente->fresh()->quantidade_disponivel);

        $this->artisan('biblioteca:reconciliar-disponibilidade', ['--aplicar' => true])
            ->expectsOutputToContain('1 obra(s) corrigida(s)')
            ->assertSuccessful();

        $this->assertSame(3, $divergente->fresh()->quantidade_disponivel);
        $this->assertSame(3, $correto->fresh()->quantidade_disponivel);

        $this->artisan('biblioteca:reconciliar-disponibilidade')
            ->expectsOutputToContain('nenhuma divergência')
            ->assertSuccessful();
    }
}
