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
            if (Schema::hasColumn('pessoa', 'nacionalidade')) {
                $table->renameColumn('nacionalidade', 'tipo_nacionalidade');
            } elseif (! Schema::hasColumn('pessoa', 'tipo_nacionalidade')) {
                $table->string('tipo_nacionalidade')->default('1')->nullable()->after('nacionalidade_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pessoa', function (Blueprint $table) {
            if (Schema::hasColumn('pessoa', 'tipo_nacionalidade')) {
                $table->renameColumn('tipo_nacionalidade', 'nacionalidade');
            }
        });
    }
};
