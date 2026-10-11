<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documento_inserido', function (Blueprint $table) {
            $table->string('status_sugerido', 30)->nullable()->after('dados_ia')->index();
        });
    }

    public function down(): void
    {
        Schema::table('documento_inserido', function (Blueprint $table) {
            $table->dropColumn('status_sugerido');
        });
    }
};
