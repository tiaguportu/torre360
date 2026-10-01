<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interessado', function (Blueprint $table) {
            $table->string('token_convite')->nullable()->unique()->after('lead_score');
            $table->dateTime('token_convite_expira_em')->nullable()->after('token_convite');
            $table->dateTime('token_convite_usado_em')->nullable()->after('token_convite_expira_em');
        });
    }

    public function down(): void
    {
        Schema::table('interessado', function (Blueprint $table) {
            $table->dropColumn(['token_convite', 'token_convite_expira_em', 'token_convite_usado_em']);
        });
    }
};
