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
        Schema::table('interessado', function (Blueprint $table) {
            $table->decimal('valor_estimado', 10, 2)->nullable()->after('observacoes');
            $table->string('temperatura')->nullable()->after('valor_estimado');
            $table->string('motivo_perda')->nullable()->after('temperatura');
            $table->dateTime('data_primeiro_contato')->nullable()->after('motivo_perda');
            $table->dateTime('data_conversao')->nullable()->after('data_primeiro_contato');
        });

        Schema::table('historico_contato', function (Blueprint $table) {
            $table->foreignId('usuario_id')->nullable()->after('interessado_id')->constrained('users')->nullOnDelete();
            $table->unsignedInteger('duracao_minutos')->nullable()->after('data_contato');
            $table->string('resultado')->nullable()->after('duracao_minutos');
        });

        Schema::table('status_interessado', function (Blueprint $table) {
            $table->boolean('is_final')->default(false)->after('ordem');
            $table->boolean('is_ganho')->default(false)->after('is_final');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interessado', function (Blueprint $table) {
            $table->dropColumn(['valor_estimado', 'temperatura', 'motivo_perda', 'data_primeiro_contato', 'data_conversao']);
        });

        Schema::table('historico_contato', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
            $table->dropColumn(['usuario_id', 'duracao_minutos', 'resultado']);
        });

        Schema::table('status_interessado', function (Blueprint $table) {
            $table->dropColumn(['is_final', 'is_ganho']);
        });
    }
};
