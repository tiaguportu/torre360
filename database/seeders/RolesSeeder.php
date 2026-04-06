<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    /**
     * Cria os papéis padrão do sistema Torre360.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [
            'super_admin' => 'Super Administrador',
            'admin' => 'Administrador',
            'secretaria' => 'Secretaria',
            'professor' => 'Professor',
            'coordenador' => 'Coordenador',
            'responsavel' => 'Responsável Financeiro',
            'aluno' => 'Aluno',
        ];

        foreach ($roles as $name => $display) {
            Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['name' => $name, 'guard_name' => 'web']
            );
        }

        $this->command->info('Papéis criados com sucesso: '.implode(', ', array_keys($roles)));
    }
}
