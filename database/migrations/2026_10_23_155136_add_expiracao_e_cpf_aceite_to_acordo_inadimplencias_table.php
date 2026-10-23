<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('acordo_inadimplencias', function (Blueprint $table) {
            $table->dateTime('token_expira_em')->nullable()->after('token_publico');
            $table->string('cpf_confirmado_aceite', 14)->nullable()->after('ip_aceite');
        });

        // Para acordos existentes sem expiração, definir prazo razoável baseado na data de criação
        if (DB::getDriverName() === 'mysql') {
            DB::table('acordo_inadimplencias')
                ->whereNull('token_expira_em')
                ->update([
                    'token_expira_em' => DB::raw('DATE_ADD(created_at, INTERVAL 30 DAY)'),
                ]);
        } else {
            DB::table('acordo_inadimplencias')
                ->whereNull('token_expira_em')
                ->update([
                    'token_expira_em' => now()->addDays(30),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acordo_inadimplencias', function (Blueprint $table) {
            $table->dropColumn(['token_expira_em', 'cpf_confirmado_aceite']);
        });
    }
};
