<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CategoriaItemMaterial;
use App\Enums\StatusListaMaterial;
use App\Exceptions\ListaMaterialException;
use App\Models\Curso;
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
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Lista de material escolar (Onda 59): trava de itens de uso coletivo (Lei nº 9.870/1999, art. 1º, § 7º), publicação,
 * cópia entre períodos, visibilidade para as famílias e PDF.
 */
class ListaMaterialEscolarTest extends TestCase
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

    private function servico(): ListaMaterialService
    {
        return app(ListaMaterialService::class);
    }

    private function lista(?Serie $serie = null, ?PeriodoLetivo $periodo = null): ListaMaterialEscolar
    {
        return ListaMaterialEscolar::create(['serie_id' => ($serie ?? $this->quinto)->id, 'periodo_letivo_id' => ($periodo ?? $this->periodo)->id, 'status' => StatusListaMaterial::Rascunho]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function item(ListaMaterialEscolar $lista, string $descricao = 'Caderno brochura 96 folhas', int $quantidade = 2, array $extra = []): \App\Models\ItemListaMaterial
    {
        return $this->servico()->salvarItem($lista, ['descricao' => $descricao, 'quantidade' => $quantidade] + $extra);
    }

    // --- Trava de uso coletivo ----------------------------------------------------------------------------------------

    public function test_itens_de_uso_coletivo_evidente_sao_bloqueados_com_ou_sem_acento_e_maiuscula(): void
    {
        foreach (['Papel higiênico', 'PAPEL HIGIENICO 12 rolos', 'Sabão em pó', 'Detergente neutro', 'Álcool em gel', 'Giz branco', 'Pincel para quadro branco', 'Toner de impressora', 'Copo descartável', 'Água mineral', 'Material de limpeza'] as $descricao) {
            $this->assertSame(ListaMaterialService::BLOQUEADO, $this->servico()->avaliar($descricao)['nivel'], $descricao);
        }

        $motivo = $this->servico()->avaliar('Papel higiênico')['motivo'];

        $this->assertStringContainsString('Lei nº 9.870/1999, art. 1º, § 7º', $motivo);
    }

    public function test_giz_de_cera_e_material_individual_e_nao_e_barrado(): void
    {
        $this->assertSame(ListaMaterialService::OK, $this->servico()->avaliar('Giz de cera grosso, 12 cores')['nivel']);
        $this->assertSame(ListaMaterialService::OK, $this->servico()->avaliar('Giz pastel seco')['nivel']);
        $this->assertSame(ListaMaterialService::BLOQUEADO, $this->servico()->avaliar('Giz de lousa branco')['nivel']);
    }

    public function test_itens_comuns_passam(): void
    {
        foreach (['Caderno brochura 96 folhas', 'Lápis de cor 24 cores', 'Estojo completo', 'Livro didático adotado', 'Tesoura sem ponta', 'Cola bastão', 'Tinta guache', 'Escova de dentes e creme dental'] as $descricao) {
            $this->assertSame(ListaMaterialService::OK, $this->servico()->avaliar($descricao)['nivel'], $descricao);
        }
    }

    public function test_itens_que_podem_ser_coletivos_pedem_confirmacao(): void
    {
        foreach (['Resma de papel A4', 'Papel sulfite A4', 'Fita adesiva larga', 'Clipes coloridos', 'Sabonete líquido'] as $descricao) {
            $this->assertSame(ListaMaterialService::ATENCAO, $this->servico()->avaliar($descricao)['nivel'], $descricao);
        }
    }

    public function test_quantidade_alta_pede_confirmacao(): void
    {
        $this->assertSame(ListaMaterialService::OK, $this->servico()->avaliar('Folha de papel pardo', 29)['nivel']);
        $this->assertSame(ListaMaterialService::ATENCAO, $this->servico()->avaliar('Folha de papel pardo', 30)['nivel']);
        $this->assertStringContainsString('quantidade', $this->servico()->avaliar('Lápis preto', 40)['motivo']);
    }

    public function test_item_bloqueado_nao_e_salvo_nem_com_confirmacao(): void
    {
        $lista = $this->lista();

        try {
            $this->servico()->salvarItem($lista, ['descricao' => 'Papel higiênico', 'quantidade' => 4, 'uso_individual_confirmado' => true]);
            $this->fail('Deveria barrar o item de uso coletivo.');
        } catch (ListaMaterialException $e) {
            $this->assertStringContainsString('uso coletivo', $e->getMessage());
        }

        $this->assertSame(0, $lista->itens()->count());
    }

    public function test_item_de_atencao_so_entra_com_a_confirmacao_e_a_confirmacao_fica_registrada(): void
    {
        $lista = $this->lista();

        try {
            $this->servico()->salvarItem($lista, ['descricao' => 'Resma de papel A4', 'quantidade' => 1]);
            $this->fail('Deveria exigir a confirmação.');
        } catch (ListaMaterialException $e) {
            $this->assertStringContainsString('Marque a confirmação de uso individual', $e->getMessage());
        }

        $item = $this->servico()->salvarItem($lista, ['descricao' => 'Resma de papel A4', 'quantidade' => 1, 'uso_individual_confirmado' => true]);

        $this->assertTrue($item->uso_individual_confirmado);
    }

    public function test_confirmacao_nao_fica_marcada_em_item_comum(): void
    {
        $item = $this->item($this->lista(), 'Caderno brochura', 1, ['uso_individual_confirmado' => true]);

        $this->assertFalse($item->fresh()->uso_individual_confirmado);
    }

    public function test_editar_um_item_comum_para_algo_coletivo_e_barrado(): void
    {
        $lista = $this->lista();
        $item = $this->item($lista);

        $this->expectException(ListaMaterialException::class);

        $this->servico()->salvarItem($lista, ['descricao' => 'Material de limpeza', 'quantidade' => 1], $item);
    }

    public function test_salvar_valida_descricao_quantidade_categoria_e_normaliza_o_texto(): void
    {
        $lista = $this->lista();

        foreach ([['descricao' => 'ab', 'quantidade' => 1], ['descricao' => 'Lápis preto', 'quantidade' => 0], ['descricao' => 'Lápis preto', 'quantidade' => 1000], ['descricao' => str_repeat('a', 161), 'quantidade' => 1]] as $dados) {
            try {
                $this->servico()->salvarItem($lista, $dados);
                $this->fail('Deveria recusar: '.json_encode($dados));
            } catch (ListaMaterialException) {
                $this->addToAssertionCount(1);
            }
        }

        $item = $this->servico()->salvarItem($lista, ['descricao' => "  Lápis   preto \n nº 2 ", 'quantidade' => '12', 'categoria' => 'categoria-inexistente', 'unidade' => ' caixa ']);

        $this->assertSame('Lápis preto nº 2', $item->descricao);
        $this->assertSame(12, $item->quantidade);
        $this->assertSame(CategoriaItemMaterial::Outros, $item->categoria);
        $this->assertSame('caixa', $item->unidade);
    }

    public function test_itens_recebem_ordem_sequencial(): void
    {
        $lista = $this->lista();

        $this->item($lista, 'Lápis preto');
        $this->item($lista, 'Borracha');
        $this->item($lista, 'Régua');

        $this->assertSame(['Lápis preto', 'Borracha', 'Régua'], $lista->itens()->pluck('descricao')->all());
        $this->assertSame([1, 2, 3], $lista->itens()->pluck('ordem')->all());
    }

    // --- Publicação --------------------------------------------------------------------------------------------------

    public function test_nao_publica_lista_vazia(): void
    {
        $this->expectException(ListaMaterialException::class);
        $this->expectExceptionMessage('não tem itens');

        $this->servico()->publicar($this->lista(), null);
    }

    public function test_publicar_grava_data_e_usuario_e_registra_no_log_sem_listar_os_itens(): void
    {
        $lista = $this->lista();
        $this->item($lista, 'Caderno brochura');
        $usuario = User::factory()->create();

        $this->servico()->publicar($lista, $usuario);

        $lista->refresh();
        $this->assertTrue($lista->estaPublicada());
        $this->assertNotNull($lista->publicada_em);
        $this->assertSame($usuario->id, $lista->publicada_por_user_id);

        $log = Activity::query()->where('log_name', 'lista_material')->latest('id')->first();
        $this->assertSame('Publicou a lista de material escolar', $log->description);
        $this->assertSame(1, $log->properties['itens']);
        $this->assertSame($usuario->id, $log->causer_id);
    }

    public function test_publicar_de_novo_e_recusado(): void
    {
        $lista = $this->lista();
        $this->item($lista);
        $this->servico()->publicar($lista, null);

        $this->expectException(ListaMaterialException::class);
        $this->expectExceptionMessage('já está publicada');

        $this->servico()->publicar($lista->fresh(), null);
    }

    public function test_publicacao_revalida_itens_quando_a_configuracao_muda(): void
    {
        $lista = $this->lista();
        $this->item($lista, 'Cola quente em bastão');
        $this->item($lista, 'Caderno brochura');

        config(['material_escolar.termos_bloqueados' => [...config('material_escolar.termos_bloqueados'), 'cola quente']]);

        try {
            $this->servico()->publicar($lista, null);
            $this->fail('Deveria barrar o item que passou a ser bloqueado.');
        } catch (ListaMaterialException $e) {
            $this->assertStringContainsString('Cola quente em bastão', $e->getMessage());
        }

        $this->assertFalse($lista->fresh()->estaPublicada());
    }

    public function test_publicacao_exige_confirmacao_dos_itens_de_atencao_que_passaram_a_exigir(): void
    {
        $lista = $this->lista();
        $this->item($lista, 'Caderno brochura');

        config(['material_escolar.termos_atencao' => [...config('material_escolar.termos_atencao'), 'caderno']]);

        $this->expectException(ListaMaterialException::class);
        $this->expectExceptionMessage('precisa da confirmação');

        $this->servico()->publicar($lista, null);
    }

    public function test_lista_publicada_nao_aceita_alteracao_de_itens_ate_voltar_a_rascunho(): void
    {
        $lista = $this->lista();
        $item = $this->item($lista);
        $this->servico()->publicar($lista, null);
        $lista->refresh();

        foreach ([
            fn () => $this->servico()->salvarItem($lista, ['descricao' => 'Borracha', 'quantidade' => 1]),
            fn () => $this->servico()->salvarItem($lista, ['descricao' => 'Outro nome', 'quantidade' => 1], $item),
            fn () => $this->servico()->removerItem($item),
        ] as $tentativa) {
            try {
                $tentativa();
                $this->fail('Deveria recusar com a lista publicada.');
            } catch (ListaMaterialException $e) {
                $this->assertStringContainsString('publicada', $e->getMessage());
            }
        }

        $this->servico()->despublicar($lista, null);
        $this->servico()->salvarItem($lista->fresh(), ['descricao' => 'Borracha', 'quantidade' => 1]);

        $this->assertSame(2, $lista->itens()->count());
        $this->assertNull($lista->fresh()->publicada_em);
    }

    public function test_despublicar_so_vale_para_lista_publicada(): void
    {
        $this->expectException(ListaMaterialException::class);

        $this->servico()->despublicar($this->lista(), null);
    }

    public function test_item_de_outra_lista_nao_pode_ser_editado_por_esta(): void
    {
        $lista = $this->lista();
        $outra = $this->lista($this->sexto);
        $itemDaOutra = $this->item($outra);

        $this->expectException(ListaMaterialException::class);
        $this->expectExceptionMessage('não pertence');

        $this->servico()->salvarItem($lista, ['descricao' => 'Qualquer coisa', 'quantidade' => 1], $itemDaOutra);
    }

    // --- Cópia entre períodos ----------------------------------------------------------------------------------------

    public function test_copia_os_itens_na_ordem_e_deixa_de_fora_o_que_a_trava_atual_nao_aceita(): void
    {
        $anoPassado = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $origem = $this->lista($this->quinto, $anoPassado);
        $this->item($origem, 'Caderno brochura', 3, ['categoria' => 'papelaria', 'unidade' => 'unidade']);
        $this->item($origem, 'Resma de papel A4', 1, ['uso_individual_confirmado' => true]);
        $this->item($origem, 'Cola quente em bastão', 1);
        $this->item($origem, 'Lápis de cor', 1, ['categoria' => 'arte']);

        config(['material_escolar.termos_bloqueados' => [...config('material_escolar.termos_bloqueados'), 'cola quente']]);

        $destino = $this->lista();
        $resultado = $this->servico()->copiar($origem, $destino);

        $this->assertSame(3, $resultado['copiados']);
        $this->assertCount(1, $resultado['ignorados']);
        $this->assertStringContainsString('Cola quente', $resultado['ignorados'][0]);
        $this->assertSame(['Caderno brochura', 'Resma de papel A4', 'Lápis de cor'], $destino->itens()->pluck('descricao')->all());
        $this->assertTrue($destino->itens()->where('descricao', 'Resma de papel A4')->first()->uso_individual_confirmado);
        $this->assertSame(4, $origem->itens()->count(), 'a origem não muda');
    }

    public function test_copia_so_para_lista_vazia_em_rascunho_e_diferente_da_origem(): void
    {
        $anoPassado = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $origem = $this->lista($this->quinto, $anoPassado);
        $this->item($origem);
        $comItens = $this->lista($this->sexto);
        $this->item($comItens, 'Borracha');

        foreach ([[$origem, $comItens, 'já tem itens'], [$origem, $origem, 'outra lista']] as [$de, $para, $trecho]) {
            try {
                $this->servico()->copiar($de, $para);
                $this->fail("Deveria recusar: {$trecho}");
            } catch (ListaMaterialException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage());
            }
        }

        $publicada = $this->lista($this->quinto);
        $this->item($publicada);
        $this->servico()->publicar($publicada, null);

        $this->expectException(ListaMaterialException::class);
        $this->servico()->copiar($origem, $publicada->fresh());
    }

    // --- O que a família vê ------------------------------------------------------------------------------------------

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

    public function test_aluno_recebe_so_a_lista_publicada_da_sua_serie_e_periodo(): void
    {
        ['matricula' => $matricula] = $this->alunoComFamilia($this->quinto);
        $rascunho = $this->lista();
        $this->item($rascunho);

        $this->assertNull($this->servico()->listaPublicadaDoAluno($matricula->fresh()), 'rascunho não aparece');

        $this->servico()->publicar($rascunho, null);
        $this->item($this->lista($this->sexto), 'Item do sexto ano');

        $lista = $this->servico()->listaPublicadaDoAluno($matricula->fresh());

        $this->assertNotNull($lista);
        $this->assertSame($rascunho->id, $lista->id);
        $this->assertSame(['Caderno brochura 96 folhas'], $lista->itens->pluck('descricao')->all());
    }

    public function test_familia_ve_a_lista_publicada_do_proprio_aluno_e_nao_a_de_outra_serie(): void
    {
        ['familia' => $familiaQuinto] = $this->alunoComFamilia($this->quinto);
        ['familia' => $familiaSexto] = $this->alunoComFamilia($this->sexto, 'Bia Souza');
        $lista = $this->lista();
        $this->item($lista);

        $this->assertFalse($this->servico()->familiaPodeVer($familiaQuinto, $lista), 'rascunho não é visto');

        $this->servico()->publicar($lista, null);
        $lista->refresh();

        $this->assertTrue($this->servico()->familiaPodeVer($familiaQuinto, $lista));
        $this->assertFalse($this->servico()->familiaPodeVer($familiaSexto, $lista));
    }

    public function test_series_sem_lista_publicada_no_periodo(): void
    {
        $lista = $this->lista();
        $this->item($lista);
        $this->lista($this->sexto); // rascunho vazio: não conta
        $this->servico()->publicar($lista, null);

        $semLista = $this->servico()->seriesSemListaPublicada($this->periodo->id, [$this->quinto->id, $this->sexto->id]);

        $this->assertSame(['6º Ano'], $semLista->pluck('nome')->all());
    }

    public function test_itens_agrupados_por_categoria_na_ordem_do_enum_sem_categoria_vazia(): void
    {
        $lista = $this->lista();
        $this->item($lista, 'Lápis de cor', 1, ['categoria' => 'arte']);
        $this->item($lista, 'Caderno brochura', 1, ['categoria' => 'papelaria']);
        $this->item($lista, 'Livro didático', 1, ['categoria' => 'livros']);
        $this->item($lista, 'Caneta azul', 1, ['categoria' => 'papelaria']);

        $grupos = $this->servico()->itensPorCategoria($lista->fresh());

        $this->assertSame(['Papelaria', 'Livros e apostilas', 'Arte e artesanato'], $grupos->keys()->all());
        $this->assertSame(['Caderno brochura', 'Caneta azul'], $grupos['Papelaria']->pluck('descricao')->all());
    }

    // --- PDF ---------------------------------------------------------------------------------------------------------

    private function equipe(array $permissoes, string $papel = 'secretaria'): User
    {
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

    public function test_familia_baixa_o_pdf_da_lista_publicada_do_seu_aluno(): void
    {
        ['familia' => $familia] = $this->alunoComFamilia($this->quinto);
        $lista = $this->lista();
        $this->item($lista);
        $this->servico()->publicar($lista, null);

        $resposta = $this->actingAs($familia)->get(route('lista-material.pdf', $lista));

        $resposta->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $resposta->headers->get('content-type'));
    }

    public function test_pdf_negado_para_outra_familia_para_rascunho_e_para_visitante(): void
    {
        ['familia' => $familia] = $this->alunoComFamilia($this->quinto);
        ['familia' => $outra] = $this->alunoComFamilia($this->sexto, 'Bia Souza');
        $lista = $this->lista();
        $this->item($lista);

        $this->actingAs($familia)->get(route('lista-material.pdf', $lista))->assertForbidden();

        $this->servico()->publicar($lista, null);

        $this->actingAs($outra)->get(route('lista-material.pdf', $lista))->assertForbidden();
        $this->actingAs($this->equipe([], 'professor'))->get(route('lista-material.pdf', $lista))->assertForbidden();

        auth()->logout();
        $this->get(route('lista-material.pdf', $lista))->assertRedirect();
    }

    public function test_equipe_com_permissao_abre_o_pdf_ate_do_rascunho(): void
    {
        $lista = $this->lista();
        $this->item($lista);

        $this->actingAs($this->equipe(['View:ListaMaterialEscolar']))
            ->get(route('lista-material.pdf', $lista))
            ->assertOk();
    }

    // --- Banco -------------------------------------------------------------------------------------------------------

    public function test_estrutura_do_banco_garante_uma_lista_por_serie_e_periodo(): void
    {
        $this->lista();

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->lista();
    }
}
