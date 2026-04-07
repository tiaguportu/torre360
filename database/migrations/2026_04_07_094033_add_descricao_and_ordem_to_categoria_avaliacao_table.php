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
        Schema::table('categoria_avaliacao', function (Blueprint $table) {
            $table->string('descricao')->nullable()->after('nome');
            $table->integer('ordem')->default(0)->after('descricao');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categoria_avaliacao', function (Blueprint $table) {
            $table->dropColumn(['descricao', 'ordem']);
        });
    }
};
