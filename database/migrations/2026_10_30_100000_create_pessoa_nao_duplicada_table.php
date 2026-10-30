<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pares de cadastros que a secretaria conferiu e confirmou serem pessoas diferentes (gêmeos, homônimos, pai e filho com o mesmo
     * nome). Sem isso a lista de possíveis duplicados repetiria o mesmo par para sempre. O par é guardado em ordem (menor id, maior id)
     * para que A+B e B+A sejam o mesmo registro.
     */
    public function up(): void
    {
        Schema::create('pessoa_nao_duplicada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_menor_id')->constrained('pessoa')->cascadeOnDelete();
            $table->foreignId('pessoa_maior_id')->constrained('pessoa')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pessoa_menor_id', 'pessoa_maior_id'], 'pessoa_nao_duplicada_par_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoa_nao_duplicada');
    }
};
