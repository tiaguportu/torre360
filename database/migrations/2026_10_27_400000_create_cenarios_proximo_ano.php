<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cenário salvo do simulador do próximo ano letivo (Onda 76): os parâmetros escolhidos e a fotografia do resultado no dia.
        Schema::create('cenarios_proximo_ano', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 120);
            $table->foreignId('periodo_rematricula_id')->constrained('periodo_rematriculas')->cascadeOnDelete();
            $table->json('parametros');
            $table->json('resultado');
            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('periodo_rematricula_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cenarios_proximo_ano');
    }
};
