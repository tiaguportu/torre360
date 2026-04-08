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
        Schema::table('faturas', function (Blueprint $table) {
            $table->dropColumn(['valor', 'valor_pago']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('faturas', function (Blueprint $table) {
            $table->decimal('valor', 12, 2)->default(0);
            $table->decimal('valor_pago', 12, 2)->nullable();
        });
    }
};
