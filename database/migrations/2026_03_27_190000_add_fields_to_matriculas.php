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
        if (config('database.default') === 'sqlite') {
            Schema::table('matriculas', function (Blueprint $table) {
                $table->unsignedBigInteger('pessoa_id')->nullable();
                $table->unsignedBigInteger('turma_id')->nullable();
                $table->date('data_matricula')->nullable();
                $table->string('status')->default('ativa');
            });

            Schema::table('turmas', function (Blueprint $table) {
                if (!Schema::hasColumn('turmas', 'serie_id')) {
                    $table->unsignedBigInteger('serie_id')->nullable();
                }
                if (!Schema::hasColumn('turmas', 'turno_id')) {
                    $table->unsignedBigInteger('turno_id')->nullable();
                }
                if (!Schema::hasColumn('turmas', 'codigo')) {
                    $table->string('codigo')->nullable();
                }
            });
        } else {
            Schema::table('matriculas', function (Blueprint $table) {
                $table->foreignId('pessoa_id')->nullable()->constrained('pessoas')->onDelete('cascade');
                $table->foreignId('turma_id')->nullable()->constrained('turmas')->onDelete('cascade');
                $table->date('data_matricula')->nullable();
                $table->string('status')->default('ativa'); // ativa, cancelada, trancada, concluída
            });

            Schema::table('turmas', function (Blueprint $table) {
                if (!Schema::hasColumn('turmas', 'serie_id')) {
                    $table->foreignId('serie_id')->nullable()->constrained('series')->onDelete('cascade');
                }
                if (!Schema::hasColumn('turmas', 'turno_id')) {
                    $table->foreignId('turno_id')->nullable()->constrained('turnos')->onDelete('cascade');
                }
                if (!Schema::hasColumn('turmas', 'codigo')) {
                    $table->string('codigo')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['pessoa_id']);
                $table->dropForeign(['turma_id']);
            }
            $table->dropColumn(['pessoa_id', 'turma_id', 'data_matricula', 'status']);
        });

        Schema::table('turmas', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['serie_id']);
                $table->dropForeign(['turno_id']);
            }
            $table->dropColumn(['serie_id', 'turno_id', 'codigo']);
        });
    }
};
