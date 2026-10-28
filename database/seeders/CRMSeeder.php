<?php

namespace Database\Seeders;

use App\Models\OrigemInteressado;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use Illuminate\Database\Seeder;

class CRMSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Origem
        OrigemInteressado::create(['nome' => 'Instagram']);
        OrigemInteressado::create(['nome' => 'Indicação']);
        OrigemInteressado::create(['nome' => 'Google']);
        OrigemInteressado::create(['nome' => 'Presencial']);

        // Status
        StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        StatusInteressado::create(['nome' => 'Contato Realizado', 'cor' => 'warning', 'ordem' => 2, 'is_final' => false, 'is_ganho' => false]);
        StatusInteressado::create(['nome' => 'Visita Agendada', 'cor' => 'primary', 'ordem' => 3, 'is_final' => false, 'is_ganho' => false]);
        StatusInteressado::create(['nome' => 'Em Negociação', 'cor' => 'primary', 'ordem' => 4, 'is_final' => false, 'is_ganho' => false]);
        StatusInteressado::create(['nome' => 'Matriculado', 'cor' => 'success', 'ordem' => 5, 'is_final' => true, 'is_ganho' => true]);
        StatusInteressado::create(['nome' => 'Desistente', 'cor' => 'danger', 'ordem' => 6, 'is_final' => true, 'is_ganho' => false]);

        // Tipo Contato
        TipoContatoInteressado::create(['nome' => 'Ligação']);
        TipoContatoInteressado::create(['nome' => 'WhatsApp']);
        TipoContatoInteressado::create(['nome' => 'E-mail']);
        TipoContatoInteressado::create(['nome' => 'Presencial']);
    }
}
