<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avaliacao', function (Blueprint $table) {
            $table->timestamp('homologada_em')->nullable();
            $table->foreignId('homologada_por')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('avaliacao', function (Blueprint $table) {
            $table->dropConstrainedForeignId('homologada_por');
            $table->dropColumn('homologada_em');
        });
    }
};
