<?php

namespace Tests\Feature\Preceptorias;

use App\Filament\Resources\Preceptorias\Pages\AgendarPreceptoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgendarPreceptoriaAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_com_permissao_pode_acessar_pagina_de_agendamento(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'ViewAny:Preceptoria', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Agendar:Preceptoria', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->givePermissionTo(['ViewAny:Preceptoria', 'Agendar:Preceptoria']);

        Livewire::actingAs($user)
            ->test(AgendarPreceptoria::class)
            ->assertSuccessful();
    }

    public function test_usuario_sem_permissao_nao_pode_acessar_pagina_de_agendamento(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'ViewAny:Preceptoria', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Agendar:Preceptoria', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->givePermissionTo('ViewAny:Preceptoria');

        Livewire::actingAs($user)
            ->test(AgendarPreceptoria::class)
            ->assertForbidden();
    }

    public function test_usuario_com_role_responsavel_pode_acessar_pagina_de_agendamento(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permViewAny = Permission::firstOrCreate(['name' => 'ViewAny:Preceptoria', 'guard_name' => 'web']);
        $permAgendar = Permission::firstOrCreate(['name' => 'Agendar:Preceptoria', 'guard_name' => 'web']);

        $roleResponsavel = Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);
        $roleResponsavel->givePermissionTo([$permViewAny, $permAgendar]);

        $user = User::factory()->create();
        $user->assignRole($roleResponsavel);

        session(['active_role' => 'responsavel']);

        Livewire::actingAs($user)
            ->test(AgendarPreceptoria::class)
            ->assertSuccessful();
    }
}
