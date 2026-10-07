<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RemoveLegacyShieldPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_legacy_permissions_with_double_colon(): void
    {
        // 1. Criar role e permissões (legadas com '::' e atuais válidas com ':')
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'web']);

        $legacy1 = Permission::create(['name' => 'view_any::matricula', 'guard_name' => 'web']);
        $legacy2 = Permission::create(['name' => 'create::aluno', 'guard_name' => 'web']);
        $validPerm = Permission::create(['name' => 'ViewAny:Matricula', 'guard_name' => 'web']);

        $role->givePermissionTo([$legacy1, $legacy2, $validPerm]);

        $this->assertDatabaseHas('permissions', ['name' => 'view_any::matricula']);
        $this->assertDatabaseHas('permissions', ['name' => 'create::aluno']);
        $this->assertDatabaseHas('permissions', ['name' => 'ViewAny:Matricula']);
        $this->assertDatabaseHas('role_has_permissions', ['permission_id' => $legacy1->id, 'role_id' => $role->id]);

        // 2. Executar a migration de limpeza
        $migration = require database_path('migrations/2026_10_07_120000_remove_legacy_shield_permissions.php');
        $migration->up();

        // 3. Verificar que as permissões legadas foram removidas
        $this->assertDatabaseMissing('permissions', ['name' => 'view_any::matricula']);
        $this->assertDatabaseMissing('permissions', ['name' => 'create::aluno']);
        $this->assertDatabaseMissing('role_has_permissions', ['permission_id' => $legacy1->id]);
        $this->assertDatabaseMissing('role_has_permissions', ['permission_id' => $legacy2->id]);

        // 4. Verificar que a permissão válida permaneceu intacta e vinculada
        $this->assertDatabaseHas('permissions', ['name' => 'ViewAny:Matricula']);
        $this->assertDatabaseHas('role_has_permissions', ['permission_id' => $validPerm->id, 'role_id' => $role->id]);
    }
}
