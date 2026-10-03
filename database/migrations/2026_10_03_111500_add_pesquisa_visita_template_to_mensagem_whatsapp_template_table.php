<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $existe = DB::table('mensagem_whatsapp_template')
            ->where('nome', 'Pesquisa de Satisfação Pós-Visita')
            ->exists();

        if (! $existe) {
            DB::table('mensagem_whatsapp_template')->insert([
                'nome' => 'Pesquisa de Satisfação Pós-Visita',
                'conteudo' => "Olá, [Nome do Responsável]! 😊 Ficamos muito felizes em receber você e sua família para conhecer nossa escola!\n\nPara que possamos acolher cada vez melhor nossos alunos e famílias, gostaríamos muito de saber como foi sua experiência no tour escolar. Leva menos de 1 minutinho:\n\n👉 [Link da Pesquisa da Visita]\n\nSeu feedback é fundamental para nós. Muito obrigado(a) pelo carinho e pelo seu tempo! 🏫✨",
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('mensagem_whatsapp_template')
            ->where('nome', 'Pesquisa de Satisfação Pós-Visita')
            ->delete();
    }
};
