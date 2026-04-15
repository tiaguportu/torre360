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
        Schema::table('cronograma_aula', function (Blueprint $table) {
            $table->dropColumn('periodo_letivo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cronograma_aula', function (Blueprint $table) {
            $table->unsignedBigInteger('periodo_letivo_id')->nullable();
        });
    }
};
