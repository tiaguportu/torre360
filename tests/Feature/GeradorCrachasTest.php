<?php

namespace Tests\Feature;

use App\Filament\Pages\GeradorCrachasV1;
use App\Filament\Pages\GeradorCrachasV3;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GeradorCrachasTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_super_admin_deve_conseguir_acessar_as_paginas_dos_geradores_de_crachas(): void
    {
        Role::create(['name' => 'super_admin']);

        $admin = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('super_admin');

        $response1 = $this->actingAs($admin)->get('/admin/secretaria/gerador-crachas-v1');
        $response1->assertStatus(200);

        $response3 = $this->actingAs($admin)->get('/admin/secretaria/gerador-crachas-v3');
        $response3->assertStatus(200);
    }

    public function test_usuario_sem_permissao_deve_receber_403_ao_acessar_os_geradores_de_crachas(): void
    {
        $user = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);

        $response1 = $this->actingAs($user)->get('/admin/secretaria/gerador-crachas-v1');
        $response1->assertStatus(403);

        $response3 = $this->actingAs($user)->get('/admin/secretaria/gerador-crachas-v3');
        $response3->assertStatus(403);
    }

    public function test_componentes_livewire_dos_geradores_de_crachas_carregam_com_campos_esperados(): void
    {
        Role::create(['name' => 'super_admin']);

        $admin = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(GeradorCrachasV1::class)
            ->assertSet('data.tipo_selecao', 'individual')
            ->assertHasNoFormErrors();

        Livewire::actingAs($admin)
            ->test(GeradorCrachasV3::class)
            ->assertSet('data.tipo_selecao', 'individual')
            ->assertHasNoFormErrors();
    }
}
