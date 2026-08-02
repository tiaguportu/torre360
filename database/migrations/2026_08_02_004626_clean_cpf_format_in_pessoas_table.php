<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('pessoa')
            ->whereNotNull('cpf')
            ->where('cpf', '!=', '')
            ->get(['id', 'cpf'])
            ->each(function ($p) {
                $clean = preg_replace('/\D/', '', $p->cpf);
                if ($clean !== $p->cpf) {
                    DB::table('pessoa')->where('id', $p->id)->update(['cpf' => $clean ?: null]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
