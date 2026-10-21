<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Professor registra portfólio e pareceres das suas turmas (o recorte é feito pela policy e pela consulta);
     * publicar o parecer para as famílias é decisão da coordenação.
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        'ViewAny:PortfolioItem' => ['super_admin', 'admin', 'coordenador', 'secretaria', 'professor'],
        'View:PortfolioItem' => ['super_admin', 'admin', 'coordenador', 'secretaria', 'professor'],
        'Create:PortfolioItem' => ['super_admin', 'admin', 'coordenador', 'professor'],
        'Update:PortfolioItem' => ['super_admin', 'admin', 'coordenador', 'professor'],
        'Delete:PortfolioItem' => ['super_admin', 'admin', 'coordenador', 'professor'],
        'DeleteAny:PortfolioItem' => ['super_admin', 'admin', 'coordenador'],

        'ViewAny:ParecerDescritivo' => ['super_admin', 'admin', 'coordenador', 'secretaria', 'professor'],
        'View:ParecerDescritivo' => ['super_admin', 'admin', 'coordenador', 'secretaria', 'professor'],
        'Create:ParecerDescritivo' => ['super_admin', 'admin', 'coordenador', 'professor'],
        'Update:ParecerDescritivo' => ['super_admin', 'admin', 'coordenador', 'professor'],
        'Delete:ParecerDescritivo' => ['super_admin', 'admin', 'coordenador'],
        'DeleteAny:ParecerDescritivo' => ['super_admin', 'admin', 'coordenador'],
        'Publicar:ParecerDescritivo' => ['super_admin', 'admin', 'coordenador'],
        'GerarComIa:ParecerDescritivo' => ['super_admin', 'admin', 'coordenador', 'professor'],
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
