<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('situacao_documento_inserido');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('situacao_documento_inserido', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
        });

        // Inserir valores padrão
        DB::table('situacao_documento_inserido')->insert([
            ['id' => 1, 'nome' => 'Pendente', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'nome' => 'Em Análise', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'nome' => 'Aprovado', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'nome' => 'Rejeitado', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
};
