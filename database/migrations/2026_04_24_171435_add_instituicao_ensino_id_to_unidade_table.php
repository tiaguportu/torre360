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
        Schema::table('unidade', function (Blueprint $table) {
            $table->foreignId('instituicao_ensino_id')->nullable()->after('id')->constrained('instituicao_ensinos')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unidade', function (Blueprint $table) {
            $table->dropForeign(['instituicao_ensino_id']);
            $table->dropColumn('instituicao_ensino_id');
        });
    }
};
