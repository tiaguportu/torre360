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
        Schema::table('documento_inserido', function (Blueprint $table) {
            $table->string('situacao')->default('pendente')->after('situacao_documento_inserido_id');
        });

        // Migrar dados
        DB::table('documento_inserido')->where('situacao_documento_inserido_id', 1)->update(['situacao' => 'pendente']);
        DB::table('documento_inserido')->where('situacao_documento_inserido_id', 2)->update(['situacao' => 'em_analise']);
        DB::table('documento_inserido')->where('situacao_documento_inserido_id', 3)->update(['situacao' => 'aprovado']);
        DB::table('documento_inserido')->where('situacao_documento_inserido_id', 4)->update(['situacao' => 'rejeitado']);

        Schema::table('documento_inserido', function (Blueprint $table) {
            $table->dropForeign(['situacao_documento_inserido_id']);
            $table->dropColumn('situacao_documento_inserido_id');
        });

        // Opcionalmente remover a tabela antiga se não for mais usada
        // Schema::dropIfExists('situacao_documento_inserido');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documento_inserido', function (Blueprint $table) {
            $table->unsignedBigInteger('situacao_documento_inserido_id')->nullable()->after('matricula_id');
        });

        // Reverter dados
        DB::table('documento_inserido')->where('situacao', 'pendente')->update(['situacao_documento_inserido_id' => 1]);
        DB::table('documento_inserido')->where('situacao', 'em_analise')->update(['situacao_documento_inserido_id' => 2]);
        DB::table('documento_inserido')->where('situacao', 'aprovado')->update(['situacao_documento_inserido_id' => 3]);
        DB::table('documento_inserido')->where('situacao', 'rejeitado')->update(['situacao_documento_inserido_id' => 4]);

        Schema::table('documento_inserido', function (Blueprint $table) {
            $table->foreign('situacao_documento_inserido_id', 'doc_ins_sit_fk')
                ->references('id')
                ->on('situacao_documento_inserido');
            $table->dropColumn('situacao');
        });
    }
};
