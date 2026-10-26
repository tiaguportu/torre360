<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Próxima ação do lead (Onda 84). `data_proximo_contato` já diz QUANDO; a próxima ação diz O QUE fazer ("ligar para
 * confirmar a visita", "mandar a proposta"), para o consultor e para quem assume a carteira saberem sem reler o histórico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interessado', function (Blueprint $table) {
            $table->string('proxima_acao', 160)->nullable()->after('data_proximo_contato');
        });
    }

    public function down(): void
    {
        Schema::table('interessado', function (Blueprint $table) {
            $table->dropColumn('proxima_acao');
        });
    }
};
