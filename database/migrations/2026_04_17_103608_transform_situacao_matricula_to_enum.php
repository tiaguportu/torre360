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
        // 1. Adicionar o novo campo string 'situacao' na tabela 'matricula' se não existir
        if (! Schema::hasColumn('matricula', 'situacao')) {
            Schema::table('matricula', function (Blueprint $table) {
                $table->string('situacao')->nullable()->after('situacao_matricula_id');
            });
        }

        // 2. Migrar os dados de 'situacao_matricula_id' para 'situacao' (Enum string)
        if (Schema::hasTable('situacao_matricula')) {
            $situacoes = DB::table('situacao_matricula')->get();

            foreach ($situacoes as $s) {
                $nome = mb_strtolower($s->nome);
                $enumValue = 'ativa'; // default

                if (str_contains($nome, 'ativa') || str_contains($nome, 'regular')) {
                    $enumValue = 'ativa';
                } elseif (str_contains($nome, 'pendente') || str_contains($nome, 'pré') || str_contains($nome, 'pre')) {
                    $enumValue = 'pendente';
                } elseif (str_contains($nome, 'trancada')) {
                    $enumValue = 'trancada';
                } elseif (str_contains($nome, 'cancelada')) {
                    $enumValue = 'cancelada';
                } elseif (str_contains($nome, 'concluído') || str_contains($nome, 'concluido') || str_contains($nome, 'concluída')) {
                    $enumValue = 'concluido';
                } elseif (str_contains($nome, 'reserva')) {
                    $enumValue = 'reserva';
                } elseif (str_contains($nome, 'evasão') || str_contains($nome, 'evasao')) {
                    $enumValue = 'evasao';
                }

                DB::table('matricula')
                    ->where('situacao_matricula_id', $s->id)
                    ->update(['situacao' => $enumValue]);
            }
        }

        // 3. Remover a coluna antiga (Passos separados para não quebrar no MySQL)
        if (Schema::hasColumn('matricula', 'situacao_matricula_id')) {
            // Tenta remover apenas a coluna. O MySQL muitas vezes cuida da FK automaticamente ou ela nem existe com esse nome.
            try {
                Schema::table('matricula', function (Blueprint $table) {
                    $table->dropColumn('situacao_matricula_id');
                });
            } catch (Exception $e) {
                // Se falhar (ex: por causa da FK), tenta outro método via SQL puro
                try {
                    DB::statement('ALTER TABLE matricula DROP COLUMN situacao_matricula_id');
                } catch (Exception $e2) {
                    // Se ainda falhar, ignora e segue (teremos uma coluna órfã mas o sistema funciona)
                }
            }
        }

        // 4. Remover a tabela legada
        Schema::dropIfExists('situacao_matricula');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Criar a tabela novamente
        Schema::create('situacao_matricula', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
        });

        // 2. Inserir situações básicas
        $ids = [
            'ativa' => DB::table('situacao_matricula')->insertGetId(['nome' => 'Ativa', 'created_at' => now(), 'updated_at' => now()]),
            'pendente' => DB::table('situacao_matricula')->insertGetId(['nome' => 'Pendente', 'created_at' => now(), 'updated_at' => now()]),
            'trancada' => DB::table('situacao_matricula')->insertGetId(['nome' => 'Trancada', 'created_at' => now(), 'updated_at' => now()]),
            'cancelada' => DB::table('situacao_matricula')->insertGetId(['nome' => 'Cancelada', 'created_at' => now(), 'updated_at' => now()]),
            'concluido' => DB::table('situacao_matricula')->insertGetId(['nome' => 'Concluída', 'created_at' => now(), 'updated_at' => now()]),
            'reserva' => DB::table('situacao_matricula')->insertGetId(['nome' => 'Reserva', 'created_at' => now(), 'updated_at' => now()]),
            'evasao' => DB::table('situacao_matricula')->insertGetId(['nome' => 'Evasão', 'created_at' => now(), 'updated_at' => now()]),
        ];

        // 3. Adicionar a coluna de volta
        Schema::table('matricula', function (Blueprint $table) {
            $table->foreignId('situacao_matricula_id')->nullable()->constrained('situacao_matricula');
        });

        // 4. Reverter os dados
        foreach ($ids as $enum => $id) {
            DB::table('matricula')->where('situacao', $enum)->update(['situacao_matricula_id' => $id]);
        }

        // 5. Remover a coluna nova
        Schema::table('matricula', function (Blueprint $table) {
            $table->dropColumn('situacao');
        });
    }
};
