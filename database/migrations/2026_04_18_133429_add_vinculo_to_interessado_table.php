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
            $table->enum('vinculo', ['Pai', 'Mãe', 'Parente', 'Tutor', 'Próprio Aluno'])->nullable()->after('origem_interessado_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interessado', function (Blueprint $table) {
            //
        });
    }
};
