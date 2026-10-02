<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interessado', function (Blueprint $table) {
            $table->json('dados_pre_matricula')->nullable()->after('token_convite_usado_em');
        });
    }

    public function down(): void
    {
        Schema::table('interessado', function (Blueprint $table) {
            $table->dropColumn('dados_pre_matricula');
        });
    }
};
