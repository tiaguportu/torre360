<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bolsa_concedidas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->foreignId('tipo_bolsa_id')->constrained('tipo_bolsas')->cascadeOnDelete();
            $table->unsignedTinyInteger('percentual');
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->string('status')->default('solicitada');
            $table->foreignId('aprovado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bolsa_concedidas');
    }
};
