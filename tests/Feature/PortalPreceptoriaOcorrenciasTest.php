<?php

namespace Tests\Feature;

use App\Filament\Portal\Pages\Preceptoria;
use App\Models\Matricula;
use App\Models\OcorrenciaEscolar;
use App\Models\Pessoa;
use App\Models\Preceptoria as PreceptoriaModel;
use App\Models\TipoOcorrencia;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalPreceptoriaOcorrenciasTest extends TestCase
{
    use RefreshDatabase;

    private function criarAlunoComMatricula(): array
    {
        Role::firstOrCreate(['name' => 'aluno', 'guard_name' => 'web']);

        $professor = Pessoa::factory()->create(['nome' => 'Professor Teste']);
        $turma = Turma::factory()->create(['professor_conselheiro_id' => $professor->id]);

        $pessoa = Pessoa::factory()->create(['nome' => 'Aluno Preceptoria Teste']);
        $user = User::factory()->create(['activated_at' => now()]);
        $pessoa->users()->save($user);
        $user->assignRole('aluno');

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $pessoa->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        return compact('user', 'pessoa', 'matricula', 'professor', 'turma');
    }

    public function test_aluno_ve_lista_de_ocorrencias_no_portal(): void
    {
        $dados = $this->criarAlunoComMatricula();

        $tipo = TipoOcorrencia::create(['nome' => 'Elogio', 'gravidade' => 'positiva']);

        OcorrenciaEscolar::create([
            'matricula_id' => $dados['matricula']->id,
            'tipo_ocorrencia_id' => $tipo->id,
            'data_hora' => now(),
            'descricao' => 'Participação exemplar em sala de aula.',
            'notificar_responsaveis' => false,
        ]);

        $this->actingAs($dados['user'])
            ->get('/portal/ocorrencias')
            ->assertOk()
            ->assertSee('Aluno Preceptoria Teste')
            ->assertSee('Elogio');
    }

    public function test_aluno_nao_ve_ocorrencia_de_outro_aluno(): void
    {
        $dados = $this->criarAlunoComMatricula();

        $outraPessoa = Pessoa::factory()->create(['nome' => 'Outro Aluno']);
        $outraMatricula = Matricula::factory()->create(['pessoa_id' => $outraPessoa->id]);
        $tipo = TipoOcorrencia::create(['nome' => 'Advertência', 'gravidade' => 'media']);

        OcorrenciaEscolar::create([
            'matricula_id' => $outraMatricula->id,
            'tipo_ocorrencia_id' => $tipo->id,
            'data_hora' => now(),
            'descricao' => 'Ocorrência de outro aluno.',
            'notificar_responsaveis' => false,
        ]);

        $this->actingAs($dados['user'])
            ->get('/portal/ocorrencias')
            ->assertOk()
            ->assertDontSee('Outro Aluno');
    }

    public function test_aluno_agenda_preceptoria_em_horario_disponivel(): void
    {
        $dados = $this->criarAlunoComMatricula();

        $slot = PreceptoriaModel::factory()->create([
            'professor_id' => $dados['professor']->id,
            'data' => now()->addDays(3)->toDateString(),
            'matricula_id' => null,
        ]);

        Livewire::actingAs($dados['user'])
            ->test(Preceptoria::class)
            ->set('data.matricula_id', $dados['matricula']->id)
            ->set('data.preceptoria_id', $slot->id)
            ->call('agendar')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('preceptoria', [
            'id' => $slot->id,
            'matricula_id' => $dados['matricula']->id,
        ]);
    }

    public function test_aluno_nao_agenda_preceptoria_para_matricula_de_outro_aluno(): void
    {
        $dados = $this->criarAlunoComMatricula();

        $outraPessoa = Pessoa::factory()->create(['nome' => 'Outro Aluno']);
        $outraMatricula = Matricula::factory()->create(['pessoa_id' => $outraPessoa->id]);

        $slot = PreceptoriaModel::factory()->create([
            'professor_id' => $dados['professor']->id,
            'data' => now()->addDays(3)->toDateString(),
            'matricula_id' => null,
        ]);

        Livewire::actingAs($dados['user'])
            ->test(Preceptoria::class)
            ->set('data.matricula_id', $outraMatricula->id)
            ->set('data.preceptoria_id', $slot->id)
            ->call('agendar');

        $this->assertDatabaseHas('preceptoria', [
            'id' => $slot->id,
            'matricula_id' => null,
        ]);
    }
}
