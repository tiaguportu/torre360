<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('visita_pesquisa_satisfacao') && ! Schema::hasColumn('visita_pesquisa_satisfacao', 'alerta_detrator_enviado_em')) {
            Schema::table('visita_pesquisa_satisfacao', function (Blueprint $table) {
                $table->timestamp('alerta_detrator_enviado_em')->nullable()->after('ip');
            });
        }

        // Provisionamento de permissão Shield para a página de relatórios do CRM
        try {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            $permissions = [
                'page_CrmRelatoriosPage',
                'View:CrmRelatoriosPage',
            ];

            foreach ($permissions as $permName) {
                $permission = Permission::firstOrCreate(
                    ['name' => $permName, 'guard_name' => 'web'],
                    ['name' => $permName, 'guard_name' => 'web']
                );

                foreach (['super_admin', 'admin'] as $roleName) {
                    $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
                    if ($role && ! $role->hasPermissionTo($permName)) {
                        $role->givePermissionTo($permission);
                    }
                }
            }
        } catch (Throwable) {
            // Em testes unitários com banco limpo sem roles prévias, não falha
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('visita_pesquisa_satisfacao') && Schema::hasColumn('visita_pesquisa_satisfacao', 'alerta_detrator_enviado_em')) {
            Schema::table('visita_pesquisa_satisfacao', function (Blueprint $table) {
                $table->dropColumn('alerta_detrator_enviado_em');
            });
        }
    }
};
