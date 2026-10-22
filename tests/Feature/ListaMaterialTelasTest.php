<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusListaMaterial;
use App\Filament\Portal\Pages\ListaDeMaterial;
use App\Filament\Resources\ListaMaterialEscolars\Pages\CreateListaMaterialEscolar;
use App\Filament\Resources\ListaMaterialEscolars\Pages\EditListaMaterialEscolar;
use App\Filament\Resources\ListaMaterialEscolars\Pages\ListListaMaterialEscolars;
use App\Filament\Resources\ListaMaterialEscolars\RelationManagers\ItensRelationManager;
use App\Models\Curso;
use App\Models\ItemListaMaterial;
use App\Models\ListaMaterialEscolar;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\ListaMaterialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Telas da lista de material (Onda 59): listagem, criação, edição com publicação, gerenciador de itens com a trava e a
 * página do Portal da Família.
 */
class ListaMaterialTelasTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private PeriodoLetivo $periodo;

    private Serie $quinto;

    private Serie $sexto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->periodo = PeriodoLetivo::create(['nome' => '2027', 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15']);
        $unidade = Unidade::create(['nome' => 'Sede']);
        $curso = Curso::create(['unidade_id' => $unidade->id, 'nome_externo' => 'Fundamental', 'nome_interno' => 'EF']);
        $this->quinto = Serie::create(['nome' => '5º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $this->sexto = Serie::create(['nome' => '6º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
    }

    private function secretaria(?array $permissoes = null, string $papel = 'secretaria'): User
    {
        $permissoes ??= ['ViewAny:ListaMaterialEscolar', 'View:ListaMaterialEscolar', 'Create:ListaMaterialEscolar', 'Update:ListaMaterialEscolar', 'Delete:ListaMaterialEscolar', 'Publicar:ListaMaterialEscolar'];

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $role = Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        foreach ($permissoes as $permissao) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole($papel);
        session(['active_role' => $papel]);

        return $user;
    }

    private function lista(?Serie $serie = null, ?PeriodoLetivo $periodo = null): ListaMaterialEscolar
    {
        return ListaMaterialEscolar::create(['serie_id' => ($serie ?? $this->quinto)->id, 'periodo_letivo_id' => ($periodo ?? $this->periodo)->id, 'status' => StatusListaMaterial::Rascunho]);
    }

    private function item(ListaMaterialEscolar $lista, string $descricao = 'Caderno brochura 96 folhas', int $quantidade = 2): ItemListaMaterial
    {
        return app(ListaMaterialService::class)->salvarItem($lista, ['descricao' => $descricao, 'quantidade' => $quantidade]);
    }

    // --- Listagem e criação ------------------------------------------------------------------------------------------

    public function test_secretaria_ve_a_listagem_e_a_coordenacao_so_consulta(): void
    {
        $lista = $this->lista();
        $this->item($lista);

        Livewire::actingAs($this->secretaria())
            ->test(ListListaMaterialEscolars::class)
            ->assertActionExists('ajuda')
            ->assertCanSeeTableRecords([$lista])
            ->assertSee('5º Ano')
            ->assertSee('Rascunho');

        $coordenacao = $this->secretaria(['ViewAny:ListaMaterialEscolar', 'View:ListaMaterialEscolar'], 'coordenador');

        Livewire::actingAs($coordenacao)->test(ListListaMaterialEscolars::class)->assertCanSeeTableRecords([$lista])->assertActionHidden('create');
    }

    public function test_quem_nao_tem_permissao_nao_abre_a_listagem_nem_a_criacao(): void
    {
        $semPermissao = $this->secretaria([], 'professor');

        Livewire::actingAs($semPermissao)->test(ListListaMaterialEscolars::class)->assertForbidden();
        Livewire::actingAs($semPermissao)->test(CreateListaMaterialEscolar::class)->assertForbidden();
    }

    public function test_cria_a_lista_em_rascunho_e_abre_a_edicao(): void
    {
        $secretaria = $this->secretaria();

        Livewire::actingAs($secretaria)
            ->test(CreateListaMaterialEscolar::class)
            ->assertActionExists('ajuda')
            ->fillForm(['serie_id' => $this->quinto->id, 'periodo_letivo_id' => $this->periodo->id, 'observacao' => 'Reaproveite o material do ano passado.'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $lista = ListaMaterialEscolar::first();

        $this->assertSame(StatusListaMaterial::Rascunho, $lista->status);
        $this->assertSame($secretaria->id, $lista->criada_por_user_id);
        $this->assertSame('Reaproveite o material do ano passado.', $lista->observacao);
    }

    public function test_segunda_lista_da_mesma_serie_e_periodo_nao_e_criada(): void
    {
        $this->lista();

        Livewire::actingAs($this->secretaria())
            ->test(CreateListaMaterialEscolar::class)
            ->fillForm(['serie_id' => $this->quinto->id, 'periodo_letivo_id' => $this->periodo->id])
            ->call('create')
            ->assertNotified('Já existe uma lista para esta série e período');

        $this->assertSame(1, ListaMaterialEscolar::count());
    }

    // --- Edição e publicação -----------------------------------------------------------------------------------------

    private function edicao(ListaMaterialEscolar $lista, ?User $user = null)
    {
        return Livewire::actingAs($user ?? $this->secretaria())->test(EditListaMaterialEscolar::class, ['record' => $lista->getRouteKey()]);
    }

    public function test_publicar_sem_itens_mostra_o_motivo_e_nao_publica(): void
    {
        $lista = $this->lista();

        $this->edicao($lista)->callAction('publicar')->assertNotified('Não foi possível publicar');

        $this->assertFalse($lista->fresh()->estaPublicada());
    }

    public function test_publica_e_volta_para_rascunho_pela_tela(): void
    {
        $lista = $this->lista();
        $this->item($lista);
        $secretaria = $this->secretaria();

        $this->edicao($lista, $secretaria)->assertActionVisible('publicar')->assertActionHidden('despublicar')->callAction('publicar')->assertNotified('Lista publicada');

        $lista->refresh();
        $this->assertTrue($lista->estaPublicada());
        $this->assertSame($secretaria->id, $lista->publicada_por_user_id);

        $this->edicao($lista, $secretaria)->assertActionHidden('publicar')->assertActionVisible('despublicar')->callAction('despublicar')->assertNotified('Lista em rascunho');

        $this->assertFalse($lista->fresh()->estaPublicada());
    }

    public function test_coordenacao_nao_publica_nem_edita(): void
    {
        $lista = $this->lista();
        $this->item($lista);
        $coordenacao = $this->secretaria(['ViewAny:ListaMaterialEscolar', 'View:ListaMaterialEscolar'], 'coordenador');

        Livewire::actingAs($coordenacao)->test(EditListaMaterialEscolar::class, ['record' => $lista->getRouteKey()])->assertForbidden();
    }

    public function test_publicada_nao_pode_ser_apagada_nem_ter_itens_alterados_mas_a_edicao_continua_abrindo(): void
    {
        $lista = $this->lista();
        $this->item($lista);
        $secretaria = $this->secretaria();

        $this->assertTrue($secretaria->can('delete', $lista));
        $this->assertTrue($secretaria->can('editarItens', $lista));

        app(ListaMaterialService::class)->publicar($lista, $secretaria);
        $lista->refresh();

        $this->assertFalse($secretaria->can('delete', $lista));
        $this->assertFalse($secretaria->can('editarItens', $lista));
        $this->assertTrue($secretaria->can('update', $lista), 'a tela abre para poder voltar a lista para rascunho');
        $this->assertTrue($secretaria->can('publicar', $lista));

        $this->edicao($lista, $secretaria)->assertSuccessful()->assertActionHidden('delete')->assertActionHidden('copiar');
    }

    public function test_super_admin_tambem_nao_altera_itens_nem_apaga_lista_publicada(): void
    {
        $lista = $this->lista();
        $item = $this->item($lista);
        $superAdmin = $this->secretaria([], 'super_admin');
        app(ListaMaterialService::class)->publicar($lista, $superAdmin);

        $this->edicao($lista->fresh(), $superAdmin)->assertActionHidden('delete');
        $this->itens($lista->fresh(), $superAdmin)->assertTableActionHidden('adicionar')->assertTableActionHidden('editar', $item)->assertTableActionHidden('remover', $item);
    }

    public function test_copia_os_itens_de_outra_lista_pela_tela(): void
    {
        $anterior = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $origem = $this->lista($this->quinto, $anterior);
        $this->item($origem, 'Caderno brochura', 3);
        $this->item($origem, 'Régua 30 cm', 1);
        $destino = $this->lista();

        $this->edicao($destino)
            ->callAction('copiar', ['origem_id' => $origem->id])
            ->assertNotified('2 item(ns) copiado(s)');

        $this->assertSame(['Caderno brochura', 'Régua 30 cm'], $destino->itens()->pluck('descricao')->all());
    }

    public function test_copiar_para_lista_com_itens_e_recusado_com_o_motivo(): void
    {
        $outra = $this->lista($this->sexto);
        $this->item($outra);
        $destino = $this->lista();
        $this->item($destino, 'Borracha', 1);

        $this->edicao($destino)->callAction('copiar', ['origem_id' => $outra->id])->assertNotified('Não foi possível copiar');

        $this->assertSame(1, $destino->itens()->count());
    }

    // --- Gerenciador de itens ----------------------------------------------------------------------------------------

    private function itens(ListaMaterialEscolar $lista, ?User $user = null)
    {
        return Livewire::actingAs($user ?? $this->secretaria())->test(ItensRelationManager::class, ['ownerRecord' => $lista, 'pageClass' => EditListaMaterialEscolar::class]);
    }

    public function test_adiciona_item_comum(): void
    {
        $lista = $this->lista();

        $this->itens($lista)
            ->callTableAction('adicionar', data: ['descricao' => 'Caderno brochura 96 folhas', 'quantidade' => 2, 'unidade' => 'unidade', 'categoria' => 'papelaria', 'observacao' => 'Capa dura'])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Item adicionado');

        $item = $lista->itens()->first();
        $this->assertSame('Caderno brochura 96 folhas', $item->descricao);
        $this->assertSame(2, $item->quantidade);
        $this->assertSame('Capa dura', $item->observacao);
    }

    public function test_formulario_barra_item_de_uso_coletivo_com_a_mensagem_da_lei(): void
    {
        $lista = $this->lista();

        $this->itens($lista)
            ->callTableAction('adicionar', data: ['descricao' => 'Papel higiênico', 'quantidade' => 4, 'categoria' => 'outros'])
            ->assertHasTableActionErrors(['descricao']);

        $this->assertSame(0, $lista->itens()->count());
    }

    public function test_formulario_exige_a_confirmacao_para_item_de_atencao(): void
    {
        $lista = $this->lista();

        $this->itens($lista)
            ->callTableAction('adicionar', data: ['descricao' => 'Resma de papel A4', 'quantidade' => 1, 'categoria' => 'papelaria', 'uso_individual_confirmado' => false])
            ->assertHasTableActionErrors(['uso_individual_confirmado']);

        $this->assertSame(0, $lista->itens()->count());

        $this->itens($lista)
            ->callTableAction('adicionar', data: ['descricao' => 'Resma de papel A4', 'quantidade' => 1, 'categoria' => 'papelaria', 'uso_individual_confirmado' => true])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($lista->itens()->first()->uso_individual_confirmado);
    }

    public function test_edita_e_remove_item(): void
    {
        $lista = $this->lista();
        $item = $this->item($lista);

        $tela = $this->itens($lista);

        $tela
            ->callTableAction('editar', $item, data: ['descricao' => 'Caderno brochura 200 folhas', 'quantidade' => 1, 'unidade' => null, 'categoria' => 'papelaria', 'observacao' => null])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Item atualizado');

        $this->assertSame('Caderno brochura 200 folhas', $item->fresh()->descricao);

        $tela->callTableAction('remover', $item)->assertNotified('Item removido');

        $this->assertSame(0, $lista->itens()->count());
    }

    public function test_lista_publicada_esconde_as_acoes_de_itens(): void
    {
        $lista = $this->lista();
        $item = $this->item($lista);
        app(ListaMaterialService::class)->publicar($lista, null);

        $this->itens($lista->fresh())
            ->assertTableActionHidden('adicionar')
            ->assertTableActionHidden('editar', $item)
            ->assertTableActionHidden('remover', $item);
    }

    public function test_coordenacao_ve_os_itens_sem_poder_alterar(): void
    {
        $lista = $this->lista();
        $item = $this->item($lista);
        $coordenacao = $this->secretaria(['ViewAny:ListaMaterialEscolar', 'View:ListaMaterialEscolar'], 'coordenador');

        $this->itens($lista, $coordenacao)
            ->assertCanSeeTableRecords([$item])
            ->assertTableActionHidden('adicionar')
            ->assertTableActionHidden('editar', $item);
    }

    // --- Portal da Família -------------------------------------------------------------------------------------------

    /**
     * @return array{matricula: Matricula, familia: User}
     */
    private function alunoComFamilia(Serie $serie, string $nome = 'Ana Lima'): array
    {
        $turma = Turma::create(['nome' => "{$serie->nome} A", 'serie_id' => $serie->id, 'periodo_letivo_id' => $this->periodo->id]);
        $matricula = Matricula::create(['pessoa_id' => Pessoa::create(['nome' => $nome])->id, 'turma_id' => $turma->id, 'situacao' => 'ativa']);

        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);
        $responsavel = Pessoa::create(['nome' => 'Responsável de '.$nome]);
        $user = User::factory()->create(['activated_at' => now(), 'email_verified_at' => now()]);
        $responsavel->users()->save($user);
        $user->assignRole('responsavel');
        $responsavel->alunos()->attach($matricula->pessoa_id);

        return ['matricula' => $matricula, 'familia' => $user];
    }

    public function test_portal_mostra_a_lista_publicada_da_serie_do_aluno_agrupada_por_categoria(): void
    {
        ['familia' => $familia] = $this->alunoComFamilia($this->quinto);
        $lista = $this->lista();
        $lista->update(['observacao' => 'Reaproveite o que estiver em bom estado.']);
        app(ListaMaterialService::class)->salvarItem($lista, ['descricao' => 'Caderno brochura 96 folhas', 'quantidade' => 3, 'categoria' => 'papelaria']);
        app(ListaMaterialService::class)->salvarItem($lista, ['descricao' => 'Lápis de cor 24 cores', 'quantidade' => 1, 'categoria' => 'arte']);
        app(ListaMaterialService::class)->publicar($lista, null);
        $outra = $this->lista($this->sexto);
        app(ListaMaterialService::class)->salvarItem($outra, ['descricao' => 'Item exclusivo do sexto ano', 'quantidade' => 1]);
        app(ListaMaterialService::class)->publicar($outra, null);

        Livewire::actingAs($familia)
            ->test(ListaDeMaterial::class)
            ->assertActionExists('ajuda')
            ->assertSee('5º Ano')
            ->assertSee('Reaproveite o que estiver em bom estado.')
            ->assertSeeInOrder(['Papelaria', 'Caderno brochura 96 folhas', 'Arte e artesanato', 'Lápis de cor 24 cores'])
            ->assertSee('Baixar a lista (PDF)')
            ->assertSee('não exige marca, loja nem kit fechado')
            ->assertDontSee('Item exclusivo do sexto ano');
    }

    public function test_portal_nao_mostra_rascunho_e_avisa_que_ainda_nao_foi_publicada(): void
    {
        ['familia' => $familia] = $this->alunoComFamilia($this->quinto);
        $lista = $this->lista();
        $this->item($lista, 'Item de rascunho');

        Livewire::actingAs($familia)
            ->test(ListaDeMaterial::class)
            ->assertDontSee('Item de rascunho')
            ->assertSee('ainda não publicou a lista de material');
    }

    public function test_portal_com_dois_alunos_troca_a_lista_pelo_seletor(): void
    {
        ['familia' => $familia, 'matricula' => $ana] = $this->alunoComFamilia($this->quinto);
        $bia = Matricula::create([
            'pessoa_id' => Pessoa::create(['nome' => 'Bia Lima'])->id,
            'turma_id' => Turma::create(['nome' => '6º Ano A', 'serie_id' => $this->sexto->id, 'periodo_letivo_id' => $this->periodo->id])->id,
            'situacao' => 'ativa',
        ]);
        $familia->pessoa->alunos()->attach($bia->pessoa_id);

        foreach ([[$this->quinto, 'Caderno do quinto ano'], [$this->sexto, 'Caderno do sexto ano']] as [$serie, $descricao]) {
            $lista = $this->lista($serie);
            app(ListaMaterialService::class)->salvarItem($lista, ['descricao' => $descricao, 'quantidade' => 1]);
            app(ListaMaterialService::class)->publicar($lista, null);
        }

        Livewire::actingAs($familia)
            ->test(ListaDeMaterial::class)
            ->set('matriculaId', $ana->id)
            ->assertSee('Caderno do quinto ano')
            ->assertDontSee('Caderno do sexto ano')
            ->set('matriculaId', $bia->id)
            ->assertSee('Caderno do sexto ano')
            ->assertDontSee('Caderno do quinto ano');
    }
}
