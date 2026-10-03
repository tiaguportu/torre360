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
        Schema::create('regua_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('gatilho')->comment('lead_criado, visita_lembrete, visita_realizada, visita_faltou, lead_estagnado, contato_atrasado');
            $table->integer('dias_offset')->default(0)->comment('Intervalo em dias para o gatilho');
            $table->string('canal')->default('email')->comment('email, notificacao_sistema');
            $table->string('assunto');
            $table->longText('mensagem');
            $table->foreignId('origem_interessado_id')->nullable()->constrained('origem_interessado')->nullOnDelete();
            $table->foreignId('status_interessado_id')->nullable()->constrained('status_interessado')->nullOnDelete();
            $table->boolean('is_ativo')->default(true);
            $table->time('horario_envio')->default('08:00:00');
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('regua_follow_up_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regua_follow_up_id')->constrained('regua_follow_ups')->cascadeOnDelete();
            $table->foreignId('interessado_id')->constrained('interessado')->cascadeOnDelete();
            $table->foreignId('visita_interessado_id')->nullable()->constrained('visita_interessado')->nullOnDelete();
            $table->string('canal')->default('email');
            $table->string('destinatario')->nullable();
            $table->string('assunto_enviado');
            $table->longText('mensagem_enviada');
            $table->string('status_envio')->default('sucesso')->comment('sucesso, falha');
            $table->text('erro')->nullable();
            $table->date('data_envio');
            $table->timestamps();

            $table->index(['regua_follow_up_id', 'data_envio']);
            $table->index(['interessado_id', 'data_envio']);
        });

        // Inserção das Réguas Padrão de Follow-up Educacional
        DB::table('regua_follow_ups')->insert([
            [
                'nome' => 'Boas-vindas para Novos Leads',
                'gatilho' => 'lead_criado',
                'dias_offset' => 0,
                'canal' => 'email',
                'assunto' => 'Bem-vindo(a) ao {{ESCOLA_NOME}}! Ficamos felizes com seu interesse',
                'mensagem' => '<p>Olá, <strong>{{NOME_RESPONSAVEL}}</strong>!</p><p>Recebemos com muita alegria o seu interesse em conhecer o <strong>{{ESCOLA_NOME}}</strong> para a educação de <strong>{{NOME_ALUNO}}</strong>.</p><p>Nossa equipe pedagógica já está preparando todas as informações sobre nossa proposta e rotina escolar. Que tal agendar uma visita presencial para conhecer nossa estrutura de perto e tirar todas as suas dúvidas com nossos consultores?</p><p>Estamos à disposição para ajudar você nesta escolha tão importante!</p><p>Com carinho,<br><strong>Equipe de Admissões - {{ESCOLA_NOME}}</strong></p>',
                'is_ativo' => true,
                'horario_envio' => '08:00:00',
                'ordem' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nome' => 'Lembrete de Visita à Escola (1 dia antes)',
                'gatilho' => 'visita_lembrete',
                'dias_offset' => 1,
                'canal' => 'email',
                'assunto' => 'Lembrete: Sua visita ao {{ESCOLA_NOME}} é amanhã!',
                'mensagem' => '<p>Olá, <strong>{{NOME_RESPONSAVEL}}</strong>!</p><p>Estamos ansiosos para receber você e sua família amanhã, <strong>{{DATA_VISITA}} às {{HORARIO_VISITA}}</strong>, para um tour pedagógico em nossa escola.</p><p>📍 <strong>Orientações para sua chegada:</strong><br>- Dirija-se à portaria principal e informe seu nome na recepção.<br>- Temos estacionamento para visitantes no local.<br>- Nosso consultor <strong>{{NOME_CONSULTOR}}</strong> estará aguardando para apresentar todos os espaços da escola e conversar sobre a série <strong>{{SERIE_INTERESSE}}</strong>.</p><p>Caso precise remarcar, por favor responda a este e-mail ou entre em contato conosco.</p><p>Até amanhã!</p>',
                'is_ativo' => true,
                'horario_envio' => '08:00:00',
                'ordem' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nome' => 'Agradecimento Pós-Visita Realizada (1 dia após)',
                'gatilho' => 'visita_realizada',
                'dias_offset' => 1,
                'canal' => 'email',
                'assunto' => 'Obrigado por nos visitar! O que achou da nossa escola?',
                'mensagem' => '<p>Olá, <strong>{{NOME_RESPONSAVEL}}</strong>!</p><p>Foi um prazer imenso receber você e sua família ontem em nosso colégio!</p><p>Esperamos que tenham gostado de conhecer nosso projeto pedagógico, nossa equipe e o ambiente que preparamos com tanto cuidado para acolher <strong>{{NOME_ALUNO}}</strong>.</p><p>Ficou com alguma dúvida ou gostaria de receber uma simulação das condições de matrícula para a série <strong>{{SERIE_INTERESSE}}</strong>? Nosso consultor <strong>{{NOME_CONSULTOR}}</strong> está à sua inteira disposição.</p><p>Será uma honra fazer parte da formação do seu filho(a)!</p>',
                'is_ativo' => true,
                'horario_envio' => '08:00:00',
                'ordem' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nome' => 'Reagendamento de Falta na Visita (No-Show)',
                'gatilho' => 'visita_faltou',
                'dias_offset' => 1,
                'canal' => 'email',
                'assunto' => 'Sentimos sua falta no {{ESCOLA_NOME}}! Vamos remarcar?',
                'mensagem' => '<p>Olá, <strong>{{NOME_RESPONSAVEL}}</strong>!</p><p>Notamos que você não pôde comparecer à visita agendada ontem. Sabemos que imprevistos acontecem na rotina!</p><p>Queremos muito apresentar nossa proposta pedagógica e mostrar como o <strong>{{ESCOLA_NOME}}</strong> pode transformar o aprendizado de <strong>{{NOME_ALUNO}}</strong>.</p><p>Quando seria um bom momento para você vir nos conhecer? Responda a este e-mail indicando o melhor dia e horário para agendarmos um novo tour.</p><p>Esperamos por você!</p>',
                'is_ativo' => true,
                'horario_envio' => '08:00:00',
                'ordem' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nome' => 'Alerta Interno: Lead Estagnado há 7 dias',
                'gatilho' => 'lead_estagnado',
                'dias_offset' => 7,
                'canal' => 'notificacao_sistema',
                'assunto' => 'Atenção: Lead {{NOME_RESPONSAVEL}} sem interação há 7 dias',
                'mensagem' => 'O lead {{NOME_RESPONSAVEL}} (Aluno: {{NOME_ALUNO}}, Série: {{SERIE_INTERESSE}}) está há mais de 7 dias sem qualquer contato registrado. Verifique a temperatura e realize uma nova abordagem para evitar que o lead esfrie.',
                'is_ativo' => true,
                'horario_envio' => '08:00:00',
                'ordem' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Provisionamento das Permissões do Shield
        try {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            $permissions = [
                'ViewAny:ReguaFollowUp',
                'View:ReguaFollowUp',
                'Create:ReguaFollowUp',
                'Update:ReguaFollowUp',
                'Delete:ReguaFollowUp',
                'Execute:ReguaFollowUp',
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
        Schema::dropIfExists('regua_follow_up_logs');
        Schema::dropIfExists('regua_follow_ups');

        try {
            $permissions = [
                'ViewAny:ReguaFollowUp',
                'View:ReguaFollowUp',
                'Create:ReguaFollowUp',
                'Update:ReguaFollowUp',
                'Delete:ReguaFollowUp',
                'Execute:ReguaFollowUp',
            ];

            foreach ($permissions as $permissionName) {
                Permission::where('name', $permissionName)->delete();
            }

            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (Throwable $e) {
        }
    }
};
