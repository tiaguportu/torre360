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
        Schema::table('documento_inserido', function (Blueprint $table) {
            $table->renameColumn('situacao', 'status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documento_inserido', function (Blueprint $table) {
            $table->renameColumn('status', 'situacao');
        });
    }
};
