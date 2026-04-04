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
        if (config('database.default') === 'sqlite') {
            Schema::table('pessoas', function (Blueprint $table) {
                $table->string('foto')->nullable();
                $table->string('telefone')->nullable();
                $table->string('email')->nullable();
                $table->unsignedBigInteger('sexo_id')->nullable();
                $table->unsignedBigInteger('cor_raca_id')->nullable();
            });
        } else {
            Schema::table('pessoas', function (Blueprint $table) {
                $table->string('foto')->nullable();
                $table->string('telefone')->nullable();
                $table->string('email')->nullable();
                $table->foreignId('sexo_id')->nullable()->constrained('sexos')->nullOnDelete();
                $table->foreignId('cor_raca_id')->nullable()->constrained('cor_racas')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pessoas', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['sexo_id']);
                $table->dropForeign(['cor_raca_id']);
            }
            $table->dropColumn(['foto', 'telefone', 'email', 'sexo_id', 'cor_raca_id']);
        });
    }
};
