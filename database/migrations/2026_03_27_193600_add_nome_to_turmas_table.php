<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') === 'sqlite') {
            try { DB::statement("ALTER TABLE turmas ADD COLUMN nome VARCHAR"); } catch (\Exception $e) {}
        } else {
            Schema::table('turmas', function (Blueprint $table) {
                $table->string('nome')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            // No action
        } else {
            Schema::table('turmas', function (Blueprint $table) {
                $table->dropColumn('nome');
            });
        }
    }
};
