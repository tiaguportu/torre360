<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Filament\Resources\Interessados\Pages\KanbanInteressados;
use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AssumirLeadAtomicoLoteF6Test extends TestCase
{
    use RefreshDatabase;

    private StatusInteressado $status;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->status = StatusInteressado::create([
            'nome' => 'Novo',
            'cor' => 'info',
            'ordem' => 1,
            'is_final' => false,
            'is_ganho' => false,
        ]);

        $this->origem = OrigemInteressado::create(['nome' => 'Site']);
        TipoContatoInteressado::firstOrCreate(['nome' => TipoContatoInteressado::FUNIL]);

        Permission::firstOrCreate(['name' => 'ViewAny:Interessado', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:Interessado', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Interessado', 'guard_name' => 'web']);
    }

    private function criarConsultor(string $nome = 'Consultor'): User
    {
        $usuario = User::factory()->create([
            'name' => $nome,
            'activated_at' => now(),
            'deactivated_at' => null,
        ]);

        $usuario->givePermissionTo('ViewAny:Interessado', 'View:Interessado', 'Update:Interessado');

        return $usuario;
    }

    private function criarLeadSemConsultor(string $nome = 'Lead'): Interessado
    {
        $pessoa = Pessoa::create([
            'nome' => $nome,
            'telefone' => '11999998888',
            'email' => fake()->unique()->safeEmail(),
        ]);

        return Interessado::create([
            'pessoa_id' => $pessoa->id,
            'usuario_id' => null,
            'status_interessado_id' => $this->status->id,
            'origem_interessado_id' => $this->origem->id,
        ]);
    }

    public function test_primeiro_consultor_assume_lead_e_segundo_perde_a_corrida_no_kanban(): void
    {
        $consultorA = $this->criarConsultor('Consultor A');
        $consultorB = $this->criarConsultor('Consultor B');
        $lead = $this->criarLeadSemConsultor('Família Silva');

        // Consultor A assume pelo Kanban
        Livewire::actingAs($consultorA)
            ->test(KanbanInteressados::class)
            ->call('assumirLead', $lead->id);

        $lead->refresh();
        $this->assertSame($consultorA->id, $lead->usuario_id);
        $this->assertSame(1, HistoricoContato::where('interessado_id', $lead->id)->count());

        // Consultor B tenta assumir o mesmo lead pelo Kanban logo em seguida
        Livewire::actingAs($consultorB)
            ->test(KanbanInteressados::class)
            ->call('assumirLead', $lead->id);

        // O lead continua com o consultor A e NÃO deve haver segundo histórico de contato
        $lead->refresh();
        $this->assertSame($consultorA->id, $lead->usuario_id);
        $this->assertSame(1, HistoricoContato::where('interessado_id', $lead->id)->count());
    }

    public function test_perdeu_a_corrida_na_pagina_de_edicao_notifica_que_ja_foi_assumido(): void
    {
        $consultorA = $this->criarConsultor('Consultor A');
        $consultorB = $this->criarConsultor('Consultor B');
        $lead = $this->criarLeadSemConsultor('Família Santos');

        // Consultor B abre a página e abre o modal de confirmação para assumir
        $componentB = Livewire::actingAs($consultorB)
            ->test(EditInteressado::class, ['record' => $lead->id])
            ->mountAction('assumirLead');

        // Nesse instante, antes de B confirmar, Consultor A vence a corrida no banco
        $lead->update(['usuario_id' => $consultorA->id]);
        HistoricoContato::create([
            'interessado_id' => $lead->id,
            'tipo_contato_interessado_id' => TipoContatoInteressado::porNome(TipoContatoInteressado::FUNIL)?->id,
            'usuario_id' => $consultorA->id,
            'data_contato' => now(),
            'relato' => 'Lead assumido pelo consultor Consultor A.',
            'resultado' => 'outro',
            'automatico' => false,
        ]);

        // Consultor B clica para confirmar
        $componentB->callMountedAction();

        $lead->refresh();
        $this->assertSame($consultorA->id, $lead->usuario_id);
        // Não pode ter gerado outro histórico
        $this->assertSame(1, HistoricoContato::where('interessado_id', $lead->id)->count(), 'Não pode duplicar histórico quando perde a corrida');
    }

    public function test_usuario_sem_permissao_update_interessado_nao_consegue_assumir_lead(): void
    {
        $usuarioSemPermissao = User::factory()->create([
            'activated_at' => now(),
            'deactivated_at' => null,
        ]);
        $usuarioSemPermissao->givePermissionTo('ViewAny:Interessado');

        $lead = $this->criarLeadSemConsultor('Família Oliveira');

        Livewire::actingAs($usuarioSemPermissao)
            ->test(KanbanInteressados::class)
            ->call('assumirLead', $lead->id);

        $lead->refresh();
        $this->assertNull($lead->usuario_id);
        $this->assertSame(0, HistoricoContato::where('interessado_id', $lead->id)->count());
    }

    public function test_tabela_interessados_acao_assumir_lead_respeita_atomicidade(): void
    {
        $consultorA = $this->criarConsultor('Consultor Tabela A');
        $consultorB = $this->criarConsultor('Consultor Tabela B');
        $lead = $this->criarLeadSemConsultor('Família Tabela');

        Livewire::actingAs($consultorA)
            ->test(ListInteressados::class)
            ->callAction(TestAction::make('assumirLead')->table($lead));

        $lead->refresh();
        $this->assertSame($consultorA->id, $lead->usuario_id);
        $this->assertSame(1, HistoricoContato::where('interessado_id', $lead->id)->count());
    }
}
