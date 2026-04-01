<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curso', function (Blueprint $table) {
            $table->unsignedSmallInteger('minutos_por_periodo')->nullable()
                ->comment('Duração padrão de um período/aula em minutos. Ex: 50 = 50 minutos.');
        });
    }

    public function down(): void
    {
        Schema::table('curso', function (Blueprint $table) {
            $table->dropColumn('minutos_por_periodo');
        });
    }
};
