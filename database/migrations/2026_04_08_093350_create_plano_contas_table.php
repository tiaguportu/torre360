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
        Schema::create('plano_contas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // Ex: 1.1, 1.1.1
            $table->string('nome');
            $table->enum('tipo', ['receita', 'despesa', 'ativo', 'passivo']);
            $table->foreignId('pai_id')->nullable()->constrained('plano_contas')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plano_contas');
    }
};
