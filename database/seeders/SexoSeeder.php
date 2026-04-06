<?php

namespace Database\Seeders;

use App\Models\Sexo;
use Illuminate\Database\Seeder;

class SexoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sexos = [
            'Feminino',
            'Masculino',
            'Não declarado',
        ];

        foreach ($sexos as $nome) {
            Sexo::firstOrCreate(['nome' => $nome]);
        }
    }
}
