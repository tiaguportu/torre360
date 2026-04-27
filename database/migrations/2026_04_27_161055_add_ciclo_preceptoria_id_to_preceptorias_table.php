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
        Schema::table('preceptoria', function (Blueprint $table) {
            $table->foreignId('ciclo_preceptoria_id')->after('id')->nullable()->constrained('ciclo_preceptorias')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('preceptoria', function (Blueprint $table) {
            $table->dropForeign(['ciclo_preceptoria_id']);
            $table->dropColumn('ciclo_preceptoria_id');
        });
    }
};
