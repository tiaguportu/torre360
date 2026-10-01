<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nota', function (Blueprint $table) {
            $table->string('situacao', 20)->nullable()->after('valor');
        });
    }

    public function down(): void
    {
        Schema::table('nota', function (Blueprint $table) {
            $table->dropColumn('situacao');
        });
    }
};
