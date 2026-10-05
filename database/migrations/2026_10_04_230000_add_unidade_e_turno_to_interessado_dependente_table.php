<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unidade e turno de preferência do formulário público deixam de ser só texto dentro de
     * `interessado.observacoes` e passam a ser colunas do dependente (cada aluno pode querer uma
     * unidade/turno diferente), permitindo filtrar e rotear leads por unidade.
     */
    public function up(): void
    {
        Schema::table('interessado_dependente', function (Blueprint $table) {
            if (! Schema::hasColumn('interessado_dependente', 'unidade_id')) {
                $table->foreignId('unidade_id')->nullable()->after('serie_id')->constrained('unidade')->nullOnDelete();
            }

            if (! Schema::hasColumn('interessado_dependente', 'turno_preferencia')) {
                $table->string('turno_preferencia', 30)->nullable()->after('unidade_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('interessado_dependente', function (Blueprint $table) {
            if (Schema::hasColumn('interessado_dependente', 'unidade_id')) {
                $table->dropConstrainedForeignId('unidade_id');
            }

            if (Schema::hasColumn('interessado_dependente', 'turno_preferencia')) {
                $table->dropColumn('turno_preferencia');
            }
        });
    }
};
