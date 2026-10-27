<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConviteReuniao;
use App\Enums\StatusEncaminhamento;
use App\Enums\StatusReuniao;
use App\Enums\TipoReuniao;
use App\Filament\Pages\MeusEncaminhamentos;
use App\Filament\Resources\Reunioes\Pages\CreateReuniao;
use App\Filament\Resources\Reunioes\Pages\EditReuniao;
use App\Filament\Resources\Reunioes\Pages\ListReunioes;
use App\Filament\Resources\Reunioes\Pages\ViewReuniao;
use App\Filament\Resources\Reunioes\RelationManagers\AlunosRelationManager;
use App\Filament\Resources\Reunioes\RelationManagers\EncaminhamentosRelationManager;
use App\Models\DocumentoAssinatura;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Reuniao;
use App\Models\ReuniaoEncaminhamento;
use App\Models\Turma;
use App\Models\User;
use App\Services\ConselhoClasseService;
use App\Services\Pendencias\Fontes\AtasPorFechar;
use App\Services\Pendencias\Fontes\EncaminhamentosDeReuniao;
use App\Services\Pendencias\Fontes\EncaminhamentosVencidosDaEquipe;
use App\Services\Relatorios\Fontes\EncaminhamentosDeReunioes;
use App\Services\ReuniaoService;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Reuniões e atas (Ondas 27 e 32), telas: quem enxerga o quê, marcar e remarcar, os botões de cada passo, a pauta e as deliberações do
 * conselho, os encaminhamentos, "Meus encaminhamentos" e as filas da Mesa. "Hoje" é quarta-feira, 28/10/2026.
 */
class ReunioesTelasTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private User $coordenadora;

    private User $outraCoordenadora;

    private User $ana;

    private User $bruno;

    private User $secretaria;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-28 09:00:00'));
        Storage::fake('local');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissoes = [
            'coordenador' => ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'VerTodas', 'Deliberar'],
            'secretaria' => ['ViewAny', 'View', 'Create', 'Update'],
            'professor' => ['ViewAny', 'View', 'Deliberar'],
            'super_admin' => [],
        ];

        foreach ($permissoes as $papel => $acoes) {
            $role = Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);

            foreach ($acoes as $acao) {
                $role->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:Reuniao", 'guard_name' => 'web']));
            }
        }

        foreach (['Create:DocumentoAssinatura', 'ViewAny:DocumentoAssinatura'] as $nome) {
            Role::findByName('coordenador')->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        $this->coordenadora = $this->usuario('coordenador', 'Célia Coordenadora');
        $this->outraCoordenadora = $this->usuario('coordenador', 'Dora Coordenadora');
        $this->ana = $this->usuario('professor', 'Ana Professora');
        $this->bruno = $this->usuario('professor', 'Bruno Professor');
        $this->secretaria = $this->usuario('secretaria', 'Sônia Secretária');
        $this->superAdmin = $this->usuario('super_admin', 'Root');
    }

    private function usuario(string $papel, string $nome): User
    {
        $usuario = User::factory()->create(['name' => $nome, 'activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    private function servico(): ReuniaoService
    {
        return app(ReuniaoService::class);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function reuniao(array $extra = [], ?User $organizador = null): Reuniao
    {
        return $this->servico()->agendar(array_merge([
            'tipo' => TipoReuniao::Pedagogica,
            'titulo' => 'Reunião pedagógica',
            'inicio_em' => '2026-10-30 14:00',
            'participantes' => [$this->ana->id],
        ], $extra), $organizador ?? $this->coordenadora);
    }

    private function tela(Reuniao $reuniao, User $usuario)
    {
        return Livewire::actingAs($this->como($usuario))->test(ViewReuniao::class, ['record' => $reuniao->getKey()]);
    }

    private function iniciar(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 16:00:00'));
    }

    // ─── Quem enxerga o quê ──────────────────────────────────────────

    public function test_a_lista_mostra_o_que_a_pessoa_organiza_ou_participa(): void
    {
        $daAna = $this->reuniao(['titulo' => 'Com a Ana']);
        $doBruno = $this->reuniao(['titulo' => 'Com o Bruno', 'participantes' => [$this->bruno->id]]);

        Livewire::actingAs($this->como($this->ana))->test(ListReunioes::class)
            ->assertCanSeeTableRecords([$daAna])
            ->assertCanNotSeeTableRecords([$doBruno]);

        // Quem organiza vê as suas; quem tem "Ver todas" vê também as dos outros.
        Livewire::actingAs($this->como($this->coordenadora))->test(ListReunioes::class)->assertCanSeeTableRecords([$daAna, $doBruno]);
        Livewire::actingAs($this->como($this->outraCoordenadora))->test(ListReunioes::class)->assertCanSeeTableRecords([$daAna, $doBruno]);

        // A secretaria não organiza nem participa e não tem "Ver todas": lista vazia.
        Livewire::actingAs($this->como($this->secretaria))->test(ListReunioes::class)->assertCanNotSeeTableRecords([$daAna, $doBruno]);
    }

    public function test_reuniao_sigilosa_so_aparece_para_quem_organiza_e_quem_participa(): void
    {
        $devolutiva = $this->reuniao(['tipo' => TipoReuniao::Devolutiva, 'titulo' => 'Devolutiva da Ana']);
        $confidencial = $this->reuniao(['confidencial' => true, 'titulo' => 'Caso delicado', 'inicio_em' => '2026-10-30 16:00']);

        foreach ([$this->coordenadora, $this->ana] as $quem) {
            Livewire::actingAs($this->como($quem))->test(ListReunioes::class)->assertCanSeeTableRecords([$devolutiva, $confidencial]);
        }

        // Nem quem coordena as vê, nem o super admin.
        foreach ([$this->outraCoordenadora, $this->bruno, $this->superAdmin] as $quem) {
            Livewire::actingAs($this->como($quem))->test(ListReunioes::class)->assertCanNotSeeTableRecords([$devolutiva, $confidencial]);
        }

        $this->actingAs($this->como($this->outraCoordenadora))
            ->get(route('filament.admin.resources.reunioes.view', ['record' => $devolutiva]))
            ->assertNotFound();

        $this->actingAs($this->como($this->ana))
            ->get(route('filament.admin.resources.reunioes.view', ['record' => $devolutiva]))
            ->assertOk();
    }

    public function test_a_politica_segue_o_sigilo_e_o_papel(): void
    {
        $aberta = $this->reuniao();
        $sigilosa = $this->reuniao(['confidencial' => true, 'titulo' => 'Sigilosa', 'inicio_em' => '2026-10-30 16:00']);

        $this->como($this->outraCoordenadora);
        $this->assertTrue($this->outraCoordenadora->can('view', $aberta));
        $this->assertTrue($this->outraCoordenadora->can('gerenciar', $aberta));
        $this->assertFalse($this->outraCoordenadora->can('view', $sigilosa));
        $this->assertFalse($this->outraCoordenadora->can('gerenciar', $sigilosa));

        // A professora participa, mas não gerencia: só vê.
        $this->como($this->ana);
        $this->assertTrue($this->ana->can('view', $aberta));
        $this->assertFalse($this->ana->can('gerenciar', $aberta));
        $this->assertFalse($this->ana->can('update', $aberta));
        $this->assertFalse($this->ana->can('create', Reuniao::class));
    }

    // ─── Marcar e remarcar ───────────────────────────────────────────

    public function test_marcar_pela_tela_convida_os_participantes(): void
    {
        Livewire::actingAs($this->como($this->coordenadora))
            ->test(CreateReuniao::class)
            ->fillForm([
                'tipo' => TipoReuniao::Pedagogica->value,
                'titulo' => 'Planejamento do último bimestre',
                'inicio_em' => '2026-10-30 14:00:00',
                'participantes' => [$this->ana->id, $this->bruno->id],
                'pauta' => 'Calendário de provas.',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $reuniao = Reuniao::query()->where('titulo', 'Planejamento do último bimestre')->firstOrFail();

        $this->assertSame($this->coordenadora->id, $reuniao->organizador_user_id);
        $this->assertSame(3, $reuniao->participantes()->count());
        $this->assertSame(1, $this->ana->notifications()->count());
    }

    public function test_marcar_o_conselho_pela_tela_monta_a_pauta(): void
    {
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-01-01', 'data_fim' => '2026-12-31', 'nota_aprovacao' => 6]);
        $presidente = Pessoa::create(['nome' => 'Ana Professora']);
        $this->ana->pessoas()->attach($presidente->id);
        $turma = Turma::create(['nome' => '7º ano B', 'periodo_letivo_id' => $periodo->id, 'professor_conselheiro_id' => $presidente->id]);
        $etapa = EtapaAvaliativa::create(['turma_id' => $turma->id, 'nome' => '3º bimestre', 'periodo_letivo_id' => $periodo->id, 'data_inicio' => '2026-08-01', 'data_fim' => '2026-10-15']);
        Matricula::factory()->create(['pessoa_id' => Pessoa::create(['nome' => 'Lia Lima'])->id, 'turma_id' => $turma->id]);

        Livewire::actingAs($this->como($this->coordenadora))
            ->test(CreateReuniao::class)
            ->fillForm([
                'tipo' => TipoReuniao::ConselhoClasse->value,
                'titulo' => 'Conselho — 7º ano B',
                'inicio_em' => '2026-10-30 14:00:00',
                'turma_id' => $turma->id,
                'etapa_avaliativa_id' => $etapa->id,
                'presidente_user_id' => $this->ana->id,
                'participantes' => [$this->bruno->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $conselho = Reuniao::query()->where('titulo', 'Conselho — 7º ano B')->firstOrFail();

        $this->assertSame(1, $conselho->alunos()->count());
        $this->assertTrue($conselho->ehPresidente($this->ana));
        $this->assertSame($turma->id, $conselho->turma_id);
    }

    public function test_conselho_recusa_etapa_de_outra_turma(): void
    {
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-01-01', 'data_fim' => '2026-12-31']);
        $turma = Turma::create(['nome' => '7º ano B', 'periodo_letivo_id' => $periodo->id]);
        $outra = Turma::create(['nome' => '8º ano A', 'periodo_letivo_id' => $periodo->id]);
        $etapaDaOutra = EtapaAvaliativa::create(['turma_id' => $outra->id, 'nome' => '3º bimestre', 'periodo_letivo_id' => $periodo->id, 'data_inicio' => '2026-08-01', 'data_fim' => '2026-10-15']);

        Livewire::actingAs($this->como($this->coordenadora))
            ->test(CreateReuniao::class)
            ->fillForm([
                'tipo' => TipoReuniao::ConselhoClasse->value,
                'titulo' => 'Conselho trocado',
                'inicio_em' => '2026-10-30 14:00:00',
                'turma_id' => $turma->id,
                'etapa_avaliativa_id' => $etapaDaOutra->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['etapa_avaliativa_id']);

        $this->assertSame(0, Reuniao::count());
    }

    public function test_remarcar_pela_tela(): void
    {
        $reuniao = $this->reuniao();

        Livewire::actingAs($this->como($this->coordenadora))
            ->test(EditReuniao::class, ['record' => $reuniao->getKey()])
            ->assertFormSet(['titulo' => 'Reunião pedagógica', 'participantes' => [$this->ana->id]])
            ->fillForm(['inicio_em' => '2026-10-30 15:30:00', 'fim_em' => '2026-10-30 16:30:00', 'participantes' => [$this->ana->id, $this->bruno->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $reuniao->refresh();
        $this->assertSame('15:30', $reuniao->inicio_em->format('H:i'));
        $this->assertSame(3, $reuniao->participantes()->count());
        $this->assertSame(2, $this->ana->notifications()->count(), 'Convite e aviso de remarcação.');
    }

    public function test_so_quem_gerencia_abre_a_edicao_da_reuniao_agendada(): void
    {
        $reuniao = $this->reuniao();

        Livewire::actingAs($this->como($this->ana))->test(EditReuniao::class, ['record' => $reuniao->getKey()])->assertForbidden();

        $this->iniciar();
        $this->servico()->marcarRealizada($reuniao, $this->coordenadora);
        Livewire::actingAs($this->como($this->coordenadora))->test(EditReuniao::class, ['record' => $reuniao->getKey()])->assertForbidden();
    }

    // ─── Os botões de cada passo ─────────────────────────────────────

    public function test_botoes_seguem_o_passo_e_o_perfil(): void
    {
        $reuniao = $this->reuniao();

        // Agendada, no futuro.
        $this->tela($reuniao, $this->coordenadora)
            ->assertActionVisible('editar')->assertActionVisible('cancelar')
            ->assertActionHidden('realizar')->assertActionHidden('ata')->assertActionHidden('fechar_ata')->assertActionHidden('pdf')
            ->assertActionHidden('vou');
        $this->tela($reuniao, $this->ana)
            ->assertActionVisible('vou')->assertActionVisible('nao_vou')
            ->assertActionHidden('editar')->assertActionHidden('cancelar')->assertActionHidden('realizar');

        // Agendada, já começou.
        $this->iniciar();
        $this->tela($reuniao, $this->coordenadora)->assertActionVisible('realizar')->assertActionVisible('ata')->assertActionHidden('fechar_ata');
        $this->tela($reuniao, $this->ana)->assertActionHidden('realizar')->assertActionHidden('ata');

        // Realizada.
        $this->servico()->marcarRealizada($reuniao, $this->coordenadora, [$this->coordenadora->id => true]);
        $this->tela($reuniao, $this->coordenadora)
            ->assertActionHidden('realizar')->assertActionHidden('editar')->assertActionHidden('cancelar')
            ->assertActionVisible('presenca')->assertActionVisible('ata')->assertActionVisible('fechar_ata')->assertActionVisible('pdf')
            ->assertActionHidden('adendo')->assertActionHidden('assinar');
        $this->tela($reuniao, $this->ana)->assertActionHidden('presenca')->assertActionHidden('ata')->assertActionHidden('fechar_ata')->assertActionHidden('vou');

        // Ata fechada.
        $this->servico()->salvarAta($reuniao, 'Ata.');
        $this->servico()->fecharAta($reuniao, $this->coordenadora);
        $this->tela($reuniao, $this->coordenadora)
            ->assertActionHidden('ata')->assertActionHidden('fechar_ata')->assertActionHidden('presenca')
            ->assertActionVisible('adendo')->assertActionVisible('pdf')->assertActionVisible('assinar');

        $this->actingAs($this->como($this->secretaria))
            ->get(route('filament.admin.resources.reunioes.view', ['record' => $reuniao]))
            ->assertNotFound();
    }

    public function test_responder_o_convite_pela_tela(): void
    {
        $reuniao = $this->reuniao();

        $this->tela($reuniao, $this->ana)->callAction('vou')->assertNotified('Resposta registrada');
        $this->assertSame(ConviteReuniao::Aceito, $reuniao->participantes()->where('user_id', $this->ana->id)->first()->convite);

        $this->tela($reuniao, $this->ana)->assertActionHidden('vou')->assertActionVisible('nao_vou')->callAction('nao_vou');
        $this->assertSame(ConviteReuniao::Recusado, $reuniao->participantes()->where('user_id', $this->ana->id)->first()->convite);
        $this->assertSame(1, $this->coordenadora->notifications()->count());
    }

    public function test_marcar_realizada_escrever_e_fechar_a_ata_pela_tela(): void
    {
        $reuniao = $this->reuniao();
        $this->iniciar();

        $this->tela($reuniao, $this->coordenadora)
            ->callAction('realizar', data: ['presentes' => [$this->coordenadora->id, $this->ana->id]])
            ->assertNotified('Reunião registrada como realizada');

        $this->tela($reuniao, $this->coordenadora)
            ->callAction('fechar_ata')
            ->assertNotified('A ata não pôde ser fechada');
        $this->assertSame(StatusReuniao::Realizada, $reuniao->refresh()->status);

        $this->tela($reuniao, $this->coordenadora)
            ->callAction('ata', data: ['ata' => 'Definimos o calendário de provas.'])
            ->assertNotified('Ata salva')
            ->callAction('fechar_ata')
            ->assertNotified('Ata fechada');

        $reuniao->refresh();
        $this->assertSame(StatusReuniao::AtaFechada, $reuniao->status);
        $this->assertSame('Definimos o calendário de provas.', $reuniao->ata);

        $this->tela($reuniao, $this->coordenadora)
            ->callAction('adendo', data: ['texto' => 'Prova de Ciências em 5/11.'])
            ->assertNotified('Adendo registrado');
        $this->assertSame(1, $reuniao->adendos()->count());
    }

    public function test_cancelar_pela_tela_exige_o_motivo(): void
    {
        $reuniao = $this->reuniao();

        $this->tela($reuniao, $this->coordenadora)->callAction('cancelar', data: ['motivo' => ''])->assertHasActionErrors(['motivo' => 'required']);
        $this->tela($reuniao, $this->coordenadora)->callAction('cancelar', data: ['motivo' => 'Feriado emendado'])->assertNotified('Reunião cancelada');

        $this->assertSame(StatusReuniao::Cancelada, $reuniao->refresh()->status);
    }

    public function test_baixa_o_pdf_da_ata_e_envia_para_assinatura(): void
    {
        $reuniao = $this->reuniao();
        $this->iniciar();
        $this->servico()->marcarRealizada($reuniao, $this->coordenadora, [$this->coordenadora->id => true, $this->ana->id => true]);
        $this->servico()->salvarAta($reuniao, 'Ata.');
        $this->servico()->fecharAta($reuniao, $this->coordenadora);

        $this->tela($reuniao, $this->ana)->callAction('pdf')->assertFileDownloaded('ata-reuniao-pedagogica-2026-10-30.pdf');

        $this->tela($reuniao, $this->coordenadora)
            ->callAction('assinar', data: ['assinantes' => [['nome' => 'Ana Professora', 'email' => 'ana@escola.test', 'papel' => 'Participante']]])
            ->assertRedirect();

        $documento = DocumentoAssinatura::query()->where('documentavel_type', Reuniao::class)->firstOrFail();
        $this->assertSame($reuniao->id, (int) $documento->documentavel_id);
        $this->tela($reuniao, $this->coordenadora)->assertActionHidden('assinar')->assertActionVisible('ver_assinatura');
    }

    public function test_reuniao_sigilosa_nao_vai_para_a_assinatura_eletronica(): void
    {
        $devolutiva = $this->reuniao(['tipo' => TipoReuniao::Devolutiva, 'titulo' => 'Devolutiva da Ana']);
        $this->iniciar();
        $this->servico()->marcarRealizada($devolutiva, $this->coordenadora, [$this->coordenadora->id => true]);
        $this->servico()->salvarAta($devolutiva, 'Conversa.');
        $this->servico()->fecharAta($devolutiva, $this->coordenadora);

        $this->tela($devolutiva, $this->coordenadora)->assertActionHidden('assinar')->assertActionVisible('pdf');
    }

    // ─── Conselho: pauta e deliberação ───────────────────────────────

    /**
     * @return array{0: Reuniao, 1: Matricula}
     */
    private function conselhoComUmAluno(): array
    {
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-01-01', 'data_fim' => '2026-12-31', 'nota_aprovacao' => 6]);
        $turma = Turma::create(['nome' => '7º ano B', 'periodo_letivo_id' => $periodo->id]);
        $etapa = EtapaAvaliativa::create(['turma_id' => $turma->id, 'nome' => '3º bimestre', 'periodo_letivo_id' => $periodo->id, 'data_inicio' => '2026-08-01', 'data_fim' => '2026-10-15']);
        $matricula = Matricula::factory()->create(['pessoa_id' => Pessoa::create(['nome' => 'Lia Lima'])->id, 'turma_id' => $turma->id]);

        $conselho = $this->reuniao([
            'tipo' => TipoReuniao::ConselhoClasse,
            'titulo' => 'Conselho — 7º ano B',
            'turma_id' => $turma->id,
            'etapa_avaliativa_id' => $etapa->id,
            'participantes' => [['user_id' => $this->ana->id, 'papel' => 'presidente']],
        ]);

        return [$conselho, $matricula];
    }

    public function test_a_aba_de_alunos_so_existe_no_conselho(): void
    {
        [$conselho] = $this->conselhoComUmAluno();
        $comum = $this->reuniao(['titulo' => 'Pedagógica comum', 'inicio_em' => '2026-10-31 14:00']);

        $this->assertTrue(AlunosRelationManager::canViewForRecord($conselho, ViewReuniao::class));
        $this->assertFalse(AlunosRelationManager::canViewForRecord($comum, ViewReuniao::class));
    }

    public function test_delibera_pela_tela_com_encaminhamento(): void
    {
        [$conselho, $matricula] = $this->conselhoComUmAluno();
        $this->iniciar();
        $linha = $conselho->alunos()->firstOrFail();

        // O presidente (professora) delibera; quem só participa de outra reunião, não.
        $componente = fn (User $quem) => Livewire::actingAs($this->como($quem))
            ->test(AlunosRelationManager::class, ['ownerRecord' => $conselho, 'pageClass' => ViewReuniao::class]);

        $componente($this->ana)
            ->assertTableActionVisible('deliberar', $linha)
            ->callTableAction('deliberar', $linha, data: [
                'deliberacao' => 'recuperacao',
                'observacao' => 'Defasagem em leitura.',
                'descricao' => 'Recuperação paralela de Português',
                'responsavel_user_id' => $this->ana->id,
                'prazo' => '2026-11-10',
            ])
            ->assertNotified('Deliberação registrada');

        $linha->refresh();
        $this->assertSame('recuperacao', $linha->deliberacao);
        $this->assertSame($this->ana->id, $linha->deliberado_por_user_id);
        $encaminhamento = $linha->encaminhamentos()->firstOrFail();
        $this->assertSame($matricula->id, $encaminhamento->matricula_id);
        $this->assertSame('2026-11-10', $encaminhamento->prazo->toDateString());

        $componente($this->bruno)->assertTableActionHidden('deliberar', $linha);
    }

    public function test_deliberacao_que_pede_acompanhamento_exige_a_descricao(): void
    {
        [$conselho] = $this->conselhoComUmAluno();
        $this->iniciar();
        $linha = $conselho->alunos()->firstOrFail();

        Livewire::actingAs($this->como($this->coordenadora))
            ->test(AlunosRelationManager::class, ['ownerRecord' => $conselho, 'pageClass' => ViewReuniao::class])
            ->callTableAction('deliberar', $linha, data: ['deliberacao' => 'chamar_familia', 'descricao' => ''])
            ->assertHasTableActionErrors(['descricao' => 'required']);

        $this->assertNull($linha->refresh()->deliberacao);
    }

    public function test_depois_da_ata_fechada_nao_ha_mais_deliberacao(): void
    {
        [$conselho] = $this->conselhoComUmAluno();
        $this->iniciar();
        $linha = $conselho->alunos()->firstOrFail();
        $this->servico()->marcarRealizada($conselho, $this->coordenadora, [$this->coordenadora->id => true]);
        app(ConselhoClasseService::class)->deliberar($linha, 'sem_pendencias', null, $this->coordenadora);
        $this->servico()->fecharAta($conselho->refresh(), $this->coordenadora);

        Livewire::actingAs($this->como($this->coordenadora))
            ->test(AlunosRelationManager::class, ['ownerRecord' => $conselho->refresh(), 'pageClass' => ViewReuniao::class])
            ->assertTableActionHidden('deliberar', $linha)
            ->assertTableActionHidden('atualizar_indicadores');
    }

    // ─── Encaminhamentos ─────────────────────────────────────────────

    public function test_registra_e_conclui_encaminhamento_pela_aba(): void
    {
        $reuniao = $this->reuniao();

        $aba = fn (User $quem) => Livewire::actingAs($this->como($quem))
            ->test(EncaminhamentosRelationManager::class, ['ownerRecord' => $reuniao, 'pageClass' => ViewReuniao::class]);

        $aba($this->coordenadora)
            ->callAction(TestAction::make('novo')->table(), data: ['descricao' => 'Enviar o calendário de provas', 'responsavel_user_id' => $this->ana->id, 'prazo' => '2026-11-06'])
            ->assertNotified('Encaminhamento registrado');

        $encaminhamento = $reuniao->encaminhamentos()->firstOrFail();
        $this->assertSame($this->ana->id, $encaminhamento->responsavel_user_id);

        // Quem participa sem gerenciar não cria, mas o responsável conclui o que é seu.
        $aba($this->ana)->assertActionHidden(TestAction::make('novo')->table());
        $aba($this->bruno)->assertTableActionHidden('concluir', $encaminhamento);
        $aba($this->ana)->assertTableActionVisible('concluir', $encaminhamento)->callTableAction('concluir', $encaminhamento, data: ['observacao' => 'Enviado.']);

        $this->assertSame(StatusEncaminhamento::Feito, $encaminhamento->refresh()->status);
    }

    public function test_meus_encaminhamentos_lista_os_do_responsavel_e_conclui(): void
    {
        $reuniao = $this->reuniao(['participantes' => [$this->ana->id, $this->bruno->id]]);
        $daAna = $this->servico()->adicionarEncaminhamento($reuniao, ['descricao' => 'Tarefa da Ana', 'responsavel_user_id' => $this->ana->id, 'prazo' => '2026-11-06'], $this->coordenadora);
        $doBruno = $this->servico()->adicionarEncaminhamento($reuniao, ['descricao' => 'Tarefa do Bruno', 'responsavel_user_id' => $this->bruno->id], $this->coordenadora);

        Livewire::actingAs($this->como($this->ana))->test(MeusEncaminhamentos::class)
            ->assertCanSeeTableRecords([$daAna])
            ->assertCanNotSeeTableRecords([$doBruno])
            ->callTableAction('concluir', $daAna, data: ['observacao' => 'Feito'])
            ->assertNotified('Encaminhamento concluído');

        $this->assertSame(StatusEncaminhamento::Feito, $daAna->refresh()->status);

        // A coordenação, sem o filtro "Somente os meus", enxerga os da equipe.
        Livewire::actingAs($this->como($this->coordenadora))->test(MeusEncaminhamentos::class)
            ->removeTableFilter('meus')
            ->assertCanSeeTableRecords([$doBruno]);
    }

    public function test_meus_encaminhamentos_nao_mostra_os_de_reuniao_sigilosa_alheia(): void
    {
        $devolutiva = $this->reuniao(['tipo' => TipoReuniao::Devolutiva, 'titulo' => 'Devolutiva da Ana']);
        $combinado = $this->servico()->adicionarEncaminhamento($devolutiva, ['descricao' => 'Plano de melhoria', 'responsavel_user_id' => $this->ana->id], $this->coordenadora);

        Livewire::actingAs($this->como($this->ana))->test(MeusEncaminhamentos::class)->assertCanSeeTableRecords([$combinado]);
        Livewire::actingAs($this->como($this->outraCoordenadora))->test(MeusEncaminhamentos::class)->removeTableFilter('meus')->assertCanNotSeeTableRecords([$combinado]);
    }

    // ─── Mesa, relatório e rotina ────────────────────────────────────

    public function test_a_mesa_mostra_encaminhamentos_atas_e_o_painel_da_coordenacao(): void
    {
        $reuniao = $this->reuniao(['participantes' => [$this->ana->id]]);
        $this->servico()->adicionarEncaminhamento($reuniao, ['descricao' => 'Fechar as notas', 'responsavel_user_id' => $this->ana->id, 'prazo' => '2026-11-02'], $this->coordenadora);
        $this->servico()->adicionarEncaminhamento($reuniao, ['descricao' => 'Sem prazo', 'responsavel_user_id' => $this->ana->id], $this->coordenadora);

        $meus = new EncaminhamentosDeReuniao;
        $equipe = new EncaminhamentosVencidosDaEquipe;
        $atas = new AtasPorFechar;

        $this->assertSame(2, $meus->contar($this->ana));
        $this->assertSame(0, $meus->atrasados($this->ana));
        $this->assertSame(0, $meus->contar($this->bruno));
        $this->assertSame(0, $equipe->contar($this->coordenadora));

        $this->travelTo(Carbon::parse('2026-11-03 09:00:00'));
        $this->assertSame(1, $meus->atrasados($this->ana));
        $this->assertSame('Fechar as notas', $meus->itens($this->ana, 5)[0]->titulo);
        $this->assertTrue($meus->itens($this->ana, 5)[0]->atrasado);
        $this->assertSame(1, $equipe->contar($this->coordenadora));
        $this->assertSame(0, $equipe->contar($this->ana), 'Quem é o responsável vê na fila pessoal, não na da equipe.');
        $this->assertSame('VerTodas:Reuniao', $equipe->permissao());

        // Ata por fechar: a organizadora e a coordenação veem; a professora participante, não.
        $this->servico()->marcarRealizada($reuniao, $this->coordenadora, [$this->coordenadora->id => true]);
        $this->assertSame(1, $atas->contar($this->coordenadora));
        $this->assertSame(1, $atas->contar($this->outraCoordenadora));
        $this->assertSame(0, $atas->contar($this->ana));
        $this->assertSame(0, $atas->atrasados($this->coordenadora), 'Realizada hoje (03/11 09:00): ainda no prazo.');

        $this->travelTo(Carbon::parse('2026-11-10 09:00:00'));
        $this->assertSame(1, $atas->atrasados($this->coordenadora));
    }

    public function test_a_mesa_nao_mostra_atas_sigilosas_a_coordenacao(): void
    {
        $devolutiva = $this->reuniao(['tipo' => TipoReuniao::Devolutiva, 'titulo' => 'Devolutiva da Ana']);
        $this->iniciar();
        $this->servico()->marcarRealizada($devolutiva, $this->coordenadora, [$this->coordenadora->id => true]);

        $this->assertSame(1, (new AtasPorFechar)->contar($this->coordenadora));
        $this->assertSame(0, (new AtasPorFechar)->contar($this->outraCoordenadora));
        $this->assertSame('Devolutiva individual', (new AtasPorFechar)->itens($this->coordenadora, 5)[0]->titulo, 'O título do assunto não aparece na fila.');
    }

    public function test_o_relatorio_de_encaminhamentos_deixa_de_fora_as_reunioes_sigilosas(): void
    {
        $aberta = $this->reuniao();
        $devolutiva = $this->reuniao(['tipo' => TipoReuniao::Devolutiva, 'titulo' => 'Devolutiva da Ana', 'inicio_em' => '2026-10-30 16:00']);
        $this->servico()->adicionarEncaminhamento($aberta, ['descricao' => 'Combinado da equipe', 'responsavel_user_id' => $this->ana->id], $this->coordenadora);
        $this->servico()->adicionarEncaminhamento($devolutiva, ['descricao' => 'Combinado sigiloso', 'responsavel_user_id' => $this->ana->id], $this->coordenadora);

        $fonte = new EncaminhamentosDeReunioes;
        $linhas = $fonte->linhas([], 100);

        $this->assertCount(1, $linhas);
        $this->assertSame('Combinado da equipe', $linhas[0]['descricao']);
        $this->assertSame('VerTodas:Reuniao', $fonte->permissao());
        $this->assertSame([], $fonte->linhas(['status' => 'feito'], 100));
    }

    public function test_a_rotina_diaria_lembra_os_encaminhamentos_vencidos(): void
    {
        $reuniao = $this->reuniao();
        $this->servico()->adicionarEncaminhamento($reuniao, ['descricao' => 'Fechar as notas', 'responsavel_user_id' => $this->ana->id, 'prazo' => '2026-11-02'], $this->coordenadora);

        $this->artisan('reunioes:lembrar-encaminhamentos')->expectsOutputToContain('Nenhum encaminhamento vencido')->assertSuccessful();

        $this->travelTo(Carbon::parse('2026-11-03 09:00:00'));
        $this->artisan('reunioes:lembrar-encaminhamentos')->expectsOutputToContain('1 encaminhamento(s) vencido(s) avisado(s).')->assertSuccessful();
        $this->artisan('reunioes:lembrar-encaminhamentos')->expectsOutputToContain('Nenhum encaminhamento vencido')->assertSuccessful();

        $this->assertNotNull(ReuniaoEncaminhamento::firstOrFail()->vencimento_avisado_em);
    }
}
