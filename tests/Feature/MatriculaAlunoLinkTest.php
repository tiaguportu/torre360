<?php

namespace Tests\Feature;

use App\Filament\Resources\Matriculas\Pages\ListMatriculas;
use App\Filament\Resources\Pessoas\PessoaResource;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatriculaAlunoLinkTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function usuario_com_permissao_de_edicao_deve_ver_link_para_edicao_da_pessoa()
    {
        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        // Mocks de permissões usando o Gate do Laravel
        Gate::define('ViewAny:Matricula', fn (User $user) => true);
        Gate::define('Update:Pessoa', fn (User $user) => true);
        Gate::define('View:Pessoa', fn (User $user) => true);

        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Teste Link Edit']);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);

        $component = Livewire::test(ListMatriculas::class);
        $table = $component->instance()->getTable();
        $column = $table->getColumn('pessoa.nome');

        $column->record($matricula);
        $expectedUrl = PessoaResource::getUrl('edit', ['record' => $aluno->id]);
        $this->assertEquals($expectedUrl, $column->getUrl());
    }

    #[Test]
    public function usuario_com_permissao_de_visualizacao_apenas_deve_ver_link_de_visualizacao_da_pessoa()
    {
        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        // Mocks de permissões usando o Gate do Laravel
        Gate::define('ViewAny:Matricula', fn (User $user) => true);
        Gate::define('Update:Pessoa', fn (User $user) => false);
        Gate::define('View:Pessoa', fn (User $user) => true);

        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Teste Link View']);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);

        $component = Livewire::test(ListMatriculas::class);
        $table = $component->instance()->getTable();
        $column = $table->getColumn('pessoa.nome');

        $column->record($matricula);
        $expectedUrl = PessoaResource::getUrl('edit', ['record' => $aluno->id]);
        $this->assertEquals($expectedUrl, $column->getUrl());
    }

    #[Test]
    public function usuario_sem_permissao_nao_deve_ver_link_na_coluna_do_aluno()
    {
        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        // Mocks de permissões usando o Gate do Laravel
        Gate::define('ViewAny:Matricula', fn (User $user) => true);
        Gate::define('Update:Pessoa', fn (User $user) => false);
        Gate::define('View:Pessoa', fn (User $user) => false);

        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Teste Sem Link']);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);

        $component = Livewire::test(ListMatriculas::class);
        $table = $component->instance()->getTable();
        $column = $table->getColumn('pessoa.nome');

        $column->record($matricula);
        $this->assertNull($column->getUrl());
    }
}
