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
        Schema::dropIfExists('template_crachas_v2');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('template_crachas_v2', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('tipo_entidade')->default('pessoa');
            $table->integer('largura')->default(300);
            $table->integer('altura')->default(480);
            $table->longText('svg_content')->nullable();
            $table->timestamps();
        });
    }
};
