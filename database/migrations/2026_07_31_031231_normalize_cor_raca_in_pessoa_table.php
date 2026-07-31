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
        DB::table('pessoa')->whereIn('cor_raca', ['nao_declarada', 'nao_declarado'])->update(['cor_raca' => '0']);
        DB::table('pessoa')->where('cor_raca', 'branca')->update(['cor_raca' => '1']);
        DB::table('pessoa')->where('cor_raca', 'preta')->update(['cor_raca' => '2']);
        DB::table('pessoa')->where('cor_raca', 'parda')->update(['cor_raca' => '3']);
        DB::table('pessoa')->where('cor_raca', 'amarela')->update(['cor_raca' => '4']);
        DB::table('pessoa')->whereIn('cor_raca', ['indigena', 'indígena'])->update(['cor_raca' => '5']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
