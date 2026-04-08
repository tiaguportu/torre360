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
        Schema::create('item_faturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fatura_id')->constrained('faturas')->onDelete('cascade');
            $table->string('descricao');
            $table->decimal('valor_unitario', 15, 2);
            $table->decimal('quantidade', 15, 2)->default(1);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->string('tipo_desconto')->default('absoluto'); // absoluto (R$) ou relativo (%)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_faturas');
    }
};
