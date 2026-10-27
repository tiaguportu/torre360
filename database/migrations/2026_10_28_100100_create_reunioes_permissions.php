<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Reuniões e atas (Ondas 27 e 32). Quem tem papel de equipe vê as reuniões em que participa; marcar e organizar é da administração,
     * da coordenação e da secretaria; `VerTodas` abre as reuniões da equipe para a coordenação (menos as sigilosas); deliberar no
     * conselho de classe é de quem leciona e coordena. Papéis que a escola não criou são ignorados.
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        'ViewAny:Reuniao' => ['super_admin', 'admin', 'secretaria', 'coordenador', 'professor'],
        'View:Reuniao' => ['super_admin', 'admin', 'secretaria', 'coordenador', 'professor'],
        'Create:Reuniao' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Update:Reuniao' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Delete:Reuniao' => ['super_admin', 'admin'],
        'DeleteAny:Reuniao' => ['super_admin', 'admin'],
        'VerTodas:Reuniao' => ['super_admin', 'admin', 'coordenador'],
        'Deliberar:Reuniao' => ['super_admin', 'admin', 'coordenador', 'professor'],
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissoes as $nome => $papeis) {
            $permissao = Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);

            foreach (Role::whereIn('name', $papeis)->get() as $papel) {
                if (! $papel->hasPermissionTo($permissao)) {
                    $papel->givePermissionTo($permissao);
                }
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', array_keys($this->permissoes))->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
