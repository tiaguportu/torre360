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
            // Ignoramos .change() no SQLite antigo (HostGator).
        } else {
            Schema::table('frequencia_escolar', function (Blueprint $table) {
                $table->string('situacao')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            // No action
        } else {
            Schema::table('frequencia_escolar', function (Blueprint $table) {
                $table->string('situacao')->nullable(false)->change();
            });
        }
    }
};
