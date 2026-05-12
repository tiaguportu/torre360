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
        Schema::table('questionario_blocos', function (Blueprint $table) {
            $table->string('identificador')->nullable()->after('questionario_id')->comment('ID textual para importação/exportação e unicidade');
        });

        Schema::table('questionario_perguntas', function (Blueprint $table) {
            $table->string('identificador')->nullable()->after('questionario_bloco_id')->comment('ID textual para importação/exportação e unicidade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questionario_blocos', function (Blueprint $table) {
            $table->dropColumn('identificador');
        });

        Schema::table('questionario_perguntas', function (Blueprint $table) {
            $table->dropColumn('identificador');
        });
    }
};
