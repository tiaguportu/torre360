<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categoria_avaliacao', function (Blueprint $table) {
            $table->boolean('eh_recuperacao')->default(false)->after('categoria_avaliacao_substituicao_id');
        });
    }

    public function down(): void
    {
        Schema::table('categoria_avaliacao', function (Blueprint $table) {
            $table->dropColumn('eh_recuperacao');
        });
    }
};
