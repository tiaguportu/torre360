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
        Schema::table('pais', function (Blueprint $table) {
            if (! Schema::hasColumn('pais', 'codigo')) {
                $table->string('codigo')->nullable()->after('sigla');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pais', function (Blueprint $table) {
            if (Schema::hasColumn('pais', 'codigo')) {
                $table->dropColumn('codigo');
            }
        });
    }
};
