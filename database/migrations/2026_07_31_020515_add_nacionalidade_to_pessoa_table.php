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
            if (! Schema::hasColumn('pessoa', 'nacionalidade')) {
                $table->string('nacionalidade')->default('1')->nullable()->after('nacionalidade_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pessoa', function (Blueprint $table) {
            if (Schema::hasColumn('pessoa', 'nacionalidade')) {
                $table->dropColumn('nacionalidade');
            }
        });
    }
};
