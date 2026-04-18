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
        Schema::table('pessoa', function (Blueprint $table) {
            if (! Schema::hasColumn('pessoa', 'sexo')) {
                $table->string('sexo')->nullable()->after('sexo_id');
            }
            if (! Schema::hasColumn('pessoa', 'cor_raca')) {
                $table->string('cor_raca')->nullable()->after('cor_raca_id');
            }
        });

        // Migrar dados de Sexo e Cor/Raça usando SQL direto para performance
        DB::statement("
            UPDATE pessoa SET 
                sexo = CASE 
                    WHEN sexo_id = 1 THEN 'feminino'
                    WHEN sexo_id = 2 THEN 'masculino'
                    WHEN sexo_id = 3 THEN 'nao_declarado'
                    ELSE NULL 
                END,
                cor_raca = CASE 
                    WHEN cor_raca_id = 1 THEN 'branca'
                    WHEN cor_raca_id = 2 THEN 'preta'
                    WHEN cor_raca_id = 3 THEN 'parda'
                    WHEN cor_raca_id = 4 THEN 'amarela'
                    WHEN cor_raca_id = 5 THEN 'indigena'
                    WHEN cor_raca_id = 6 THEN 'nao_declarado'
                    ELSE NULL 
                END
        ");

        // Remover chaves estrangeiras de forma segura
        try {
            Schema::table('pessoa', function (Blueprint $table) {
                $table->dropForeign(['sexo_id']);
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('pessoa', function (Blueprint $table) {
                $table->dropColumn('sexo_id');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('pessoa', function (Blueprint $table) {
                $table->dropForeign(['cor_raca_id']);
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('pessoa', function (Blueprint $table) {
                $table->dropColumn('cor_raca_id');
            });
        } catch (\Exception $e) {}

        // Remove a coluna raca_cor que parecia redundante no dump
        if (Schema::hasColumn('pessoa', 'raca_cor')) {
            Schema::table('pessoa', function (Blueprint $table) {
                $table->dropColumn('raca_cor');
            });
        }

        // Opcional: Remover as tabelas que agora são enums de forma segura
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('sexo');
        Schema::dropIfExists('cor_raca');
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('sexo', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
        });

        Schema::create('cor_raca', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
        });

        // Popular dados básicos
        DB::table('sexo')->insert([
            ['id' => 1, 'nome' => 'Feminino'],
            ['id' => 2, 'nome' => 'Masculino'],
            ['id' => 3, 'nome' => 'Não declarado'],
        ]);

        DB::table('cor_raca')->insert([
            ['id' => 1, 'nome' => 'Branca'],
            ['id' => 2, 'nome' => 'Preta'],
            ['id' => 3, 'nome' => 'Parda'],
            ['id' => 4, 'nome' => 'Amarela'],
            ['id' => 5, 'nome' => 'Indígena'],
            ['id' => 6, 'nome' => 'Não declarado'],
        ]);

        Schema::table('pessoa', function (Blueprint $table) {
            $table->unsignedBigInteger('sexo_id')->nullable()->after('id');
            $table->unsignedBigInteger('cor_raca_id')->nullable()->after('sexo_id');
            $table->foreign('sexo_id')->references('id')->on('sexo');
            $table->foreign('cor_raca_id')->references('id')->on('cor_raca');
        });

        DB::table('pessoa')->get()->each(function ($p) {
            $sexo_id = match ($p->sexo) {
                'feminino' => 1,
                'masculino' => 2,
                'nao_declarado' => 3,
                default => null,
            };

            $cor_raca_id = match ($p->cor_raca) {
                'branca' => 1,
                'preta' => 2,
                'parda' => 3,
                'amarela' => 4,
                'indigena' => 5,
                'nao_declarado' => 6,
                default => null,
            };

            DB::table('pessoa')->where('id', $p->id)->update([
                'sexo_id' => $sexo_id,
                'cor_raca_id' => $cor_raca_id,
            ]);
        });

        Schema::table('pessoa', function (Blueprint $table) {
            $table->dropColumn('sexo');
            $table->dropColumn('cor_raca');
        });
    }
};
