<?php

namespace Tests\Feature\Preceptorias;

use App\Filament\Resources\Preceptorias\Pages\AgendarPreceptoria;
use App\Filament\Resources\Preceptorias\Pages\CreatePreceptoria;
use App\Filament\Resources\Preceptorias\Pages\EditPreceptoria;
use App\Filament\Resources\Preceptorias\Pages\ListPreceptorias;
use App\Models\CicloPreceptoria;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\Preceptorias\PreceptoriaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Professor e aluno não podem ter duas preceptorias em horários que se sobrepõem, por onde quer que se grave.
 */
class PreceptoriaConflitoHorarioTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, string>  $permissoes
     */
    private function usuarioCom(array $permissoes, string $papelAtivo = 'admin'): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $papel = Role::firstOrCreate(['name' => $papelAtivo, 'guard_name' => 'web']);
        foreach ($permissoes as $nome) {
            $papel->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        $user = User::factory()->create();
        $user->assignRole($papel);
        session(['active_role' => $papelAtivo]);

        return $user;
    }

    private function ocupada(Pessoa $professor, string $data = '2026-09-08', string $inicio = '10:00:00', string $fim = '10:30:00'): Preceptoria
    {
        // o formulário de edição exige o ciclo
        $ciclo = CicloPreceptoria::firstOrCreate(['nome' => 'Ciclo de Teste'], ['data_inicio' => '2026-09-01', 'data_fim' => '2026-09-30']);

        return Preceptoria::factory()->create([
            'ciclo_preceptoria_id' => $ciclo->id,
            'professor_id' => $professor->id,
            'data' => $data,
            'hora_inicio' => $inicio,
            'hora_fim' => $fim,
        ]);
    }

    public function test_editar_para_horario_concorrente_avisa_e_nao_salva(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'View:Preceptoria', 'Update:Preceptoria']);
        $professor = Pessoa::factory()->create();
        $this->ocupada($professor);
        $outra = $this->ocupada($professor, '2026-09-08', '11:00:00', '11:30:00');

        Livewire::actingAs($user)
            ->test(EditPreceptoria::class, ['record' => $outra->getRouteKey()])
            ->fillForm(['hora_inicio' => '10:15', 'hora_fim' => '10:45'])
            ->call('save')
            ->assertNotified('Horário em conflito');

        $this->assertSame('11:00', $outra->fresh()->hora_inicio->format('H:i'));
    }

    public function test_cadastro_de_datas_avulsas_pula_a_data_em_conflito_e_cria_as_outras(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Create:Preceptoria']);
        $ciclo = CicloPreceptoria::create(['nome' => 'Ciclo', 'data_inicio' => '2026-09-01', 'data_fim' => '2026-09-30']);
        $professor = Pessoa::factory()->create();
        $this->ocupada($professor, '2026-09-08');

        Livewire::actingAs($user)
            ->test(CreatePreceptoria::class)
            ->fillForm([
                'ciclo_preceptoria_id' => $ciclo->id,
                'professor_id' => $professor->id,
                'hora_inicio' => '10:00',
                'hora_fim' => '10:30',
                'tipo_selecao_data' => 'datas_especificas',
                'datas' => [['data' => '2026-09-08'], ['data' => '2026-09-09']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertEquals(
            ['2026-09-08', '2026-09-09'],
            Preceptoria::orderBy('data')->get()->map(fn (Preceptoria $p) => $p->data->toDateString())->all(),
        );
        $this->assertSame(1, Preceptoria::whereDate('data', '2026-09-08')->count());
        $this->assertSame(1, Preceptoria::whereDate('data', '2026-09-09')->count());
    }

    public function test_cadastro_de_data_avulsa_em_conflito_nao_cria_nada(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Create:Preceptoria']);
        $ciclo = CicloPreceptoria::create(['nome' => 'Ciclo', 'data_inicio' => '2026-09-01', 'data_fim' => '2026-09-30']);
        $professor = Pessoa::factory()->create();
        $this->ocupada($professor, '2026-09-08');

        Livewire::actingAs($user)
            ->test(CreatePreceptoria::class)
            ->fillForm([
                'ciclo_preceptoria_id' => $ciclo->id,
                'professor_id' => $professor->id,
                'hora_inicio' => '10:15',
                'hora_fim' => '10:45',
                'tipo_selecao_data' => 'datas_especificas',
                'datas' => [['data' => '2026-09-08']],
            ])
            ->call('create')
            ->assertNotified('Nenhuma preceptoria criada')
            ->assertNoRedirect();

        $this->assertSame(1, Preceptoria::count());
    }

    public function test_clonar_sem_nova_data_nao_duplica_o_horario(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Create:Preceptoria']);
        $original = $this->ocupada(Pessoa::factory()->create());

        Livewire::actingAs($user)
            ->test(ListPreceptorias::class)
            ->callTableBulkAction('clone', [$original], data: [])
            ->assertNotified();

        $this->assertSame(1, Preceptoria::count());

        // com nova data o clone é criado
        Livewire::actingAs($user)
            ->test(ListPreceptorias::class)
            ->callTableBulkAction('clone', [$original], data: ['data' => '2026-09-15']);

        $this->assertSame(2, Preceptoria::count());
    }

    public function test_editar_em_lote_para_o_mesmo_horario_deixa_so_a_primeira(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Update:Preceptoria']);
        $professor = Pessoa::factory()->create();
        $a = $this->ocupada($professor, '2026-09-08', '08:00:00', '08:30:00');
        $b = $this->ocupada($professor, '2026-09-09', '09:00:00', '09:30:00');

        Livewire::actingAs($user)
            ->test(ListPreceptorias::class)
            ->callTableBulkAction('editar_lote', [$a, $b], data: ['data' => '2026-09-10', 'hora_inicio' => '14:00', 'hora_fim' => '14:30'])
            ->assertNotified();

        // a segunda a ser gravada cairia em cima da primeira (a ordem é a da seleção): só uma muda de dia
        $this->assertSame(1, Preceptoria::whereDate('data', '2026-09-10')->count());
        $this->assertSame(1, Preceptoria::whereIn('id', [$a->id, $b->id])->whereDate('data', '!=', '2026-09-10')->count());
    }

    public function test_agendar_aluno_que_ja_tem_preceptoria_no_horario_avisa_conflito(): void
    {
        Notification::fake();

        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Agendar:Preceptoria']);
        $professor = Pessoa::factory()->create();
        $turma = Turma::factory()->create(['professor_conselheiro_id' => $professor->id]);
        $matricula = Matricula::factory()->create(['pessoa_id' => Pessoa::factory()->create()->id, 'turma_id' => $turma->id]);
        // o aluno já está ocupado com outro professor nesse horário
        Preceptoria::factory()->create(['matricula_id' => $matricula->id, 'data' => now()->addDays(3)->toDateString(), 'hora_inicio' => '10:00:00', 'hora_fim' => '10:30:00']);
        $livre = Preceptoria::factory()->create(['professor_id' => $professor->id, 'data' => now()->addDays(3)->toDateString(), 'hora_inicio' => '10:15:00', 'hora_fim' => '10:45:00']);

        Livewire::actingAs($user)
            ->test(AgendarPreceptoria::class)
            ->fillForm(['matricula_id' => $matricula->id, 'preceptoria_id' => $livre->id])
            ->call('agendar')
            ->assertNotified('Horário em conflito');

        $this->assertNull($livre->fresh()->matricula_id);
        Notification::assertNothingSent();
    }

    public function test_liberar_pela_tela_de_agendar_manda_o_nome_de_quem_saiu(): void
    {
        Notification::fake();

        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Agendar:Preceptoria']);
        $matricula = Matricula::factory()->create(['pessoa_id' => Pessoa::factory()->create(['nome' => 'Aluna Que Saiu'])->id]);
        $preceptoria = Preceptoria::factory()->create(['matricula_id' => $matricula->id, 'data' => now()->addDays(3)->toDateString()]);

        Livewire::actingAs($user)
            ->test(AgendarPreceptoria::class)
            ->call('liberarHorario', $preceptoria->id);

        $this->assertNull($preceptoria->fresh()->matricula_id);
        Notification::assertSentTo(
            $user,
            PreceptoriaNotification::class,
            fn (PreceptoriaNotification $n) => $n->tipo === 'liberacao' && $n->alunoAnterior === 'Aluna Que Saiu',
        );
    }
}
