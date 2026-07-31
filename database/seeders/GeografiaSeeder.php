<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GeografiaSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Países (com códigos INEP para o Educacenso)
        $paises = [
            ['nome' => 'Brasil', 'sigla' => 'br', 'codigo' => '76'],
            ['nome' => 'Portugal', 'sigla' => 'pt', 'codigo' => '620'],
            ['nome' => 'Angola', 'sigla' => 'ao', 'codigo' => '24'],
            ['nome' => 'Cabo Verde', 'sigla' => 'cv', 'codigo' => '132'],
            ['nome' => 'Guiné-Bissau', 'sigla' => 'gw', 'codigo' => '624'],
            ['nome' => 'Moçambique', 'sigla' => 'mz', 'codigo' => '508'],
            ['nome' => 'São Tomé e Príncipe', 'sigla' => 'st', 'codigo' => '678'],
            ['nome' => 'Timor-Leste', 'sigla' => 'tl', 'codigo' => '626'],
            ['nome' => 'Estados Unidos', 'sigla' => 'us', 'codigo' => '840'],
            ['nome' => 'Espanha', 'sigla' => 'es', 'codigo' => '724'],
            ['nome' => 'França', 'sigla' => 'fr', 'codigo' => '250'],
            ['nome' => 'Itália', 'sigla' => 'it', 'codigo' => '380'],
            ['nome' => 'Alemanha', 'sigla' => 'de', 'codigo' => '276'],
            ['nome' => 'Argentina', 'sigla' => 'ar', 'codigo' => '32'],
            ['nome' => 'Uruguai', 'sigla' => 'uy', 'codigo' => '858'],
            ['nome' => 'Paraguai', 'sigla' => 'py', 'codigo' => '600'],
            ['nome' => 'Chile', 'sigla' => 'cl', 'codigo' => '152'],
            ['nome' => 'Colômbia', 'sigla' => 'co', 'codigo' => '170'],
            ['nome' => 'Peru', 'sigla' => 'pe', 'codigo' => '604'],
            ['nome' => 'Venezuela', 'sigla' => 've', 'codigo' => '862'],
            ['nome' => 'Bolívia', 'sigla' => 'bo', 'codigo' => '68'],
            ['nome' => 'Equador', 'sigla' => 'ec', 'codigo' => '218'],
            ['nome' => 'Japão', 'sigla' => 'jp', 'codigo' => '392'],
            ['nome' => 'China', 'sigla' => 'cn', 'codigo' => '156'],
            ['nome' => 'Reino Unido', 'sigla' => 'gb', 'codigo' => '826'],
        ];

        foreach ($paises as $pais) {
            DB::table('pais')->updateOrInsert(['sigla' => $pais['sigla']], $pais);
        }

        $brasilId = DB::table('pais')->where('sigla', 'br')->first()->id;

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
            DB::table('estado')->updateOrInsert(['sigla' => $estado['sigla'], 'pais_id' => $brasilId], $estado);
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
                logger()->error('Falha ao decodificar JSON de cidades: '.json_last_error_msg());

                return;
            }

            if (! is_array($municipios)) {
                logger()->error('JSON de cidades não é um array válido.');

                return;
            }

            // Mapeamento de Códigos IBGE UF para Siglas
            $ufMap = [
                11 => 'RO', 12 => 'AC', 13 => 'AM', 14 => 'RR', 15 => 'PA', 16 => 'AP', 17 => 'TO',
                21 => 'MA', 22 => 'PI', 23 => 'CE', 24 => 'RN', 25 => 'PB', 26 => 'PE', 27 => 'AL',
                28 => 'SE', 29 => 'BA', 31 => 'MG', 32 => 'ES', 33 => 'RJ', 35 => 'SP', 41 => 'PR',
                42 => 'SC', 43 => 'RS', 50 => 'MS', 51 => 'MT', 52 => 'GO', 53 => 'DF',
            ];

            // Buscar IDs dos estados para evitar múltiplas queries
            $estadoIds = DB::table('estado')->pluck('id', 'sigla')->toArray();

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

                // Inserir/Atualizar em lotes de 100 para evitar limites de memória/SQL
                if (count($cidadesParaInserir) >= 100) {
                    foreach ($cidadesParaInserir as $cidade) {
                        DB::table('cidade')->updateOrInsert(
                            ['codigo_ibge' => $cidade['codigo_ibge']],
                            $cidade
                        );
                    }
                    $cidadesParaInserir = [];
                }
            }

            if (! empty($cidadesParaInserir)) {
                foreach ($cidadesParaInserir as $cidade) {
                    DB::table('cidade')->updateOrInsert(
                        ['codigo_ibge' => $cidade['codigo_ibge']],
                        $cidade
                    );
                }
            }
        }
    }
}
