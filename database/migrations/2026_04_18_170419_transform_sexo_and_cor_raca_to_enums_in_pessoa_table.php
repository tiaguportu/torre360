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

        // Migrar dados de Sexo
        DB::table('pessoa')->get()->each(function ($p) {
            $sexo = match ($p->sexo_id) {
                1 => 'feminino',
                2 => 'masculino',
                3 => 'nao_declarado',
                default => null,
            };

            $cor_raca = match ($p->cor_raca_id) {
                1 => 'branca',
                2 => 'preta',
                3 => 'parda',
                4 => 'amarela',
                5 => 'indigena',
                6 => 'nao_declarado',
                default => null,
            };

            DB::table('pessoa')->where('id', $p->id)->update([
                'sexo' => $sexo,
                'cor_raca' => $cor_raca,
            ]);
        });

        Schema::table('pessoa', function (Blueprint $table) {
            $table->dropForeign(['sexo_id']);
            $table->dropColumn('sexo_id');
            $table->dropForeign(['cor_raca_id']);
            $table->dropColumn('cor_raca_id');

            // Remove a coluna raca_cor que parecia redundante no dump
            if (Schema::hasColumn('pessoa', 'raca_cor')) {
                $table->dropColumn('raca_cor');
            }
        });

        // Opcional: Remover as tabelas que agora são enums
        Schema::dropIfExists('sexo');
        Schema::dropIfExists('cor_raca');
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
