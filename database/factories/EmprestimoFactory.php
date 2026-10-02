<?php

namespace Database\Factories;

use App\Models\Emprestimo;
use App\Models\Livro;
use App\Models\Matricula;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmprestimoFactory extends Factory
{
    protected $model = Emprestimo::class;

    public function definition(): array
    {
        return [
            'livro_id' => Livro::factory(),
            'matricula_id' => Matricula::factory(),
            'data_emprestimo' => now()->subDays(5),
            'data_prevista_devolucao' => now()->addDays(9),
            'status' => 'emprestado',
        ];
    }
}
