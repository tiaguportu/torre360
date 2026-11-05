<?php

namespace Tests\Unit;

use App\Enums\StatusFatura;
use App\Support\FaturaImportParser;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Leitura tolerante da planilha de importação de faturas: um valor mal lido vira cobrança errada, então o que é
 * ambíguo ou inválido volta como null (e a linha é recusada), e faturas pagas não entram por importação.
 */
class FaturaImportParserTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: float|null}>
     */
    public static function valores(): array
    {
        return [
            'ponto decimal' => ['1234.56', 1234.56],
            'virgula decimal' => ['1234,56', 1234.56],
            'formato brasileiro' => ['1.234,56', 1234.56],
            'formato internacional' => ['1,234.56', 1234.56],
            'com R$' => ['R$ 1.234,56', 1234.56],
            'R$ minusculo e colado' => ['r$99,90', 99.9],
            'espaco inquebravel' => ["R$\u{00A0}250,00", 250.0],
            'milhares so com pontos' => ['1.234.567', 1234567.0],
            'milhares so com virgulas' => ['1,234,567', 1234567.0],
            'inteiro como texto' => ['100', 100.0],
            'zero' => ['0', 0.0],
            'inteiro nativo' => [150, 150.0],
            'float nativo' => [89.9, 89.9],
            'espacos em volta' => ['  45,50  ', 45.5],
            'vazio' => ['', null],
            'nulo' => [null, null],
            'so o prefixo' => ['R$', null],
            'letras' => ['abc', null],
            'misturado' => ['12abc', null],
            'negativo em texto' => ['-10,00', null],
            'negativo nativo' => [-5, null],
            'array' => [[1, 2], null],
            'booleano' => [true, null],
        ];
    }

    #[DataProvider('valores')]
    public function test_parse_money(mixed $entrada, ?float $esperado): void
    {
        $this->assertEqualsWithDelta($esperado, FaturaImportParser::parseMoney($entrada), 0.0001);

        if ($esperado === null) {
            $this->assertNull(FaturaImportParser::parseMoney($entrada));
        }
    }

    /**
     * @return array<string, array{0: mixed, 1: string|null}>
     */
    public static function datas(): array
    {
        return [
            'dia/mes/ano' => ['15/03/2026', '2026-03-15'],
            'ano-mes-dia' => ['2026-03-15', '2026-03-15'],
            'com espacos' => ['  01/12/2026 ', '2026-12-01'],
            'dia inexistente' => ['31/02/2026', null],
            'mes inexistente' => ['10/13/2026', null],
            'ano-mes-dia invalido' => ['2026-02-30', null],
            'sem zero a esquerda' => ['1/3/2026', null],
            'ano com dois digitos' => ['15/03/26', null],
            'texto' => ['amanha', null],
            'vazio' => ['', null],
            'nulo' => [null, null],
            'numero' => [20260315, null],
        ];
    }

    #[DataProvider('datas')]
    public function test_parse_strict_date(mixed $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, FaturaImportParser::parseStrictDate($entrada));
    }

    /**
     * @return array<string, array{0: mixed, 1: string|null}>
     */
    public static function descontos(): array
    {
        return [
            'vazio e absoluto' => ['', 'absoluto'],
            'nulo e absoluto' => [null, 'absoluto'],
            'R$' => ['R$', 'absoluto'],
            'absoluto' => ['Absoluto', 'absoluto'],
            'percentual simbolo' => ['%', 'relativo'],
            'relativo' => ['RELATIVO', 'relativo'],
            'percentual' => ['  percentual ', 'relativo'],
            'desconhecido' => ['metade', null],
        ];
    }

    #[DataProvider('descontos')]
    public function test_parse_tipo_desconto(mixed $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, FaturaImportParser::parseTipoDesconto($entrada));
    }

    public function test_importacao_so_aceita_pendente_atrasado_e_cancelado(): void
    {
        $this->assertSame(StatusFatura::Pendente, FaturaImportParser::parseStatus(''));
        $this->assertSame(StatusFatura::Pendente, FaturaImportParser::parseStatus(null));
        $this->assertSame(StatusFatura::Pendente, FaturaImportParser::parseStatus(' Pendente '));
        $this->assertSame(StatusFatura::Atrasado, FaturaImportParser::parseStatus('ATRASADO'));
        $this->assertSame(StatusFatura::Cancelado, FaturaImportParser::parseStatus('cancelado'));
        $this->assertSame(StatusFatura::Atrasado, FaturaImportParser::parseStatus(StatusFatura::Atrasado));
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function statusRecusadosNaImportacao(): array
    {
        return [
            'pago' => ['pago'],
            'parcial' => ['parcial'],
            'pago parcialmente' => ['Pago Parcialmente'],
            'enum pago' => [StatusFatura::Pago],
            'enum parcial' => [StatusFatura::Parcial],
            'desconhecido' => ['talvez'],
        ];
    }

    #[DataProvider('statusRecusadosNaImportacao')]
    public function test_importacao_recusa_fatura_paga_parcial_ou_status_desconhecido(mixed $status): void
    {
        $this->expectException(RowImportFailedException::class);

        FaturaImportParser::parseStatus($status);
    }

    public function test_status_tolerante_aceita_todos_os_rotulos_do_enum(): void
    {
        $this->assertNull(FaturaImportParser::parseStatusTolerante(''));
        $this->assertNull(FaturaImportParser::parseStatusTolerante(null));
        $this->assertSame(StatusFatura::Pago, FaturaImportParser::parseStatusTolerante('Pago'));
        $this->assertSame(StatusFatura::Parcial, FaturaImportParser::parseStatusTolerante('pago parcialmente'));
        $this->assertSame(StatusFatura::Parcial, FaturaImportParser::parseStatusTolerante('parcial'));
        $this->assertSame(StatusFatura::Pendente, FaturaImportParser::parseStatusTolerante(' pendente'));
        $this->assertSame(StatusFatura::Cancelado, FaturaImportParser::parseStatusTolerante(StatusFatura::Cancelado));
    }

    public function test_status_tolerante_recusa_valor_desconhecido(): void
    {
        $this->expectException(RowImportFailedException::class);
        $this->expectExceptionMessage("Status 'quitada' inválido");

        FaturaImportParser::parseStatusTolerante('quitada');
    }
}
