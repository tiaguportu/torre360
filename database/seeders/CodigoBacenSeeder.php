<?php

namespace Database\Seeders;

use App\Models\CodigoBacen;
use Illuminate\Database\Seeder;

class CodigoBacenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bancos = [
            ['codigo' => '001', 'nome_extenso' => 'Banco do Brasil S.A.', 'nome_reduzido' => 'BANCO DO BRASIL', 'ispb' => '00000000'],
            ['codigo' => '033', 'nome_extenso' => 'Banco Santander (Brasil) S.A.', 'nome_reduzido' => 'SANTANDER', 'ispb' => '90400888'],
            ['codigo' => '104', 'nome_extenso' => 'Caixa Econômica Federal', 'nome_reduzido' => 'CAIXA ECONOMICA FEDERAL', 'ispb' => '00360305'],
            ['codigo' => '237', 'nome_extenso' => 'Banco Bradesco S.A.', 'nome_reduzido' => 'BRADESCO', 'ispb' => '60746948'],
            ['codigo' => '341', 'nome_extenso' => 'Itaú Unibanco S.A.', 'nome_reduzido' => 'ITAU UNIBANCO', 'ispb' => '60701190'],
            ['codigo' => '077', 'nome_extenso' => 'Banco Inter S.A.', 'nome_reduzido' => 'INTER', 'ispb' => '00416968'],
            ['codigo' => '260', 'nome_extenso' => 'Nu Pagamentos S.A.', 'nome_reduzido' => 'NUBANK', 'ispb' => '18236120'],
            ['codigo' => '748', 'nome_extenso' => 'Banco Cooperativo Sicredi S.A.', 'nome_reduzido' => 'SICREDI', 'ispb' => '01181521'],
            ['codigo' => '756', 'nome_extenso' => 'Banco Cooperativo do Brasil S.A.', 'nome_reduzido' => 'BANCOOB', 'ispb' => '02038232'],
            ['codigo' => '422', 'nome_extenso' => 'Banco Safra S.A.', 'nome_reduzido' => 'SAFRA', 'ispb' => '58160789'],
            ['codigo' => '212', 'nome_extenso' => 'Banco Original S.A.', 'nome_reduzido' => 'ORIGINAL', 'ispb' => '92894922'],
            ['codigo' => '655', 'nome_extenso' => 'Banco Votorantim S.A.', 'nome_reduzido' => 'BV', 'ispb' => '59588111'],
            ['codigo' => '041', 'nome_extenso' => 'Banco do Estado do Rio Grande do Sul S.A.', 'nome_reduzido' => 'BANRISUL', 'ispb' => '92702067'],
            ['codigo' => '004', 'nome_extenso' => 'Banco do Nordeste do Brasil S.A.', 'nome_reduzido' => 'BNB', 'ispb' => '07237373'],
        ];

        foreach ($bancos as $banco) {
            CodigoBacen::updateOrCreate(['codigo' => $banco['codigo']], $banco);
        }
    }
}
