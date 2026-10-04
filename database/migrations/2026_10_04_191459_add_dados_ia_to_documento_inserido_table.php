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
        Schema::table('documento_inserido', function (Blueprint $table) {
            $table->json('dados_ia')->nullable()->after('hash_arquivo');
            $table->timestamp('analisado_ia_em')->nullable()->after('dados_ia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documento_inserido', function (Blueprint $table) {
            $table->dropColumn(['dados_ia', 'analisado_ia_em']);
        });
    }
};
