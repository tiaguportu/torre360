<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GeografiaSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Países (Amostra abrangente, pode ser expandida conforme necessário)
        $paises = [
            ['nome' => 'Brasil', 'sigla' => 'br'],
            ['nome' => 'Portugal', 'sigla' => 'pt'],
            ['nome' => 'Angola', 'sigla' => 'ao'],
            ['nome' => 'Cabo Verde', 'sigla' => 'cv'],
            ['nome' => 'Guiné-Bissau', 'sigla' => 'gw'],
            ['nome' => 'Moçambique', 'sigla' => 'mz'],
            ['nome' => 'São Tomé e Príncipe', 'sigla' => 'st'],
            ['nome' => 'Timor-Leste', 'sigla' => 'tl'],
            ['nome' => 'Estados Unidos', 'sigla' => 'us'],
            ['nome' => 'Espanha', 'sigla' => 'es'],
            ['nome' => 'França', 'sigla' => 'fr'],
            ['nome' => 'Itália', 'sigla' => 'it'],
            ['nome' => 'Alemanha', 'sigla' => 'de'],
        ];

        foreach ($paises as $pais) {
            DB::table('paises')->updateOrInsert(['sigla' => $pais['sigla']], $pais);
        }

        $brasilId = DB::table('paises')->where('sigla', 'BRA')->first()->id;

        // 2. Estados do Brasil
        $estados = [
            ['pais_id' => $brasilId, 'nome' => 'Acre', 'sigla' => 'AC'],
            ['pais_id' => $brasilId, 'nome' => 'Alagoas', 'sigla' => 'AL'],
            ['pais_id' => $brasilId, 'nome' => 'Amapá', 'sigla' => 'AP'],
            ['pais_id' => $brasilId, 'nome' => 'Amazonas', 'sigla' => 'AM'],
            ['pais_id' => $brasilId, 'nome' => 'Bahia', 'sigla' => 'BA'],
            ['pais_id' => $brasilId, 'nome' => 'Ceará', 'sigla' => 'CE'],
            ['pais_id' => $brasilId, 'nome' => 'Distrito Federal', 'sigla' => 'DF'],
            ['pais_id' => $brasilId, 'nome' => 'Espírito Santo', 'sigla' => 'ES'],
            ['pais_id' => $brasilId, 'nome' => 'Goiás', 'sigla' => 'GO'],
            ['pais_id' => $brasilId, 'nome' => 'Maranhão', 'sigla' => 'MA'],
            ['pais_id' => $brasilId, 'nome' => 'Mato Grosso', 'sigla' => 'MT'],
            ['pais_id' => $brasilId, 'nome' => 'Mato Grosso do Sul', 'sigla' => 'MS'],
            ['pais_id' => $brasilId, 'nome' => 'Minas Gerais', 'sigla' => 'MG'],
            ['pais_id' => $brasilId, 'nome' => 'Pará', 'sigla' => 'PA'],
            ['pais_id' => $brasilId, 'nome' => 'Paraíba', 'sigla' => 'PB'],
            ['pais_id' => $brasilId, 'nome' => 'Paraná', 'sigla' => 'PR'],
            ['pais_id' => $brasilId, 'nome' => 'Pernambuco', 'sigla' => 'PE'],
            ['pais_id' => $brasilId, 'nome' => 'Piauí', 'sigla' => 'PI'],
            ['pais_id' => $brasilId, 'nome' => 'Rio de Janeiro', 'sigla' => 'RJ'],
            ['pais_id' => $brasilId, 'nome' => 'Rio Grande do Norte', 'sigla' => 'RN'],
            ['pais_id' => $brasilId, 'nome' => 'Rio Grande do Sul', 'sigla' => 'RS'],
            ['pais_id' => $brasilId, 'nome' => 'Rondônia', 'sigla' => 'RO'],
            ['pais_id' => $brasilId, 'nome' => 'Roraima', 'sigla' => 'RR'],
            ['pais_id' => $brasilId, 'nome' => 'Santa Catarina', 'sigla' => 'SC'],
            ['pais_id' => $brasilId, 'nome' => 'São Paulo', 'sigla' => 'SP'],
            ['pais_id' => $brasilId, 'nome' => 'Sergipe', 'sigla' => 'SE'],
            ['pais_id' => $brasilId, 'nome' => 'Tocantins', 'sigla' => 'TO'],
        ];

        foreach ($estados as $estado) {
            DB::table('estados')->updateOrInsert(['sigla' => $estado['sigla'], 'pais_id' => $brasilId], $estado);
        }

        // 3. Cidades (População via JSON IBGE)
        $jsonPath = base_path('database/data/municipios.json');
        if (file_exists($jsonPath)) {
            $jsonContent = file_get_contents($jsonPath);
            
            // Remover UTF-8 BOM se presente
            if (substr($jsonContent, 0, 3) === "\xef\xbb\xbf") {
                $jsonContent = substr($jsonContent, 3);
            }

            $municipios = json_decode($jsonContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                logger()->error('Falha ao decodificar JSON de cidades: ' . json_last_error_msg());
                return;
            }

            if (!is_array($municipios)) {
                logger()->error('JSON de cidades não é um array válido.');
                return;
            }

            // Mapeamento de Códigos IBGE UF para Siglas
            $ufMap = [
                11 => 'RO', 12 => 'AC', 13 => 'AM', 14 => 'RR', 15 => 'PA', 16 => 'AP', 17 => 'TO',
                21 => 'MA', 22 => 'PI', 23 => 'CE', 24 => 'RN', 25 => 'PB', 26 => 'PE', 27 => 'AL',
                28 => 'SE', 29 => 'BA', 31 => 'MG', 32 => 'ES', 33 => 'RJ', 35 => 'SP', 41 => 'PR',
                42 => 'SC', 43 => 'RS', 50 => 'MS', 51 => 'MT', 52 => 'GO', 53 => 'DF'
            ];

            // Buscar IDs dos estados para evitar múltiplas queries
            $estadoIds = DB::table('estados')->pluck('id', 'sigla')->toArray();

            $cidadesParaInserir = [];
            foreach ($municipios as $m) {
                $sigla = $ufMap[$m['codigo_uf']] ?? null;
                if ($sigla && isset($estadoIds[$sigla])) {
                    $cidadesParaInserir[] = [
                        'estado_id' => $estadoIds[$sigla],
                        'nome' => $m['nome'],
                        'codigo_ibge' => $m['codigo_ibge'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Inserir em lotes de 500 para evitar limites de memória/SQL
                if (count($cidadesParaInserir) >= 500) {
                    DB::table('cidades')->insert($cidadesParaInserir);
                    $cidadesParaInserir = [];
                }
            }

            if (!empty($cidadesParaInserir)) {
                DB::table('cidades')->insert($cidadesParaInserir);
            }
        }
    }
}
