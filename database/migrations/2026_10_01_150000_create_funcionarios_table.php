<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funcionarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoa')->cascadeOnDelete();
            $table->string('cargo');
            $table->date('data_admissao');
            $table->date('data_desligamento')->nullable();
            $table->string('regime');
            $table->unsignedSmallInteger('carga_horaria_semanal')->nullable();
            $table->foreignId('unidade_id')->nullable()->constrained('unidade')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funcionarios');
    }
};
