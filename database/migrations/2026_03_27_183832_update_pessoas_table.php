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
            // SQL bruto para evitar inspeção de esquema (pragma_table_xinfo) no SQLite antigo
            try { DB::statement("ALTER TABLE pessoas ADD COLUMN foto VARCHAR"); } catch (\Exception $e) {}
            try { DB::statement("ALTER TABLE pessoas ADD COLUMN telefone VARCHAR"); } catch (\Exception $e) {}
            try { DB::statement("ALTER TABLE pessoas ADD COLUMN email VARCHAR"); } catch (\Exception $e) {}
            try { DB::statement("ALTER TABLE pessoas ADD COLUMN sexo_id INTEGER"); } catch (\Exception $e) {}
            try { DB::statement("ALTER TABLE pessoas ADD COLUMN cor_raca_id INTEGER"); } catch (\Exception $e) {}
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
        if (config('database.default') === 'sqlite') {
            // Rollback de colunas no SQLite antigo é limitado
        } else {
            Schema::table('pessoas', function (Blueprint $table) {
                $table->dropForeign(['sexo_id']);
                $table->dropForeign(['cor_raca_id']);
                $table->dropColumn(['foto', 'telefone', 'email', 'sexo_id', 'cor_raca_id']);
            });
        }
    }
};
