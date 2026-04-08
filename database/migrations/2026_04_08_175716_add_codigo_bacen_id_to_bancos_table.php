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
        Schema::table('bancos', function (Blueprint $table) {
            $table->dropColumn('codigo_bacen');
            $table->foreignId('codigo_bacen_id')->nullable()->constrained('codigo_bacens')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bancos', function (Blueprint $table) {
            $table->string('codigo_bacen')->nullable();
            $table->dropForeign(['codigo_bacen_id']);
            $table->dropColumn('codigo_bacen_id');
        });
    }
};
