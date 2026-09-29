<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::create('regua_cobrancas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->integer('dias_offset')->default(0)->comment('Negativo = antes do vencimento, 0 = no dia, positivo = apos vencimento');
            $table->string('tipo_gatilho')->default('antes_vencimento')->comment('antes_vencimento, no_vencimento, apos_vencimento');
            $table->string('canal')->default('todos')->comment('email, portal, push, todos');
            $table->string('assunto');
            $table->text('mensagem');
            $table->boolean('is_ativo')->default(true);
            $table->time('horario_envio')->default('08:00:00');
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('regua_cobranca_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regua_cobranca_id')->constrained('regua_cobrancas')->cascadeOnDelete();
            $table->foreignId('fatura_id')->constrained('faturas')->cascadeOnDelete();
            $table->foreignId('pessoa_id')->nullable()->constrained('pessoa')->nullOnDelete();
            $table->string('canal')->default('portal');
            $table->string('destinatario')->nullable();
            $table->text('mensagem_enviada');
            $table->string('status_envio')->default('sucesso')->comment('sucesso, falha');
            $table->text('erro')->nullable();
            $table->date('data_envio');
            $table->timestamps();

            $table->index(['fatura_id', 'data_envio']);
            $table->index(['regua_cobranca_id', 'data_envio']);
        });

        // Inserção das Réguas Padrão de Mercado
        DB::table('regua_cobrancas')->insert([
            [
                'nome' => 'Lembrete Preventivo (5 dias antes)',
                'dias_offset' => -5,
                'tipo_gatilho' => 'antes_vencimento',
                'canal' => 'todos',
                'assunto' => 'Lembrete: Sua fatura de {{ALUNO_NOME}} vencerá em 5 dias',
                'mensagem' => 'Olá {{RESPONSAVEL_NOME}}, informamos que a fatura nº {{NUMERO_FATURA}} do estudante {{ALUNO_NOME}} vencerá em 5 dias ({{DATA_VENCIMENTO}}), no valor de R$ {{VALOR}}. Evite atrasos e aproveite a tranquilidade do pagamento antecipado.',
                'is_ativo' => true,
                'horario_envio' => '08:00:00',
                'ordem' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nome' => 'Lembrete Próximo (2 dias antes)',
                'dias_offset' => -2,
                'tipo_gatilho' => 'antes_vencimento',
                'canal' => 'todos',
                'assunto' => 'Sua fatura de {{ALUNO_NOME}} vence em breve',
                'mensagem' => 'Prezado(a) {{RESPONSAVEL_NOME}}, lembramos que a fatura nº {{NUMERO_FATURA}} no valor de R$ {{VALOR}} com vencimento em {{DATA_VENCIMENTO}} vence em 2 dias. Acesse o portal para visualizar ou efetuar o pagamento.',
                'is_ativo' => true,
                'horario_envio' => '08:00:00',
                'ordem' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nome' => 'Vencimento Hoje (Dia D)',
                'dias_offset' => 0,
                'tipo_gatilho' => 'no_vencimento',
                'canal' => 'todos',
                'assunto' => 'Hoje é o dia de vencimento da sua fatura #{{NUMERO_FATURA}}',
                'mensagem' => 'Olá {{RESPONSAVEL_NOME}}! Lembramos que hoje ({{DATA_VENCIMENTO}}) é o vencimento da fatura nº {{NUMERO_FATURA}} ({{ALUNO_NOME}}), no valor de R$ {{VALOR}}. Não deixe para última hora.',
                'is_ativo' => true,
                'horario_envio' => '08:00:00',
                'ordem' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nome' => 'Aviso de Atraso Leve (3 dias após)',
                'dias_offset' => 3,
                'tipo_gatilho' => 'apos_vencimento',
                'canal' => 'todos',
                'assunto' => 'Fatura em atraso: Fatura #{{NUMERO_FATURA}} pendente de regularização',
                'mensagem' => 'Olá {{RESPONSAVEL_NOME}}, não identificamos a compensação da fatura nº {{NUMERO_FATURA}} do estudante {{ALUNO_NOME}}, vencida há {{DIAS_ATRASO}} dias (em {{DATA_VENCIMENTO}}), no valor de R$ {{VALOR}}. Caso já tenha efetuado o pagamento, desconsidere este aviso.',
                'is_ativo' => true,
                'horario_envio' => '08:00:00',
                'ordem' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nome' => 'Aviso de Cobrança (7 dias após)',
                'dias_offset' => 7,
                'tipo_gatilho' => 'apos_vencimento',
                'canal' => 'todos',
                'assunto' => 'Importante: Regularização de pendência financeira - Fatura #{{NUMERO_FATURA}}',
                'mensagem' => 'Prezado(a) {{RESPONSAVEL_NOME}}, a fatura nº {{NUMERO_FATURA}} ({{ALUNO_NOME}}) encontra-se vencida há {{DIAS_ATRASO}} dias. Pedimos a gentileza de regularizar a pendência financeira para evitar encargos adicionais.',
                'is_ativo' => true,
                'horario_envio' => '08:00:00',
                'ordem' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nome' => 'Notificação Crítica Pré-Bloqueio (15 dias após)',
                'dias_offset' => 15,
                'tipo_gatilho' => 'apos_vencimento',
                'canal' => 'todos',
                'assunto' => 'Notificação de Cobrança: Fatura #{{NUMERO_FATURA}} em aberto há 15 dias',
                'mensagem' => 'Prezado(a) {{RESPONSAVEL_NOME}}, consta em aberto em nosso sistema a fatura nº {{NUMERO_FATURA}} há mais de 15 dias. Solicitamos que entre em contato com nosso setor financeiro ou acesse o portal imediatamente para negociação e quitação.',
                'is_ativo' => true,
                'horario_envio' => '08:00:00',
                'ordem' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Permissões Shield
        try {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            $permissions = [
                'ViewAny:ReguaCobranca',
                'View:ReguaCobranca',
                'Create:ReguaCobranca',
                'Update:ReguaCobranca',
                'Delete:ReguaCobranca',
                'Execute:ReguaCobranca',
            ];

            foreach ($permissions as $permissionName) {
                Permission::firstOrCreate(
                    ['name' => $permissionName, 'guard_name' => 'web'],
                    ['name' => $permissionName, 'guard_name' => 'web']
                );
            }

            $rolesToSync = Role::whereIn('name', ['super_admin', 'admin', 'financeiro', 'coordenador'])->get();

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
        Schema::dropIfExists('regua_cobranca_logs');
        Schema::dropIfExists('regua_cobrancas');

        try {
            $permissions = [
                'ViewAny:ReguaCobranca',
                'View:ReguaCobranca',
                'Create:ReguaCobranca',
                'Update:ReguaCobranca',
                'Delete:ReguaCobranca',
                'Execute:ReguaCobranca',
            ];

            foreach ($permissions as $permissionName) {
                Permission::where('name', $permissionName)->delete();
            }

            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (Throwable $e) {
        }
    }
};
