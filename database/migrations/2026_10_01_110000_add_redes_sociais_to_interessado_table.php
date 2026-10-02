<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interessado', function (Blueprint $table) {
            $table->json('redes_sociais')->nullable()->after('observacoes');
        });
    }

    public function down(): void
    {
        Schema::table('interessado', function (Blueprint $table) {
            $table->dropColumn('redes_sociais');
        });
    }
};
