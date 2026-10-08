<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensagem_whatsapp_template', function (Blueprint $table) {
            $table->text('instrucoes_ia')->nullable()->after('conteudo');
        });
    }

    public function down(): void
    {
        Schema::table('mensagem_whatsapp_template', function (Blueprint $table) {
            $table->dropColumn('instrucoes_ia');
        });
    }
};
