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
        Schema::create('tributacao_cursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->unique()->constrained('cursos')->onDelete('cascade');
            $table->string('cnae')->nullable();
            $table->decimal('iss', 5, 2)->default(0);
            $table->decimal('pis', 5, 2)->default(0);
            $table->decimal('cofins', 5, 2)->default(0);
            $table->string('item_servico')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tributacao_cursos');
    }
};
