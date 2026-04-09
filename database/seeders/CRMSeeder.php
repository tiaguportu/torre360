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
        StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1]);
        StatusInteressado::create(['nome' => 'Contato Realizado', 'cor' => 'warning', 'ordem' => 2]);
        StatusInteressado::create(['nome' => 'Visita Agendada', 'cor' => 'primary', 'ordem' => 3]);
        StatusInteressado::create(['nome' => 'Em Negociação', 'cor' => 'primary', 'ordem' => 4]);
        StatusInteressado::create(['nome' => 'Matriculado', 'cor' => 'success', 'ordem' => 5]);
        StatusInteressado::create(['nome' => 'Desistente', 'cor' => 'danger', 'ordem' => 6]);

        // Tipo Contato
        TipoContatoInteressado::create(['nome' => 'Ligação']);
        TipoContatoInteressado::create(['nome' => 'WhatsApp']);
        TipoContatoInteressado::create(['nome' => 'E-mail']);
        TipoContatoInteressado::create(['nome' => 'Presencial']);
    }
}
