<?php

namespace Tests\Feature;

use App\Filament\Pages\GeradorCrachas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GeradorCrachasTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_super_admin_deve_conseguir_acessar_a_pagina_do_gerador_de_crachas(): void
    {
        Role::create(['name' => 'super_admin']);

        $admin = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('super_admin');

        $response = $this->actingAs($admin)->get('/admin/secretaria/gerador-crachas');

        $response->assertStatus(200);
    }

    public function test_usuario_sem_permissao_deve_receber_403_ao_acessar_o_gerador_de_crachas(): void
    {
        $user = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/admin/secretaria/gerador-crachas');

        $response->assertStatus(403);
    }

    public function test_componente_livewire_do_gerador_de_crachas_carrega_com_campos_esperados(): void
    {
        Role::create(['name' => 'super_admin']);

        $admin = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(GeradorCrachas::class)
            ->assertSet('data.tipo_selecao', 'individual')
            ->assertHasNoFormErrors();
    }
}
