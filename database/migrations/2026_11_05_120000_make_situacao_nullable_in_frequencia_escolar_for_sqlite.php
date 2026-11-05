<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alinha o esquema do SQLite (usado nos testes) com o do MySQL/MariaDB da produção.
 *
 * `frequencia_escolar.situacao` nasceu `NOT NULL` e a migration `make_situacao_nullable_in_frequencia_escolar` a tornou
 * nula só fora do SQLite. Na produção a coluna aceita nulo, e a chamada depende disso: ao abrir a aula ela cria uma
 * linha sem situação para cada aluno, até o professor marcar presença ou ausência. No SQLite essa gravação falhava, e
 * por isso a abertura da chamada para um aluno novo nunca pôde ser testada.
 *
 * Em MySQL/MariaDB não há nada a fazer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('frequencia_escolar', function (Blueprint $table) {
            $table->string('situacao')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Sem volta: o esquema da produção já aceita nulo, e voltar a exigir valor falharia se houver linha sem situação.
    }
};
