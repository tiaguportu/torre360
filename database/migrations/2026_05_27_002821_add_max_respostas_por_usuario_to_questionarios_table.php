<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('questionarios', function (Blueprint $table) {
            $table->integer('max_respostas_por_usuario')->nullable()->after('is_anonimo');
        });

        // Questionários existentes no banco de dados devem manter a limitação de 1 resposta
        DB::table('questionarios')->update(['max_respostas_por_usuario' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questionarios', function (Blueprint $table) {
            $table->dropColumn('max_respostas_por_usuario');
        });
    }
};
