<?php

namespace Tests\Feature;

use App\Filament\Resources\AtendimentoEnfermagemResource\Pages\CreateAtendimentoEnfermagem;
use App\Filament\Resources\AtendimentoEnfermagemResource\Pages\ListAtendimentoEnfermagems;
use App\Filament\Resources\FichaMedicaResource\Pages\CreateFichaMedica;
use App\Filament\Resources\FichaMedicaResource\Pages\ListFichaMedicas;
use App\Models\AtendimentoEnfermagem;
use App\Models\FichaMedica;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SaudeEscolarTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $professor;

    private Pessoa $aluno;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'professor', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->superAdmin->assignRole('super_admin');

        $this->professor = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->professor->assignRole('professor');

        $this->aluno = Pessoa::factory()->create([
            'nome' => 'Aluno Saude Teste',
        ]);

        $periodo = PeriodoLetivo::factory()->create();
        $turma = Turma::factory()->create(['periodo_letivo_id' => $periodo->id]);

        Matricula::factory()->create([
            'pessoa_id' => $this->aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);
    }

    public function test_usuario_sem_permissao_nao_pode_acessar_fichas_medicas(): void
    {
        $this->actingAs($this->professor);
        session(['active_role' => 'professor']);

        $this->get('/admin/ficha-medicas')->assertForbidden();

        Livewire::test(ListFichaMedicas::class)->assertForbidden();
    }

    public function test_super_admin_pode_listar_fichas_medicas(): void
    {
        $this->actingAs($this->superAdmin);
        session(['active_role' => 'super_admin']);

        FichaMedica::create([
            'pessoa_id' => $this->aluno->id,
            'tipo_sanguineo' => 'O+',
            'has_alergia_lactose' => true,
            'has_alergia_gluten' => false,
            'has_alergia_amendoim' => false,
            'numero_carteira_sus' => '123456789012345',
        ]);

        $this->get('/admin/ficha-medicas')->assertSuccessful();

        Livewire::test(ListFichaMedicas::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(FichaMedica::all());
    }

    public function test_super_admin_pode_criar_ficha_medica_via_formulario(): void
    {
        $this->actingAs($this->superAdmin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateFichaMedica::class)
            ->fillForm([
                'pessoa_id' => $this->aluno->id,
                'tipo_sanguineo' => 'A+',
                'has_alergia_lactose' => true,
                'has_alergia_gluten' => true,
                'has_alergia_amendoim' => false,
                'outras_alergias_alimentares' => 'Frutos do mar',
                'numero_carteira_sus' => '987654321098765',
                'hospital_preferencia' => 'Hospital Infantil',
                'medicamentos' => [
                    [
                        'nome_medicamento' => 'Bombinha de Asma',
                        'dosagem' => '2 jatos',
                        'horario_administracao' => 'Em caso de crise',
                        'autorizado_responsaveis' => true,
                    ],
                ],
                'contatosEmergencia' => [
                    [
                        'nome' => 'Maria Silva (Mae)',
                        'parentesco_grau' => 'Mae',
                        'telefone_principal' => '11999998888',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('ficha_medicas', [
            'pessoa_id' => $this->aluno->id,
            'has_alergia_lactose' => true,
            'has_alergia_gluten' => true,
        ]);

        $ficha = FichaMedica::where('pessoa_id', $this->aluno->id)->firstOrFail();
        $this->assertSame('A+', $ficha->tipo_sanguineo);
        $this->assertSame('Frutos do mar', $ficha->outras_alergias_alimentares);
        $this->assertSame('987654321098765', $ficha->numero_carteira_sus);

        // Comprova que no banco em repouso o dado está criptografado e não em texto claro
        $brutoFicha = DB::table('ficha_medicas')->where('id', $ficha->id)->first();
        $this->assertNotSame('A+', $brutoFicha->tipo_sanguineo);
        $this->assertNotSame('987654321098765', $brutoFicha->numero_carteira_sus);
    }

    public function test_usuario_sem_permissao_nao_pode_acessar_atendimentos_enfermagem(): void
    {
        $this->actingAs($this->professor);
        session(['active_role' => 'professor']);

        $this->get('/admin/atendimento-enfermagems')->assertForbidden();

        Livewire::test(ListAtendimentoEnfermagems::class)->assertForbidden();
    }

    public function test_super_admin_pode_criar_e_listar_atendimento_de_enfermagem(): void
    {
        $this->actingAs($this->superAdmin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateAtendimentoEnfermagem::class)
            ->fillForm([
                'pessoa_id' => $this->aluno->id,
                'data_hora' => now()->format('Y-m-d H:i:s'),
                'sintomas_queixa' => 'Dor de cabeca e febre leve de 37.8C',
                'procedimento_realizado' => 'Afericao de temperatura e repouso de 20min',
                'medicamento_ministrado' => 'Dipirona gotas conforme autorizacao',
                'notificado_responsaveis' => true,
                'observacoes' => 'Aluno liberado para retornar a sala de aula',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('atendimento_enfermagems', [
            'pessoa_id' => $this->aluno->id,
            'atendido_por_user_id' => $this->superAdmin->id,
            'notificado_responsaveis' => true,
        ]);

        $atendimento = AtendimentoEnfermagem::where('pessoa_id', $this->aluno->id)->firstOrFail();
        $this->assertSame('Dor de cabeca e febre leve de 37.8C', $atendimento->sintomas_queixa);
        $this->assertSame('Dipirona gotas conforme autorizacao', $atendimento->medicamento_ministrado);

        // Comprova que no banco em repouso o dado está criptografado
        $brutoAtendimento = DB::table('atendimento_enfermagems')->where('id', $atendimento->id)->first();
        $this->assertNotSame('Dor de cabeca e febre leve de 37.8C', $brutoAtendimento->sintomas_queixa);

        $this->get('/admin/atendimento-enfermagems')->assertSuccessful();

        Livewire::test(ListAtendimentoEnfermagems::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(AtendimentoEnfermagem::all());
    }
}
