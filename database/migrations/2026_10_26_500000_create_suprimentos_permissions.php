<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Suprimentos (Ondas 16, 38 e 43). O orçamento é da administração e do financeiro; o estoque, de quem cuida do almoxarifado
     * (secretaria); qualquer pessoa da equipe pede uma compra, mas só vê as próprias. A alçada de aprovação sobe com o valor:
     * coordenação até o primeiro teto, direção (administrador) até o segundo, mantenedora (super admin) acima. Papéis que a escola
     * não criou (como o financeiro) são ignorados; quem montar perfis próprios concede as permissões na tela de papéis.
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        // Orçamento anual e Orçado x realizado
        'ViewAny:Orcamento' => ['super_admin', 'admin', 'financeiro'],
        'View:Orcamento' => ['super_admin', 'admin', 'financeiro'],
        'Create:Orcamento' => ['super_admin', 'admin', 'financeiro'],
        'Update:Orcamento' => ['super_admin', 'admin', 'financeiro'],
        'Delete:Orcamento' => ['super_admin', 'admin', 'financeiro'],
        'Aprovar:Orcamento' => ['super_admin', 'admin'],
        'View:OrcadoRealizado' => ['super_admin', 'admin', 'financeiro', 'coordenador'],

        // Estoque de consumíveis
        'ViewAny:ItemEstoque' => ['super_admin', 'admin', 'secretaria', 'financeiro', 'coordenador'],
        'View:ItemEstoque' => ['super_admin', 'admin', 'secretaria', 'financeiro', 'coordenador'],
        'Create:ItemEstoque' => ['super_admin', 'admin', 'secretaria'],
        'Update:ItemEstoque' => ['super_admin', 'admin', 'secretaria'],
        'Delete:ItemEstoque' => ['super_admin', 'admin'],
        'Movimentar:ItemEstoque' => ['super_admin', 'admin', 'secretaria', 'coordenador'],

        // Compras e cotações
        'ViewAny:RequisicaoCompra' => ['super_admin', 'admin', 'secretaria', 'financeiro', 'coordenador', 'professor'],
        'View:RequisicaoCompra' => ['super_admin', 'admin', 'secretaria', 'financeiro', 'coordenador', 'professor'],
        'Create:RequisicaoCompra' => ['super_admin', 'admin', 'secretaria', 'financeiro', 'coordenador', 'professor'],
        'Update:RequisicaoCompra' => ['super_admin', 'admin', 'secretaria', 'financeiro', 'coordenador', 'professor'],
        'VerTodas:RequisicaoCompra' => ['super_admin', 'admin', 'financeiro'],
        'Cotar:RequisicaoCompra' => ['super_admin', 'admin', 'secretaria', 'financeiro'],
        'Receber:PedidoCompra' => ['super_admin', 'admin', 'secretaria'],
        'AprovarNivel1:PedidoCompra' => ['super_admin', 'admin', 'coordenador'],
        'AprovarNivel2:PedidoCompra' => ['super_admin', 'admin'],
        'AprovarNivel3:PedidoCompra' => ['super_admin'],
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
