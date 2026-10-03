<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicacao_interessados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicador_pessoa_id')->constrained('pessoa')->cascadeOnDelete();
            $table->foreignId('interessado_id')->constrained('interessado')->cascadeOnDelete();
            $table->string('codigo_indicacao', 50)->nullable()->index();
            $table->string('status', 30)->default('pendente')->index(); // pendente, matriculado, recompensado, cancelado
            $table->string('recompensa_tipo', 50)->nullable(); // desconto_mensalidade, desconto_rematricula, brinde, outro
            $table->string('recompensa_detalhe')->nullable();
            $table->decimal('valor_recompensa', 10, 2)->nullable();
            $table->timestamp('data_conversao')->nullable();
            $table->timestamp('data_recompensa')->nullable();
            $table->foreignId('recompensado_por_usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });

        // Adiciona campo codigo_indicacao na tabela pessoa caso não exista
        if (! Schema::hasColumn('pessoa', 'codigo_indicacao')) {
            Schema::table('pessoa', function (Blueprint $table) {
                $table->string('codigo_indicacao', 30)->nullable()->unique()->after('cpf');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('indicacao_interessados');

        if (Schema::hasColumn('pessoa', 'codigo_indicacao')) {
            Schema::table('pessoa', function (Blueprint $table) {
                $table->dropColumn('codigo_indicacao');
            });
        }
    }
};
