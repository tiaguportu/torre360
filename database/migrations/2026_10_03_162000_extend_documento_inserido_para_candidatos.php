<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('interessado', 'token_documentos')) {
            Schema::table('interessado', function (Blueprint $table) {
                $table->string('token_documentos', 64)->nullable()->unique()->after('status_interessado_id');
            });
        }

        Schema::table('documento_inserido', function (Blueprint $table) {
            $table->foreignId('matricula_id')->nullable()->change();
            $table->foreignId('interessado_id')->nullable()->after('matricula_id')->constrained('interessado')->cascadeOnDelete();
            $table->foreignId('interessado_dependente_id')->nullable()->after('interessado_id')->constrained('interessado_dependente')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documento_inserido', function (Blueprint $table) {
            $table->dropForeign(['interessado_id']);
            $table->dropForeign(['interessado_dependente_id']);
            $table->dropColumn(['interessado_id', 'interessado_dependente_id']);
            $table->foreignId('matricula_id')->nullable(false)->change();
        });

        if (Schema::hasColumn('interessado', 'token_documentos')) {
            Schema::table('interessado', function (Blueprint $table) {
                $table->dropColumn('token_documentos');
            });
        }
    }
};
