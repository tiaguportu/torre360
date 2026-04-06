<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') === 'sqlite') {
            try {
                DB::statement('ALTER TABLE curso ADD COLUMN minutos_por_periodo INTEGER');
            } catch (Exception $e) {
            }
        } else {
            if (! Schema::hasColumn('curso', 'minutos_por_periodo')) {
                Schema::table('curso', function (Blueprint $table) {
                    $table->unsignedSmallInteger('minutos_por_periodo')->nullable()
                        ->comment('Duração padrão de um período/aula em minutos. Ex: 50 = 50 minutos.');
                });
            }
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            // No action
        } else {
            Schema::table('curso', function (Blueprint $table) {
                $table->dropColumn('minutos_por_periodo');
            });
        }
    }
};
