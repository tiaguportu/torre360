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
        Schema::table('matriculas', function (Blueprint $table) {
            if (!Schema::hasColumn('matriculas', 'situacao_matricula_id')) {
                $table->foreignId('situacao_matricula_id')->nullable()->constrained('situacao_matriculas')->onDelete('set null');
            }
            if (Schema::hasColumn('matriculas', 'status')) {
                $table->dropColumn('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            if (Schema::hasColumn('matriculas', 'situacao_matricula_id')) {
                $table->dropForeign(['situacao_matricula_id']);
                $table->dropColumn('situacao_matricula_id');
            }
            if (!Schema::hasColumn('matriculas', 'status')) {
                $table->string('status')->default('ativa');
            }
        });
    }
};
