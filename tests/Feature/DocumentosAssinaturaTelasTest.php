<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MotivoCancelamentoMatricula;
use App\Enums\OrigemCancelamentoMatricula;
use App\Enums\StatusDocumentoAssinatura;
use App\Enums\TipoProcessoFuncionario;
use App\Enums\TipoTemplateDocumento;
use App\Filament\Portal\Pages\CancelamentoDeMatricula;
use App\Filament\Resources\CancelamentosMatricula\Pages\ListCancelamentosMatricula;
use App\Filament\Resources\DocumentosAssinatura\Pages\CreateDocumentoAssinatura;
use App\Filament\Resources\DocumentosAssinatura\Pages\ListDocumentosAssinatura;
use App\Filament\Resources\DocumentosAssinatura\Pages\ViewDocumentoAssinatura;
use App\Filament\Resources\ProcessosFuncionario\Pages\ViewProcessoFuncionario;
use App\Filament\Resources\ProcessosFuncionario\RelationManagers\ItensRelationManager;
use App\Models\CancelamentoMatricula;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\DocumentoAssinatura;
use App\Models\Funcionario;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\ResponsavelFinanceiro;
use App\Models\Serie;
use App\Models\TemplateDocumento;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\CancelamentoMatriculaService;
use App\Services\Documentos\AssinaturaDocumentoService;
use App\Services\ProcessoFuncionarioService;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Telas da assinatura eletrônica de documentos avulsos (Onda 69): lista, criação, página do documento, a ação no
 * cancelamento de matrícula, o link no Portal da família e a ação na lista de providências do RH.
 *
 * "Hoje" é quinta-feira, 22/10/2026. O Assinafy é sempre simulado.
 */
class DocumentosAssinaturaTelasTest extends TestCase
{
    use RefreshDatabase;

    private const ASSINAFY_ID = 'DOC-TELA-1';

    private const CERTIFICADO = "%PDF-1.4\n1 0 obj<<>>endobj\n%certificado\n%%EOF";

    private User $secretaria;

    private User $coordenador;

    private User $admin;

    private Matricula $matricula;

    private Contrato $contrato;

    private Pessoa $mae;

    private TemplateDocumento $modeloDistrato;

    private CancelamentoMatricula $cancelamento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-22 09:00:00'));
        Storage::fake('local');

