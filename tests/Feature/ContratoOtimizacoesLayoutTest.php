<?php

namespace Tests\Feature;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Filament\Resources\Contratos\Pages\EditContrato;
use App\Filament\Resources\Contratos\Pages\ListContratos;
use App\Filament\Resources\Contratos\Tables\ContratosTable;
use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\TemplateContrato;
use App\Models\TipoVinculo;
use App\Models\User;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use ReflectionClass;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContratoOtimizacoesLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        TipoVinculo::firstOrCreate(['nome' => 'Pai']);
        TipoVinculo::firstOrCreate(['nome' => 'Mãe']);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $permissoes = [
            'ViewAny:Contrato', 'View:Contrato', 'Create:Contrato', 'Update:Contrato', 'Delete:Contrato',
            'DeleteAny:Contrato', 'Import:Contrato', 'Export:Contrato',
        ];
        foreach ($permissoes as $p) {
            $perm = Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $role->givePermissionTo($perm);
        }

        $this->adminUser = User::factory()->create(['email' => 'admin.test@example.com']);
        $this->adminUser->assignRole($role);
        $this->actingAs($this->adminUser);
    }

    public function test_badge_executa_numero_fixo_de_consultas_com_um_e_com_n_contratos(): void
    {
        $respEmail = 'resp.badge@example.com';
        $user = User::factory()->create(['email' => $respEmail]);
        $pessoa = Pessoa::factory()->create(['nome' => 'Responsável Badge', 'email' => $respEmail]);
        $pessoa->users()->save($user);

        $template = TemplateContrato::create([
            'nome' => 'Template Badge',
            'conteudo' => '<p>Conteúdo</p>',
            'is_padrao' => false,
        ]);

        // Cria 1 contrato pendente
        $aluno1 = Pessoa::factory()->create(['nome' => 'Aluno 1']);
        $mat1 = Matricula::factory()->create(['pessoa_id' => $aluno1->id]);
        $c1 = Contrato::create([
            'valor_total' => 1000.00,
            'matricula_id' => $mat1->id,
            'template_contrato_id' => $template->id,
            'assinafy_status' => 'pending',
        ]);
        $c1->responsaveisFinanceiros()->create(['pessoa_id' => $pessoa->id, 'percentual' => 100]);

        $this->actingAs($user);

        // Warm-up de inicialização (lazy caches, permissões, IDs de vínculos)
        ContratoResource::getNavigationBadge();

        // Mede com 1 contrato
        DB::flushQueryLog();
        DB::enableQueryLog();
        $badge1 = ContratoResource::getNavigationBadge();
        $queriesCom1 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertEquals('1', $badge1);

        // Cria mais 4 contratos pendentes para o mesmo usuário (total 5)
        for ($i = 2; $i <= 5; $i++) {
            $alunoN = Pessoa::factory()->create(['nome' => "Aluno {$i}"]);
            $matN = Matricula::factory()->create(['pessoa_id' => $alunoN->id]);
            $cN = Contrato::create([
                'valor_total' => 1000.00 * $i,
                'matricula_id' => $matN->id,
                'template_contrato_id' => $template->id,
                'assinafy_status' => 'pending',
            ]);
            $cN->responsaveisFinanceiros()->create(['pessoa_id' => $pessoa->id, 'percentual' => 100]);
        }

        // Mede com 5 contratos
        DB::flushQueryLog();
        DB::enableQueryLog();
        $badgeN = ContratoResource::getNavigationBadge();
        $queriesComN = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertEquals('5', $badgeN);
        $this->assertEquals(
            $queriesCom1,
            $queriesComN,
            "O badge deve executar número fixo de queries (sem N+1). Consultas com 1: {$queriesCom1}, consultas com 5: {$queriesComN}."
        );
    }

    public function test_scope_pendentes_do_usuario_retorna_mesmo_conjunto_da_regra_do_widget(): void
    {
        $respEmail = 'resp.scope@example.com';
        $user = User::factory()->create(['email' => $respEmail]);
        $pessoa = Pessoa::factory()->create(['nome' => 'Responsável Scope', 'email' => $respEmail]);
        $pessoa->users()->save($user);

        $template = TemplateContrato::create([
            'nome' => 'Template Scope',
            'conteudo' => '<p>Conteúdo</p>',
            'is_padrao' => false,
        ]);

        // Contrato 1: Pendente via responsável financeiro
        $aluno1 = Pessoa::factory()->create(['nome' => 'Aluno 1']);
        $mat1 = Matricula::factory()->create(['pessoa_id' => $aluno1->id]);
        $c1 = Contrato::create([
            'valor_total' => 1000.00,
            'matricula_id' => $mat1->id,
            'template_contrato_id' => $template->id,
            'assinafy_status' => 'pending',
        ]);
        $c1->responsaveisFinanceiros()->create(['pessoa_id' => $pessoa->id, 'percentual' => 100]);

        // Contrato 2: Assinado (não deve vir)
        $aluno2 = Pessoa::factory()->create(['nome' => 'Aluno 2']);
        $mat2 = Matricula::factory()->create(['pessoa_id' => $aluno2->id]);
        $c2 = Contrato::create([
            'valor_total' => 2000.00,
            'matricula_id' => $mat2->id,
            'template_contrato_id' => $template->id,
            'assinafy_status' => 'signed',
        ]);
        $c2->responsaveisFinanceiros()->create(['pessoa_id' => $pessoa->id, 'percentual' => 100]);

        // Contrato 3: De outra pessoa (não deve vir)
        $outraPessoa = Pessoa::factory()->create(['nome' => 'Outra']);
        $aluno3 = Pessoa::factory()->create(['nome' => 'Aluno 3']);
        $mat3 = Matricula::factory()->create(['pessoa_id' => $aluno3->id]);
        $c3 = Contrato::create([
            'valor_total' => 3000.00,
            'matricula_id' => $mat3->id,
            'template_contrato_id' => $template->id,
            'assinafy_status' => 'pending',
        ]);
        $c3->responsaveisFinanceiros()->create(['pessoa_id' => $outraPessoa->id, 'percentual' => 100]);

        $idsScope = Contrato::query()->pendentesDoUsuario($user)->pluck('contrato.id')->sort()->values()->all();

        // Lógica de referência do widget antigo
        $pessoasIds = $user->pessoas->pluck('id')->filter()->toArray();
        $idsWidgetAntigo = Contrato::query()
            ->naoAssinado()
            ->where(function ($query) use ($pessoasIds) {
                $query->whereHas('matricula', function ($qMat) use ($pessoasIds) {
                    $qMat->whereIn('pessoa_id', $pessoasIds)
                        ->orWhereHas('pessoa.responsaveis', function ($qResp) use ($pessoasIds) {
                            $qResp->whereIn('responsavel_id', $pessoasIds);
                        });
                })
                    ->orWhereHas('responsaveisFinanceiros', function ($qRF) use ($pessoasIds) {
                        $qRF->whereIn('pessoa_id', $pessoasIds);
                    })
                    ->orWhereHas('matricula.turma.serie.curso.unidade.representantesLegais', function ($qRep) use ($pessoasIds) {
                        $qRep->whereIn('pessoa_id', $pessoasIds);
                    });
            })
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertEquals($idsWidgetAntigo, $idsScope);
        $this->assertEquals([$c1->id], $idsScope);
    }

    public function test_contadores_das_abas_correspondem_aos_dados_no_banco(): void
    {
        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Teste']);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);
        $template = TemplateContrato::create(['nome' => 'Template', 'conteudo' => 'ok', 'is_padrao' => false]);

        // 1 Não enviado
        Contrato::create(['valor_total' => 100, 'matricula_id' => $matricula->id, 'template_contrato_id' => $template->id, 'assinafy_status' => 'pendente']);

        // 2 Aguardando
        Contrato::create(['valor_total' => 200, 'matricula_id' => $matricula->id, 'template_contrato_id' => $template->id, 'assinafy_status' => 'pending']);
        Contrato::create(['valor_total' => 300, 'matricula_id' => $matricula->id, 'template_contrato_id' => $template->id, 'assinafy_status' => 'enviado']);

        // 2 Assinados
        Contrato::create(['valor_total' => 400, 'matricula_id' => $matricula->id, 'template_contrato_id' => $template->id, 'assinafy_status' => 'signed']);
        Contrato::create(['valor_total' => 500, 'matricula_id' => $matricula->id, 'template_contrato_id' => $template->id, 'assinafy_status' => 'ready']);

        // 2 Com problema
        Contrato::create(['valor_total' => 600, 'matricula_id' => $matricula->id, 'template_contrato_id' => $template->id, 'assinafy_status' => 'refused']);
        Contrato::create(['valor_total' => 700, 'matricula_id' => $matricula->id, 'template_contrato_id' => $template->id, 'assinafy_status' => 'expired']);

        $page = new ListContratos;
        $tabs = $page->getTabs();

        $this->assertEquals(7, $tabs['todos']->getBadge());
        $this->assertEquals(1, $tabs['nao_enviados']->getBadge());
        $this->assertEquals(2, $tabs['aguardando']->getBadge());
        $this->assertEquals(2, $tabs['assinados']->getBadge());
        $this->assertEquals(2, $tabs['problema']->getBadge());
    }

    public function test_coluna_signatarios_renderiza_estados_esperados(): void
    {
        $template = TemplateContrato::create(['nome' => 'Template Coluna', 'conteudo' => 'ok', 'is_padrao' => false]);

        // 1. Assinado
        $aluno1 = Pessoa::factory()->create(['nome' => 'Aluno 1', 'email' => 'aluno1@test.com']);
        $mat1 = Matricula::factory()->create(['pessoa_id' => $aluno1->id]);
        $cAssinado = Contrato::create([
            'valor_total' => 1000,
            'matricula_id' => $mat1->id,
            'template_contrato_id' => $template->id,
            'assinafy_status' => 'signed',
            'assinafy_request_log' => [
                'signers_status' => [
                    'aluno1@test.com' => ['status' => 'signed'],
                ],
            ],
        ]);

        // 2. Pendente
        $aluno2 = Pessoa::factory()->create(['nome' => 'Aluno 2', 'email' => 'aluno2@test.com']);
        $mat2 = Matricula::factory()->create(['pessoa_id' => $aluno2->id]);
        $cPendente = Contrato::create([
            'valor_total' => 2000,
            'matricula_id' => $mat2->id,
            'template_contrato_id' => $template->id,
            'assinafy_status' => 'pending',
            'assinafy_request_log' => [
                'signers_status' => [
                    'aluno2@test.com' => ['status' => 'pending'],
                ],
            ],
        ]);

        // 3. Recusado
        $aluno3 = Pessoa::factory()->create(['nome' => 'Aluno 3', 'email' => 'aluno3@test.com']);
        $mat3 = Matricula::factory()->create(['pessoa_id' => $aluno3->id]);
        $cRecusado = Contrato::create([
            'valor_total' => 3000,
            'matricula_id' => $mat3->id,
            'template_contrato_id' => $template->id,
            'assinafy_status' => 'refused',
            'assinafy_request_log' => [
                'signers_status' => [
                    'aluno3@test.com' => ['status' => 'refused'],
                ],
            ],
        ]);

        // 4. Sem signatários
        $aluno4 = Pessoa::factory()->create(['nome' => 'Aluno 4', 'email' => null]);
        $mat4 = Matricula::factory()->create(['pessoa_id' => $aluno4->id]);
        $cSemSignatarios = Contrato::create([
            'valor_total' => 4000,
            'matricula_id' => $mat4->id,
            'template_contrato_id' => $template->id,
            'assinafy_status' => 'pendente',
        ]);

        $table = ContratosTable::configure(Table::make(new ListContratos));
        $column = $table->getColumn('status_signatarios');

        $this->assertNotNull($column);

        $refProp = new \ReflectionProperty($column, 'getStateUsing');
        $refProp->setAccessible(true);
        $closure = $refProp->getValue($column);

        $stateAssinado = $closure($cAssinado);
        $statePendente = $closure($cPendente);
        $stateRecusado = $closure($cRecusado);
        $stateSemSignatarios = $closure($cSemSignatarios);

        $this->assertContains('Aluno 1 (Assinado)', $stateAssinado);
        $this->assertContains('Aluno 2 (Pendente)', $statePendente);
        $this->assertContains('Aluno 3 (Recusado)', $stateRecusado);
        $this->assertEquals(['Sem signatários'], $stateSemSignatarios);
    }

    public function test_edit_contrato_salva_sem_modal_quando_nao_enviado_e_com_modal_quando_ja_enviado(): void
    {
        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Edit']);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);
        $template = TemplateContrato::create(['nome' => 'Template Edit', 'conteudo' => 'ok', 'is_padrao' => false]);

        $reflection = new ReflectionClass(EditContrato::class);
        $method = $reflection->getMethod('getSaveFormAction');
        $method->setAccessible(true);

        // 1. Contrato nunca enviado
        $contratoNaoEnviado = Contrato::create([
            'valor_total' => 1500.00,
            'matricula_id' => $matricula->id,
            'template_contrato_id' => $template->id,
            'assinafy_id' => null,
            'assinafy_request_log' => null,
        ]);

        $componentNaoEnviado = Livewire::test(EditContrato::class, ['record' => $contratoNaoEnviado->id]);
        $saveActionNaoEnviado = $method->invoke($componentNaoEnviado->instance());

        $this->assertFalse(
            $saveActionNaoEnviado->isConfirmationRequired(),
            'Contrato não enviado não deve requerer modal de confirmação ao salvar.'
        );

        $componentNaoEnviado
            ->fillForm(['valor_total' => 1800.00])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(1800.00, (float) $contratoNaoEnviado->fresh()->valor_total);

        // 2. Contrato já enviado
        $contratoEnviado = Contrato::create([
            'valor_total' => 3500.00,
            'matricula_id' => $matricula->id,
            'template_contrato_id' => $template->id,
            'assinafy_id' => 'doc_enviado_999',
            'assinafy_status' => 'pending',
            'assinafy_request_log' => ['environment' => 'sandbox'],
        ]);

        $componentEnviado = Livewire::test(EditContrato::class, ['record' => $contratoEnviado->id]);
        $saveActionEnviado = $method->invoke($componentEnviado->instance());

        $this->assertTrue(
            $saveActionEnviado->isConfirmationRequired(),
            'Contrato já enviado deve requerer modal de confirmação para alertar sobre o reset da assinatura.'
        );

        $componentEnviado
            ->fillForm(['valor_total' => 3800.00])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(
            $contratoEnviado->fresh()->assinafy_id,
            'Ao salvar um contrato já enviado, o estado no Assinafy deve ser resetado.'
        );
    }
}
