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
        Schema::table('matricula', function (Blueprint $table) {
            $table->date('data_ativacao')->nullable()->after('situacao');
            $table->date('data_desativacao')->nullable()->after('data_ativacao');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matricula', function (Blueprint $table) {
            $table->dropColumn(['data_ativacao', 'data_desativacao']);
        });
    }
};
