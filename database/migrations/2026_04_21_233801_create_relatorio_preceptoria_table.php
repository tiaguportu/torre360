<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relatorio_preceptoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preceptoria_id')->unique()->constrained('preceptoria')->cascadeOnDelete();
            $table->longText('corpo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relatorio_preceptoria');
    }
};
