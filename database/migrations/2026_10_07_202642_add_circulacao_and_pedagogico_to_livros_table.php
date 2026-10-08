<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('livros', function (Blueprint $table) {
            $table->string('codigo')->nullable()->unique()->after('id');
            $table->string('faixa_etaria')->nullable()->after('categoria');
            $table->json('segmentos')->nullable()->after('faixa_etaria');
        });
    }

    public function down(): void
    {
        Schema::table('livros', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->dropColumn(['codigo', 'faixa_etaria', 'segmentos']);
        });
    }
};
