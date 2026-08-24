<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mensagem_whatsapp_template', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('conteudo');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        DB::table('mensagem_whatsapp_template')->insert([
            [
                'nome' => 'Lembrete de Visita Agendada',
                'conteudo' => 'Olá, [Nome do Responsável]! Tudo bem? Passando para confirmar a visita de [Nome do Aluno] agendada para [Horário de Visita Agendada]. Contamos com vocês! 😊',
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nome' => 'Retomar Contato',
                'conteudo' => 'Olá, [Nome do Responsável]! Notamos que faz um tempo desde nosso último contato sobre a matrícula de [Nome do Aluno]. Podemos conversar e tirar suas dúvidas?',
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mensagem_whatsapp_template');
    }
};
