<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('consentimento_matriculas')) {
            Schema::create('consentimento_matriculas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
                $table->foreignId('tipo_consentimento_id')->constrained('tipo_consentimentos')->cascadeOnDelete();
                $table->string('status')->default('pendente');
                $table->timestamp('respondido_em')->nullable();
                $table->foreignId('respondido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('ip_resposta')->nullable();
                $table->date('vigencia_inicio')->nullable();
                $table->date('vigencia_fim')->nullable();
                $table->text('observacao')->nullable();
                $table->timestamps();

                $table->unique(['matricula_id', 'tipo_consentimento_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consentimento_matriculas');
    }
};
