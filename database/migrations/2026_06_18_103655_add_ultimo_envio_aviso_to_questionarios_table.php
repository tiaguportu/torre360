<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionarios', function (Blueprint $table) {
            $table->timestamp('ultimo_envio_aviso')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questionarios', function (Blueprint $table) {
            $table->dropColumn('ultimo_envio_aviso');
        });
    }
};
