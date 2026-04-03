<?php

namespace Database\Seeders;

use App\Models\Curso;
use App\Models\DocumentoObrigatorio;
use App\Models\SituacaoDocumentoInserido;
use Illuminate\Database\Seeder;

class DocumentosSeeder extends Seeder
{
    public function run(): void
    {
        $situacoes = [
            ['nome' => 'Pendente', 'cor' => '#6b7280'],
            ['nome' => 'Em Análise', 'cor' => '#3b82f6'],
            ['nome' => 'Aprovado', 'cor' => '#10b981'],
            ['nome' => 'Rejeitado', 'cor' => '#ef4444'],
        ];

        foreach ($situacoes as $situacao) {
            SituacaoDocumentoInserido::firstOrCreate(['nome' => $situacao['nome']]);
        }

        $curso = Curso::where('nome_interno', 'Ensino Médio')->first();

        if ($curso) {
            $docs = [
                'RG do Aluno',
                'CPF do Aluno',
                'Histórico Escolar',
                'Comprovante de Residência',
                'Foto 3x4',
            ];

            foreach ($docs as $doc) {
                DocumentoObrigatorio::firstOrCreate([
                    'nome' => $doc,
                    'curso_id' => $curso->id,
                ]);
            }
        }
    }
}
