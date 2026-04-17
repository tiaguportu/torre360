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
            $table->string('estado_civil')->nullable()->after('email');
            $table->string('profissao')->nullable()->after('estado_civil');
            $table->string('identidade')->nullable()->after('profissao');
        });

        Schema::table('endereco', function (Blueprint $table) {
            $table->enum('tipo', ['residencial', 'comercial'])->default('residencial')->after('cidade_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pessoa', function (Blueprint $table) {
            $table->dropColumn(['estado_civil', 'profissao', 'identidade']);
        });

        Schema::table('endereco', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
