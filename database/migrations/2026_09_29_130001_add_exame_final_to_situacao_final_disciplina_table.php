<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('situacao_final_disciplina', function (Blueprint $table) {
            $table->decimal('nota_exame_final', 5, 2)->nullable()->after('situacao');
            $table->decimal('media_final_pos_exame', 5, 2)->nullable()->after('nota_exame_final');
            $table->string('situacao_final_pos_exame')->nullable()->after('media_final_pos_exame');
        });
    }

    public function down(): void
    {
        Schema::table('situacao_final_disciplina', function (Blueprint $table) {
            $table->dropColumn(['nota_exame_final', 'media_final_pos_exame', 'situacao_final_pos_exame']);
        });
    }
};
