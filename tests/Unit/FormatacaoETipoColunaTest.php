<?php

namespace Tests\Unit;

use App\Enums\TipoColunaRelatorio;
use App\Support\Formatacao;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormatacaoETipoColunaTest extends TestCase
{
    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function cpfs(): array
    {
        return [
            'so digitos' => ['12345678901', '123.456.789-01'],
            'ja formatado' => ['123.456.789-01', '123.456.789-01'],
            'com espacos' => [' 123 456 789 01 ', '123.456.789-01'],
            'curto volta como veio' => ['1234567', '1234567'],
            'longo volta como veio' => ['123456789012', '123456789012'],
            'vazio vira nulo' => ['', null],
            'nulo' => [null, null],
        ];
    }

    #[DataProvider('cpfs')]
    public function test_cpf(?string $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, Formatacao::cpf($entrada));
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function cnpjs(): array
    {
        return [
            'so digitos' => ['12345678000195', '12.345.678/0001-95'],
            'ja formatado' => ['12.345.678/0001-95', '12.345.678/0001-95'],
            'cpf nao e cnpj' => ['12345678901', '12345678901'],
            'vazio vira nulo' => ['', null],
            'nulo' => [null, null],
        ];
    }

    #[DataProvider('cnpjs')]
    public function test_cnpj(?string $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, Formatacao::cnpj($entrada));
    }

    public function test_reais_usa_virgula_decimal_e_ponto_de_milhar(): void
    {
        $this->assertSame('R$ 0,00', Formatacao::reais(0));
        $this->assertSame('R$ 99,90', Formatacao::reais(99.9));
        $this->assertSame('R$ 1.234,56', Formatacao::reais(1234.56));
        $this->assertSame('R$ 1.000.000,00', Formatacao::reais(1000000));
        $this->assertSame('R$ 0,13', Formatacao::reais(0.125), 'arredonda para o centavo');
    }

    public function test_so_numeros_alinham_a_direita_no_relatorio(): void
    {
        $this->assertTrue(TipoColunaRelatorio::Inteiro->alinhaADireita());
        $this->assertTrue(TipoColunaRelatorio::Decimal->alinhaADireita());
        $this->assertTrue(TipoColunaRelatorio::Moeda->alinhaADireita());
        $this->assertFalse(TipoColunaRelatorio::Texto->alinhaADireita());
        $this->assertFalse(TipoColunaRelatorio::Data->alinhaADireita());
    }
}
