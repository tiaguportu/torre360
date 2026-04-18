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
        Schema::table('unidade', function (Blueprint $table) {
            $table->string('celular_whatsapp')->nullable()->after('cnpj');
            $table->string('instagram')->nullable()->after('celular_whatsapp');
            $table->string('facebook')->nullable()->after('instagram');
            $table->string('youtube')->nullable()->after('facebook');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unidade', function (Blueprint $table) {
            //
        });
    }
};
