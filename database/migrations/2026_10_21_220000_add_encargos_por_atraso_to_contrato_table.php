<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multa e juros de mora do contrato (Onda 62). Nulo = vale o padrão da escola em `config/financeiro.php`. Ficam no
 * contrato porque são o que a família assinou: mudar o padrão da escola não pode alterar contratos já firmados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contrato', function (Blueprint $table) {
            if (! Schema::hasColumn('contrato', 'multa_percentual')) {
                $table->decimal('multa_percentual', 5, 2)->nullable();
            }

            if (! Schema::hasColumn('contrato', 'juros_mensal_percentual')) {
                $table->decimal('juros_mensal_percentual', 5, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contrato', function (Blueprint $table) {
            foreach (['juros_mensal_percentual', 'multa_percentual'] as $coluna) {
                if (Schema::hasColumn('contrato', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
