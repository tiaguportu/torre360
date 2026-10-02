<?php

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TmpDebugBulkTest extends TestCase
{
    use RefreshDatabase;

    public function test_dump(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');

        $c = User::factory()->create(['name' => 'Carla Souza', 'activated_at' => now()]);
        $p = Pessoa::factory()->create(['telefone' => '(11) 98888-7777', 'user_id' => $c->id]);
        $c->pessoas()->attach($p);
        $st = StatusInteressado::firstOrCreate(['nome' => 'Em Atendimento'], ['cor' => 'warning', 'ordem' => 2, 'is_final' => false, 'is_ganho' => false]);
        $or = OrigemInteressado::firstOrCreate(['nome' => 'Instagram']);
        $lead = Interessado::create(['pessoa_id' => Pessoa::factory()->create(['nome' => 'Lead da Carla'])->id, 'usuario_id' => $c->id, 'origem_interessado_id' => $or->id, 'status_interessado_id' => $st->id]);

        $t = Livewire::actingAs($admin)->test(ListInteressados::class)->mountTableBulkAction('enviarAosConsultores', [$lead]);
        $html = $t->html();
        file_put_contents('C:/Users/tiagu/AppData/Local/Temp/claude/c--xampp-htdocs-torre360/94e03782-3eba-4551-b821-02e3c3229546/scratchpad/bulk.html', $html);
        $this->assertTrue(true);
    }
}
