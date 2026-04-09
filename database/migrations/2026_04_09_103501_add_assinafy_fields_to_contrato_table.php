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
        Schema::table('contrato', function (Blueprint $table) {
            $table->string('assinafy_id')->nullable()->after('id');
            $table->string('assinafy_status')->default('pendente')->after('assinafy_id');
            $table->string('assinafy_pdf_url')->nullable()->after('assinafy_status');
            $table->json('assinafy_request_log')->nullable()->after('assinafy_pdf_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contrato', function (Blueprint $table) {
            $table->dropColumn(['assinafy_id', 'assinafy_status', 'assinafy_pdf_url', 'assinafy_request_log']);
        });
    }
};
