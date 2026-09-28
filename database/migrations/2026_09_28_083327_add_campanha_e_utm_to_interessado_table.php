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
        Schema::table('interessado', function (Blueprint $table) {
            $table->foreignId('campanha_marketing_id')->nullable()->after('origem_interessado_id')->constrained('campanha_marketing')->nullOnDelete();
            $table->string('utm_source')->nullable()->after('campanha_marketing_id');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interessado', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campanha_marketing_id');
            $table->dropColumn(['utm_source', 'utm_medium', 'utm_campaign']);
        });
    }
};
