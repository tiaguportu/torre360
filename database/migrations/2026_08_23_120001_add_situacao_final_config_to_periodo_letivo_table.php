<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodo_letivo', function (Blueprint $table) {
            $table->decimal('nota_aprovacao', 4, 2)->default(7.00)->after('data_fim');
            $table->decimal('nota_recuperacao_minima', 4, 2)->default(5.00)->after('nota_aprovacao');
        });
    }

    public function down(): void
    {
        Schema::table('periodo_letivo', function (Blueprint $table) {
            $table->dropColumn(['nota_aprovacao', 'nota_recuperacao_minima']);
        });
    }
};
