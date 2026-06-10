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
        if (Schema::hasTable('enderecos')) {
            Schema::table('enderecos', function (Blueprint $table) {
                $table->string('complemento')->nullable()->after('numero');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('enderecos')) {
            Schema::table('enderecos', function (Blueprint $table) {
                $table->dropColumn('complemento');
            });
        }
    }
};
