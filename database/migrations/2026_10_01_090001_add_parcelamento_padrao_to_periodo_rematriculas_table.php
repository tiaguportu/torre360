<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodo_rematriculas', function (Blueprint $table) {
            $table->unsignedInteger('quantidade_parcelas_padrao')->default(12)->after('valor_taxa');
            $table->decimal('valor_entrada_padrao', 12, 2)->default(0)->after('quantidade_parcelas_padrao');
        });
    }

    public function down(): void
    {
        Schema::table('periodo_rematriculas', function (Blueprint $table) {
            $table->dropColumn(['quantidade_parcelas_padrao', 'valor_entrada_padrao']);
        });
    }
};