        config([
            'services.assinafy.key' => 'CHAVE-DE-TESTE',
            'services.assinafy.account_id' => 'CONTA-TESTE',
            'services.assinafy.url' => 'https://assinafy.teste/v1',
            'services.assinafy.webhook_secret' => null,
        ]);
        Http::preventStrayRequests();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'admin', 'secretaria', 'coordenador', 'professor', 'responsavel', 'aluno'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['ViewAny', 'View', 'Create', 'Enviar', 'Cancelar'] as $acao) {
            foreach (['secretaria', 'admin'] as $papel) {
                Role::findByName($papel)->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:DocumentoAssinatura", 'guard_name' => 'web']));
            }
        }

        foreach (['ViewAny', 'View'] as $acao) {
            Role::findByName('coordenador')->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:DocumentoAssinatura", 'guard_name' => 'web']));
        }

        foreach (['ViewAny', 'View', 'Create', 'Update', 'Concluir'] as $acao) {
            Role::findByName('admin')->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:ProcessoFuncionario", 'guard_name' => 'web']));
        }

        // A secretaria aprova e anexa o distrato (cancelamento de matrícula) e abre Modelos de Documentos.
        foreach (['ViewAny:CancelamentoMatricula', 'View:CancelamentoMatricula', 'Create:CancelamentoMatricula', 'Aprovar:CancelamentoMatricula', 'Concluir:CancelamentoMatricula', 'ViewAny:TemplateDocumento'] as $permissao) {
            Role::findByName('secretaria')->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }

        $this->secretaria = $this->usuario('secretaria');
        $this->coordenador = $this->usuario('coordenador');
        $this->admin = $this->usuario('admin');

        $unidade = Unidade::create(['nome' => 'Unidade Sede', 'cnpj' => '12.345.678/0001-90']);
        $unidade->representantesLegais()->attach(Pessoa::create(['nome' => 'Diretora Clara', 'email' => 'clara@escola.test'])->id, ['cargo' => 'Diretora']);
        $curso = Curso::create(['unidade_id' => $unidade->id, 'nome_externo' => 'Fundamental', 'nome_interno' => 'EF']);
        $serie = Serie::create(['curso_id' => $curso->id, 'nome' => '7º Ano', 'sistema_avaliacao' => 'Nota']);
        $this->matricula = Matricula::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Ana Lima', 'data_nascimento' => '2014-03-10'])->id,
            'turma_id' => Turma::factory()->create(['serie_id' => $serie->id])->id,
            'situacao' => 'ativa',
        ]);
        $this->contrato = Contrato::create(['matricula_id' => $this->matricula->id, 'valor_total' => 12000, 'data_aceite' => '2026-01-05']);
        $this->mae = Pessoa::create(['nome' => 'Marina Lima', 'email' => 'marina@teste.com']);
        ResponsavelFinanceiro::create(['contrato_id' => $this->contrato->id, 'pessoa_id' => $this->mae->id, 'percentual' => 100]);

        $this->modeloDistrato = $this->modelo(TipoTemplateDocumento::DistratoMatricula, 'Distrato de Matrícula');

        $servico = app(CancelamentoMatriculaService::class);
        $pedido = $servico->solicitar($this->matricula, ['motivo' => MotivoCancelamentoMatricula::MudancaCidade, 'data_efetiva' => '2026-10-31'], null, OrigemCancelamentoMatricula::Escola);
        $this->cancelamento = $servico->aprovar($pedido, $this->secretaria, []);
    }

    private function servico(): AssinaturaDocumentoService
    {
        return app(AssinaturaDocumentoService::class);
    }

    private function usuario(string $papel, array $atributos = []): User
    {
        $usuario = User::factory()->create($atributos + ['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    private function modelo(TipoTemplateDocumento $tipo, string $nome, bool $ativo = true): TemplateDocumento
    {
        $conteudo = $tipo->contexto()->value === 'funcionario'
            ? '<p>Termo de {{FUNCIONARIO_NOME}}.</p>{{BLOCO_ASSINATURAS}}'
            : '<p>{{ALUNO_NOME}} — saída em {{DATA_SAIDA}}.</p>{{BLOCO_ASSINATURAS}}';

        return TemplateDocumento::create(['nome' => $nome, 'tipo' => $tipo, 'conteudo' => $conteudo, 'validade_dias' => 0, 'is_ativo' => $ativo]);
    }

    private function documento(?TemplateDocumento $modelo = null, ?object $registro = null, ?array $assinantes = null): DocumentoAssinatura
    {
        return $this->servico()->criar($modelo ?? $this->modeloDistrato, $registro ?? $this->cancelamento, $assinantes ?? [
            ['nome' => 'Marina Lima', 'email' => 'marina@teste.com', 'papel' => 'Responsável financeiro'],
            ['nome' => 'Diretora Clara', 'email' => 'clara@escola.test', 'papel' => 'Escola'],
        ], $this->secretaria);
    }

    private function documentoEnviado(?TemplateDocumento $modelo = null, ?object $registro = null): DocumentoAssinatura
    {
        $documento = $this->documento($modelo, $registro);
        $this->fakeAssinafy();

        return $this->servico()->enviar($documento, $this->secretaria);
    }

    /**
     * @param  list<array<string, mixed>>  $assinantesDaApi
     */
    private function fakeAssinafy(string $statusDoDocumento = 'pending_signature', array $assinantesDaApi = [], ?string $certificado = null): void
    {
        Http::swap(new HttpFactory);
        Http::preventStrayRequests();

        Http::fake(function (Request $requisicao) use ($statusDoDocumento, $assinantesDaApi, $certificado) {
            $metodo = $requisicao->method();
            $caminho = (string) parse_url($requisicao->url(), PHP_URL_PATH);

            return match (true) {
                $metodo === 'POST' && str_ends_with($caminho, '/documents') => Http::response(['id' => self::ASSINAFY_ID], 200),
                $metodo === 'GET' && str_ends_with($caminho, '/signers') => Http::response(['data' => []], 200),
                $metodo === 'POST' && str_ends_with($caminho, '/signers') => Http::response(['id' => 'SIGNER-'.substr(md5((string) $requisicao['email']), 0, 8)], 200),
                $metodo === 'POST' && str_ends_with($caminho, '/assignments') => Http::response(['signing_urls' => array_map(
                    fn (array $assinante): array => ['signer_id' => $assinante['id'], 'url' => 'https://assinafy.teste/sign/'.$assinante['id'].'?token=T'.$assinante['id']],
                    $requisicao['signers'],
                )], 200),
                $metodo === 'GET' && str_ends_with($caminho, '/download/certificated') => $certificado === null ? Http::response('', 404) : Http::response($certificado, 200),
                $metodo === 'GET' && (bool) preg_match('#/documents/[^/]+$#', $caminho) => Http::response(['data' => ['id' => self::ASSINAFY_ID, 'status' => $statusDoDocumento, 'assignment' => ['signers' => $assinantesDaApi]]], 200),
                $metodo === 'DELETE' => Http::response(null, 204),
                default => Http::response(['message' => 'rota não prevista no teste'], 500),
            };
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function todosAssinaram(): array
    {
        return [
            ['email' => 'marina@teste.com', 'completed' => true, 'signed_at' => '2026-10-22T13:00:00Z'],
            ['email' => 'clara@escola.test', 'completed' => true, 'signed_at' => '2026-10-22T14:30:00Z'],
        ];
    }

    private function funcionarioComModelo(): array
    {
        $funcionario = Funcionario::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Marta Souza', 'email' => 'marta@teste.com'])->id,
            'cargo' => 'Professora',
        ]);

        return [$funcionario, $this->modelo(TipoTemplateDocumento::TermoConfidencialidade, 'Termo de Confidencialidade')];
    }

    // --- Lista -------------------------------------------------------------------------------------------------------

    public function test_lista_mostra_o_documento_a_situacao_e_quantos_assinaram(): void
    {
        $documento = $this->documentoEnviado();

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListDocumentosAssinatura::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$documento])
            ->assertSee('Distrato de Matrícula — Ana Lima')
            ->assertSee('Aguardando assinaturas')
            ->assertSee('0/2');
    }

    public function test_lista_nao_mostra_documento_de_rh_a_quem_nao_ve_admissao_e_desligamento(): void
    {
        [$funcionario, $termo] = $this->funcionarioComModelo();
        $deRh = $this->servico()->criar($termo, $funcionario, [['nome' => 'Marta Souza', 'email' => 'marta@teste.com']], $this->admin);
        $doAluno = $this->documento();

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListDocumentosAssinatura::class)
            ->assertCanSeeTableRecords([$doAluno])
            ->assertCanNotSeeTableRecords([$deRh]);

        Livewire::actingAs($this->como($this->admin))
            ->test(ListDocumentosAssinatura::class)
            ->assertCanSeeTableRecords([$doAluno, $deRh]);
    }

    public function test_filtro_por_situacao_separa_os_documentos(): void
    {
        $rascunho = $this->documento();
        $outroModelo = $this->modelo(TipoTemplateDocumento::Personalizado, 'Declaração livre');
        $enviado = $this->servico()->criar($outroModelo, $this->matricula, [['nome' => 'Marina Lima', 'email' => 'marina@teste.com']], $this->secretaria);
        $this->fakeAssinafy();
        $enviado = $this->servico()->enviar($enviado, $this->secretaria);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListDocumentosAssinatura::class)
            ->filterTable('status', [StatusDocumentoAssinatura::Enviado->value])
            ->assertCanSeeTableRecords([$enviado])
            ->assertCanNotSeeTableRecords([$rascunho]);
    }

    public function test_so_quem_cria_ve_o_botao_de_novo_documento(): void
    {
        Livewire::actingAs($this->como($this->secretaria))->test(ListDocumentosAssinatura::class)->assertActionVisible('create');
        Livewire::actingAs($this->como($this->coordenador))->test(ListDocumentosAssinatura::class)->assertSuccessful()->assertActionHidden('create');
    }

    public function test_lista_leva_aos_modelos_so_para_quem_pode_abri_los(): void
    {
        Livewire::actingAs($this->como($this->secretaria))->test(ListDocumentosAssinatura::class)->assertActionVisible('modelos');
        Livewire::actingAs($this->como($this->coordenador))->test(ListDocumentosAssinatura::class)->assertActionHidden('modelos');
    }

    public function test_pessoa_sem_permissao_nao_abre_a_lista(): void
    {
        $this->actingAs($this->como($this->usuario('professor')))
            ->get('/admin/secretaria/documentos-assinatura')
            ->assertForbidden();
    }

    // --- Criar -------------------------------------------------------------------------------------------------------

    public function test_criacao_oferece_so_modelos_ativos_e_esconde_os_de_funcionario_de_quem_nao_e_do_rh(): void
    {
        $contrato = $this->modelo(TipoTemplateDocumento::ContratoTrabalho, 'Contrato de Trabalho');
        $inativo = $this->modelo(TipoTemplateDocumento::Personalizado, 'Declaração inativa', ativo: false);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(CreateDocumentoAssinatura::class)
            ->assertFormFieldExists('template_documento_id', fn (Select $campo): bool => $campo->getOptions() === [
                $this->modeloDistrato->id => 'Distrato de Matrícula — Cancelamento de matrícula',
            ]);

        Livewire::actingAs($this->como($this->admin))
            ->test(CreateDocumentoAssinatura::class)
            ->assertFormFieldExists('template_documento_id', fn (Select $campo): bool => array_keys($campo->getOptions()) === [$contrato->id, $this->modeloDistrato->id]
                && $campo->getOptions()[$contrato->id] === 'Contrato de Trabalho — Funcionário'
                && ! array_key_exists($inativo->id, $campo->getOptions()));
    }

    public function test_ao_escolher_o_registro_os_assinantes_ja_vem_sugeridos(): void
    {
        $tela = Livewire::actingAs($this->como($this->secretaria))
            ->test(CreateDocumentoAssinatura::class)
            ->fillForm([
                'template_documento_id' => $this->modeloDistrato->id,
                'documentavel_id' => $this->cancelamento->id,
            ]);

        $assinantes = array_values($tela->get('data.assinantes'));

        $this->assertCount(2, $assinantes);
        $this->assertSame(['marina@teste.com', 'clara@escola.test'], array_column($assinantes, 'email'));
        $this->assertSame(['Responsável financeiro', 'Escola'], array_column($assinantes, 'papel'));
    }

    public function test_cria_o_documento_e_leva_para_a_pagina_dele(): void
    {
        Livewire::actingAs($this->como($this->secretaria))
            ->test(CreateDocumentoAssinatura::class)
            ->fillForm([
                'template_documento_id' => $this->modeloDistrato->id,
                'documentavel_id' => $this->cancelamento->id,
                'expira_em' => '2026-11-15',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Documento criado')
            ->assertRedirect();

        $documento = DocumentoAssinatura::query()->firstOrFail();

        $this->assertSame(StatusDocumentoAssinatura::Rascunho, $documento->status);
        $this->assertSame('2026-11-15', $documento->expira_em->toDateString());
        $this->assertSame($this->secretaria->id, $documento->criado_por_user_id);
        $this->assertSame(2, $documento->assinantes()->count());
        Http::assertNothingSent();
    }

    public function test_assinantes_editados_na_tela_valem_no_documento(): void
    {
        Livewire::actingAs($this->como($this->secretaria))
            ->test(CreateDocumentoAssinatura::class)
            ->fillForm([
                'template_documento_id' => $this->modeloDistrato->id,
                'documentavel_id' => $this->cancelamento->id,
                'titulo' => 'Distrato da Ana',
                'assinantes' => [['nome' => 'Tio Paulo', 'email' => 'paulo@teste.com', 'papel' => 'Avalista']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $documento = DocumentoAssinatura::query()->firstOrFail();

        $this->assertSame('Distrato da Ana', $documento->titulo);
        $this->assertSame(['paulo@teste.com'], $documento->assinantes()->pluck('email')->all());
    }

    public function test_criacao_exige_modelo_registro_e_assinantes_com_email_valido(): void
    {
        Livewire::actingAs($this->como($this->secretaria))
            ->test(CreateDocumentoAssinatura::class)
            ->call('create')
            ->assertHasFormErrors(['template_documento_id' => 'required', 'documentavel_id' => 'required', 'assinantes' => 'required']);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(CreateDocumentoAssinatura::class)
            ->fillForm([
                'template_documento_id' => $this->modeloDistrato->id,
                'documentavel_id' => $this->cancelamento->id,
                'assinantes' => [['nome' => 'Sem Email', 'email' => 'isso-nao-e-email', 'papel' => null]],
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertSame(0, DocumentoAssinatura::query()->count());
    }

    public function test_criacao_avisa_quando_ja_existe_documento_em_andamento_e_nao_cria_outro(): void
    {
        $this->documento();

        Livewire::actingAs($this->como($this->secretaria))
            ->test(CreateDocumentoAssinatura::class)
            ->fillForm([
                'template_documento_id' => $this->modeloDistrato->id,
                'documentavel_id' => $this->cancelamento->id,
            ])
            ->call('create')
            ->assertNotified('Não foi possível criar o documento')
            ->assertNoRedirect();

        $this->assertSame(1, DocumentoAssinatura::query()->count());
    }

    public function test_coordenacao_nao_abre_a_criacao(): void
    {
        $this->actingAs($this->como($this->coordenador))
            ->get('/admin/secretaria/documentos-assinatura/create')
            ->assertForbidden();
    }

    // --- Página do documento ----------------------------------------------------------------------------------------

    public function test_pagina_mostra_os_assinantes_a_situacao_e_o_texto_enviado(): void
    {
        $documento = $this->documentoEnviado();

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ViewDocumentoAssinatura::class, ['record' => $documento->id])
            ->assertSuccessful()
            ->assertSee('Distrato de Matrícula — Ana Lima')
            ->assertSee('Aguardando assinaturas')
            ->assertSee('Marina Lima')
            ->assertSee('marina@teste.com')
            ->assertSee('Diretora Clara')
            ->assertSee($documento->arquivo_original_hash);
    }

    public function test_enviar_pela_pagina_muda_a_situacao_e_esconde_o_botao(): void
    {
        $documento = $this->documento();
        $this->fakeAssinafy();

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ViewDocumentoAssinatura::class, ['record' => $documento->id])
            ->assertActionVisible('enviar')
            ->callAction('enviar')
            ->assertNotified('Documento enviado')
            ->assertActionHidden('enviar')
            ->assertActionVisible('atualizar');

        $this->assertSame(StatusDocumentoAssinatura::Enviado, $documento->refresh()->status);
    }

    public function test_envio_que_falha_mostra_o_motivo_e_deixa_reenviar(): void
    {
        $documento = $this->documento();
        config(['services.assinafy.key' => '']);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ViewDocumentoAssinatura::class, ['record' => $documento->id])
            ->callAction('enviar')
            ->assertNotified('Não foi possível enviar')
            ->assertActionVisible('enviar');

        $this->assertSame(StatusDocumentoAssinatura::Rascunho, $documento->refresh()->status);
    }

    public function test_atualizar_pela_pagina_traz_o_certificado_e_mostra_o_pdf_assinado(): void
    {
        $documento = $this->documentoEnviado();
        $this->fakeAssinafy('certificated', $this->todosAssinaram(), self::CERTIFICADO);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ViewDocumentoAssinatura::class, ['record' => $documento->id])
            ->assertActionHidden('baixar_assinado')
            ->callAction('atualizar')
            ->assertNotified('Situação: Assinado')
            ->assertActionVisible('baixar_assinado')
            ->assertActionHidden('cancelar')
            ->assertSee('Assinado por todos em');

        $this->assertTrue($this->cancelamento->refresh()->temDistratoAssinado());
    }

    public function test_cancelar_pela_pagina_encerra_o_documento(): void
    {
        $documento = $this->documentoEnviado();
        $this->fakeAssinafy();

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ViewDocumentoAssinatura::class, ['record' => $documento->id])
            ->callAction('cancelar')
            ->assertNotified('Documento cancelado')
            ->assertActionHidden('cancelar')
            ->assertActionHidden('atualizar')
            ->assertActionHidden('links');

        $this->assertSame(StatusDocumentoAssinatura::Cancelado, $documento->refresh()->status);
    }

    public function test_links_de_assinatura_aparecem_so_para_quem_envia_e_so_dos_que_faltam_assinar(): void
    {
        $documento = $this->documentoEnviado();
        $links = $documento->assinantes()->pluck('link_assinatura')->all();

        $tela = Livewire::actingAs($this->como($this->secretaria))
            ->test(ViewDocumentoAssinatura::class, ['record' => $documento->id])
            ->assertActionVisible('links')
            ->mountAction('links')
            ->assertMountedActionModalSee([$links[0], $links[1]]);

        // Depois que a primeira pessoa assina, o link dela deixa de aparecer.
        $this->fakeAssinafy('pending_signature', [
            ['email' => 'marina@teste.com', 'completed' => true, 'signed_at' => '2026-10-22T13:00:00Z'],
            ['email' => 'clara@escola.test', 'completed' => false],
        ]);
        $this->servico()->atualizar($documento);

        $tela = Livewire::actingAs($this->como($this->secretaria))
            ->test(ViewDocumentoAssinatura::class, ['record' => $documento->id])
            ->mountAction('links')
            ->assertMountedActionModalSee($links[1])
            ->assertMountedActionModalDontSee($links[0]);

        Livewire::actingAs($this->como($this->coordenador))
            ->test(ViewDocumentoAssinatura::class, ['record' => $documento->id])
            ->assertActionHidden('links')
            ->assertActionHidden('enviar')
            ->assertActionHidden('cancelar');
    }

    public function test_super_admin_nao_ve_acao_que_o_estado_do_documento_nao_permite(): void
    {
        $superAdmin = $this->usuario('super_admin');
        $enviado = $this->documentoEnviado();
        $this->fakeAssinafy('certificated', $this->todosAssinaram(), self::CERTIFICADO);
        $assinado = $this->servico()->atualizar($enviado);
        $rascunho = $this->servico()->criar($this->modelo(TipoTemplateDocumento::Personalizado, 'Declaração livre'), $this->matricula, [['nome' => 'Marina Lima', 'email' => 'marina@teste.com']], $this->secretaria);

        Livewire::actingAs($this->como($superAdmin))
            ->test(ViewDocumentoAssinatura::class, ['record' => $assinado->id])
            ->assertActionHidden('enviar')
            ->assertActionHidden('cancelar')
            ->assertActionHidden('links')
            ->assertActionVisible('atualizar');

        Livewire::actingAs($this->como($superAdmin))
            ->test(ViewDocumentoAssinatura::class, ['record' => $rascunho->id])
            ->assertActionVisible('enviar')
            ->assertActionVisible('cancelar')
            ->assertActionHidden('atualizar')
            ->assertActionHidden('links');
    }

    public function test_acoes_da_lista_seguem_o_estado_de_cada_documento(): void
    {
        $rascunho = $this->documento();
        $outroModelo = $this->modelo(TipoTemplateDocumento::Personalizado, 'Declaração livre');
        $enviado = $this->servico()->criar($outroModelo, $this->matricula, [['nome' => 'Marina Lima', 'email' => 'marina@teste.com']], $this->secretaria);
        $this->fakeAssinafy();
        $enviado = $this->servico()->enviar($enviado, $this->secretaria);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListDocumentosAssinatura::class)
            ->assertTableActionVisible('enviar', $rascunho)
            ->assertTableActionHidden('enviar', $enviado)
            ->assertTableActionVisible('atualizar', $enviado)
            ->assertTableActionHidden('atualizar', $rascunho)
            ->callTableAction('enviar', $rascunho);

        $this->assertSame(StatusDocumentoAssinatura::Enviado, $rascunho->refresh()->status);
    }

    // --- Cancelamento de matrícula ---------------------------------------------------------------------------------

    public function test_cancelamento_monta_o_distrato_para_assinatura_e_leva_ao_documento(): void
    {
        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListCancelamentosMatricula::class)
            ->assertActionExists(TestAction::make('preparar_assinatura_eletronica')->table($this->cancelamento))
            ->callAction(TestAction::make('preparar_assinatura_eletronica')->table($this->cancelamento))
            ->assertRedirect();

        $documento = DocumentoAssinatura::query()->firstOrFail();

        $this->assertSame(CancelamentoMatricula::class, $documento->documentavel_type);
        $this->assertSame($this->cancelamento->id, $documento->documentavel_id);
        $this->assertSame(['marina@teste.com', 'clara@escola.test'], $documento->assinantes()->pluck('email')->all());
        $this->assertSame(StatusDocumentoAssinatura::Rascunho, $documento->status, 'nada vai ao Assinafy antes da conferência');
        Http::assertNothingSent();
    }

    public function test_cancelamento_sem_modelo_de_distrato_ativo_explica_o_que_fazer(): void
    {
        $this->modeloDistrato->update(['is_ativo' => false]);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListCancelamentosMatricula::class)
            ->callAction(TestAction::make('preparar_assinatura_eletronica')->table($this->cancelamento))
            ->assertNotified('Não há modelo de distrato ativo')
            ->assertNoRedirect();

        $this->assertSame(0, DocumentoAssinatura::query()->count());
    }

    public function test_cancelamento_com_documento_em_andamento_oferece_ver_em_vez_de_montar_outro(): void
    {
        $documento = $this->documento();

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListCancelamentosMatricula::class)
            ->assertActionHidden(TestAction::make('preparar_assinatura_eletronica')->table($this->cancelamento))
            ->assertActionVisible(TestAction::make('ver_assinatura_eletronica')->table($this->cancelamento));

        $this->assertSame(1, DocumentoAssinatura::query()->where('id', $documento->id)->count());
    }

    public function test_cancelamento_sem_permissao_de_criar_documento_nao_ve_a_acao(): void
    {
        Role::findByName('secretaria')->revokePermissionTo('Create:DocumentoAssinatura');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListCancelamentosMatricula::class)
            ->assertActionHidden(TestAction::make('preparar_assinatura_eletronica')->table($this->cancelamento));
    }

    // --- Portal da família -----------------------------------------------------------------------------------------

    private function familia(): User
    {
        $usuario = $this->usuario('responsavel', ['email' => 'marina@teste.com']);
        $this->mae->users()->save($usuario);
        $this->mae->alunos()->attach($this->matricula->pessoa_id);

        return $usuario;
    }

    public function test_portal_mostra_o_link_de_assinatura_so_do_proprio_usuario(): void
    {
        $this->documentoEnviado();
        $links = DocumentoAssinatura::query()->firstOrFail()->assinantes()->pluck('link_assinatura', 'email');

        Livewire::actingAs($this->como($this->familia()))
            ->test(CancelamentoDeMatricula::class)
            ->assertSee('enviou o distrato para assinatura eletrônica')
            ->assertSee('0 de 2')
            ->assertSee('Assinar o distrato')
            ->assertSee(e($links['marina@teste.com']), false)
            ->assertDontSee(e($links['clara@escola.test']), false);
    }

    public function test_portal_depois_de_assinar_mostra_que_ja_assinou_sem_o_botao(): void
    {
        $documento = $this->documentoEnviado();
        $this->fakeAssinafy('pending_signature', [
            ['email' => 'marina@teste.com', 'completed' => true, 'signed_at' => '2026-10-22T13:00:00Z'],
            ['email' => 'clara@escola.test', 'completed' => false],
        ]);
        $this->servico()->atualizar($documento);

        Livewire::actingAs($this->como($this->familia()))
            ->test(CancelamentoDeMatricula::class)
            ->assertSee('1 de 2')
            ->assertSee('Você já assinou')
            ->assertDontSee('Assinar o distrato');
    }

    public function test_portal_sem_assinatura_eletronica_mantem_o_texto_de_baixar_e_entregar(): void
    {
        Livewire::actingAs($this->como($this->familia()))
            ->test(CancelamentoDeMatricula::class)
            ->assertSee('Baixe o distrato, assine e entregue na secretaria')
            ->assertDontSee('Assinar o distrato');
    }

    // --- RH ----------------------------------------------------------------------------------------------------------

    public function test_providencia_do_rh_monta_o_documento_do_funcionario_ligado_a_ela(): void
    {
        [$funcionario, $termo] = $this->funcionarioComModelo();
        $processo = app(ProcessoFuncionarioService::class)->iniciar($funcionario, TipoProcessoFuncionario::Admissao, '2026-11-02', $this->como($this->admin));
        $item = $processo->itens()->where('titulo', 'Contrato de trabalho assinado')->firstOrFail();

        Livewire::actingAs($this->como($this->admin))
            ->test(ItensRelationManager::class, ['ownerRecord' => $processo, 'pageClass' => ViewProcessoFuncionario::class])
            ->assertTableActionVisible('assinatura_eletronica', $item)
            ->callTableAction('assinatura_eletronica', $item, data: ['modelo' => $termo->id])
            ->assertHasNoTableActionErrors()
            ->assertRedirect();

        $documento = DocumentoAssinatura::query()->firstOrFail();

        $this->assertSame($item->id, $documento->documentavel_id);
        $this->assertStringContainsString('Marta Souza', $documento->conteudo);
        $this->assertSame('marta@teste.com', $documento->assinantes()->first()->email);

        // Com o documento em andamento, a ação some e aparece o atalho para ele.
        Livewire::actingAs($this->como($this->admin))
            ->test(ItensRelationManager::class, ['ownerRecord' => $processo, 'pageClass' => ViewProcessoFuncionario::class])
            ->assertTableActionHidden('assinatura_eletronica', $item)
            ->assertTableActionVisible('ver_assinatura_eletronica', $item);
    }

    public function test_providencia_do_rh_so_oferece_modelos_de_funcionario_ativos(): void
    {
        [$funcionario, $termo] = $this->funcionarioComModelo();
        $inativo = $this->modelo(TipoTemplateDocumento::ContratoTrabalho, 'Contrato de Trabalho (inativo)', ativo: false);
        $processo = app(ProcessoFuncionarioService::class)->iniciar($funcionario, TipoProcessoFuncionario::Admissao, '2026-11-02', $this->como($this->admin));
        $item = $processo->itens()->firstOrFail();

        $tela = fn () => Livewire::actingAs($this->como($this->admin))
            ->test(ItensRelationManager::class, ['ownerRecord' => $processo, 'pageClass' => ViewProcessoFuncionario::class]);

        // O modelo inativo e o de outro tipo de registro (distrato, que usa dados de cancelamento) não são aceitos.
        $tela()->callTableAction('assinatura_eletronica', $item, data: ['modelo' => $inativo->id])->assertHasTableActionErrors(['modelo']);
        $tela()->callTableAction('assinatura_eletronica', $item, data: ['modelo' => $this->modeloDistrato->id])->assertHasTableActionErrors(['modelo']);
        $this->assertSame(0, DocumentoAssinatura::query()->count());

        $tela()->callTableAction('assinatura_eletronica', $item, data: ['modelo' => $termo->id])->assertHasNoTableActionErrors();
        $this->assertSame(1, DocumentoAssinatura::query()->count());
    }

    public function test_providencia_ja_resolvida_nao_oferece_a_assinatura(): void
    {
        [$funcionario] = $this->funcionarioComModelo();
        $servicoRh = app(ProcessoFuncionarioService::class);
        $processo = $servicoRh->iniciar($funcionario, TipoProcessoFuncionario::Admissao, '2026-11-02', $this->como($this->admin));
        $item = $processo->itens()->firstOrFail();
        $servicoRh->marcarItem($item, $this->admin);

        Livewire::actingAs($this->como($this->admin))
            ->test(ItensRelationManager::class, ['ownerRecord' => $processo, 'pageClass' => ViewProcessoFuncionario::class])
            ->assertTableActionHidden('assinatura_eletronica', $item->refresh());
    }
}
