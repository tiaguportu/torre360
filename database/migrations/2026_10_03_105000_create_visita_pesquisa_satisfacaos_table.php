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
        Schema::create('visita_pesquisa_satisfacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visita_interessado_id')->unique()->constrained('visita_interessado')->cascadeOnDelete();
            $table->foreignId('interessado_id')->constrained('interessado')->cascadeOnDelete();
            $table->string('token', 64)->unique()->index();
            $table->unsignedTinyInteger('nota_nps')->nullable()->comment('Escala 0 a 10: recomendação geral');
            $table->unsignedTinyInteger('nota_atendimento')->nullable()->comment('Escala 1 a 5: acolhimento e consultor');
            $table->unsignedTinyInteger('nota_infraestrutura')->nullable()->comment('Escala 1 a 5: espaço e instalações');
            $table->unsignedTinyInteger('nota_proposta_pedagogica')->nullable()->comment('Escala 1 a 5: projeto pedagógico');
            $table->text('comentario')->nullable();
            $table->timestamp('respondido_em')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        // Provisionamento de Permissões Shield
        try {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            $permissions = [
                'ViewAny:PesquisaSatisfacaoVisita',
                'View:PesquisaSatisfacaoVisita',
                'Delete:PesquisaSatisfacaoVisita',
            ];

            foreach ($permissions as $permissionName) {
                Permission::firstOrCreate(
                    ['name' => $permissionName, 'guard_name' => 'web'],
                    ['name' => $permissionName, 'guard_name' => 'web']
                );
            }

            $rolesToSync = Role::whereIn('name', ['super_admin', 'admin', 'coordenador'])->get();

            foreach ($rolesToSync as $role) {
                foreach ($permissions as $permissionName) {
                    $permission = Permission::where('name', $permissionName)->first();
                    if ($permission && ! $role->hasPermissionTo($permission)) {
                        $role->givePermissionTo($permission);
                    }
                }
            }

            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (Throwable $e) {
            // Em testes ou ambientes onde roles ainda não existem
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visita_pesquisa_satisfacao');

        try {
            $permissions = [
                'ViewAny:PesquisaSatisfacaoVisita',
                'View:PesquisaSatisfacaoVisita',
                'Delete:PesquisaSatisfacaoVisita',
            ];

            foreach ($permissions as $permissionName) {
                Permission::where('name', $permissionName)->delete();
            }

            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (Throwable $e) {
        }
    }
};
