<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sala', 'exige_aprovacao_reserva')) {
            return;
        }

        Schema::table('sala', function (Blueprint $table): void {
            $table->boolean('exige_aprovacao_reserva')->default(false)->after('ativa');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sala', 'exige_aprovacao_reserva')) {
            return;
        }

        Schema::table('sala', function (Blueprint $table): void {
            $table->dropColumn('exige_aprovacao_reserva');
        });
    }
};
