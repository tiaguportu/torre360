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
        Schema::table('dia_nao_letivo', function (Blueprint $table) {
            $table->unique(['periodo_letivo_id', 'data'], 'unique_periodo_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dia_nao_letivo', function (Blueprint $table) {
            $table->dropUnique('unique_periodo_data');
        });
    }
};
