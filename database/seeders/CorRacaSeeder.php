<?php

namespace Database\Seeders;

use App\Models\CorRaca;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CorRacaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $corRacas = [
            'Branca',
            'Preta',
            'Parda',
            'Amarela',
            'Indígena',
            'Não declarado'
        ];

        foreach ($corRacas as $nome) {
            CorRaca::firstOrCreate(['nome' => $nome]);
        }
    }
}
