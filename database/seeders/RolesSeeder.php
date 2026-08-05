<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
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

        // Atribuir permissões de preceptoria para os papéis aplicáveis
        $permsPreceptoria = [
            Permission::firstOrCreate(['name' => 'ViewAny:Preceptoria', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'View:Preceptoria', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'Agendar:Preceptoria', 'guard_name' => 'web']),
        ];

        foreach (['responsavel', 'aluno', 'secretaria', 'admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                foreach ($permsPreceptoria as $perm) {
                    if (! $role->hasPermissionTo($perm)) {
                        $role->givePermissionTo($perm);
                    }
                }
            }
        }

        $this->command->info('Papéis e permissões criados com sucesso: '.implode(', ', array_keys($roles)));
    }
}
