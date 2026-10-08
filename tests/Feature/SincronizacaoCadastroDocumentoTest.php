<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoDocumento;
use App\Models\DocumentoInserido;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoDocumento;
use App\Rules\Cpf;
use App\Services\SincronizacaoCadastroDocumentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "Sincronizar com Cadastro": os dados extraídos pela IA são texto livre (datas em DD/MM/AAAA). Antes eram gravados
 * crus — "05/03/2015" virava 3 de maio no aluno e "15/03/2015" lançava exceção — e CPF inválido/duplicado derrubava
 * a ação ou entrava no cadastro.
 */
class SincronizacaoCadastroDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private const CPF_VALIDO = '529.982.247-25';

    /**
     * @param  array<string, mixed>  $extraidos
     * @return array{0: DocumentoInserido, 1: Pessoa, 2: InteressadoDependente}
     */
    private function cenario(array $extraidos, bool $documentoDoAluno = false, array $pessoa = []): array
    {
        $origem = OrigemInteressado::create(['nome' => 'Site']);
        $status = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);

        $pessoa = Pessoa::create($pessoa + ['nome' => 'Carlos Alberto Silva', 'telefone' => '11988887777', 'email' => 'carlos@example.com']);

        $interessado = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'origem_interessado_id' => $origem->id,
            'status_interessado_id' => $status->id,
        ]);

        $dependente = InteressadoDependente::create([
            'interessado_id' => $interessado->id,
            'nome_crianca' => 'Lucas Silva',
            'data_nascimento' => null,
        ]);

        $tipo = TipoDocumento::create(['nome' => 'RG', 'flag_obrigatorio' => true, 'status' => 'ativo']);

        $documento = DocumentoInserido::create([
            'interessado_id' => $interessado->id,
            'tipo_documento_id' => $tipo->id,
            'interessado_dependente_id' => $documentoDoAluno ? $dependente->id : null,
            'arquivo_path' => 'documentos_candidatos/'.$interessado->id.'/doc.pdf',
            'nome_arquivo_original' => 'doc.pdf',
            'hash_arquivo' => 'abc123',
            'status' => SituacaoDocumento::EM_ANALISE,
            'dados_ia' => ['dados_extraidos' => $extraidos],
            'analisado_ia_em' => now(),
        ]);

        return [$documento, $pessoa, $dependente];
    }

    private function servico(): SincronizacaoCadastroDocumentoService
    {
        return app(SincronizacaoCadastroDocumentoService::class);
    }

    /**
     * @return array<string, array{0: mixed, 1: ?string}>
     */
    public static function datas(): array
    {
        $amanha = now()->addDay()->format('d/m/Y');

        return [
            'dia > 12 (antes lançava exceção)' => ['15/03/2015', '2015-03-15'],
            'dia <= 12 (antes virava mês/dia)' => ['05/03/2015', '2015-03-05'],
            'sem zero à esquerda' => ['5/3/2015', '2015-03-05'],
            'hífen' => ['05-03-2015', '2015-03-05'],
            'ponto' => ['05.03.2015', '2015-03-05'],
            'ISO' => ['2015-03-05', '2015-03-05'],
            'ISO com hora' => ['2015-03-05T00:00:00Z', '2015-03-05'],
            'espaços ao redor' => ['  05/03/2015 ', '2015-03-05'],
            'dia inexistente' => ['31/02/2015', null],
            'mês 13' => ['15/13/2015', null],
            'zerada' => ['00/00/0000', null],
            'ano com 2 dígitos' => ['05/03/15', null],
            'por extenso' => ['5 de março de 2015', null],
            'anterior a 1900' => ['01/01/1899', null],
            'futura' => [$amanha, null],
            'vazia' => ['', null],
            'nula' => [null, null],
        ];
    }

    #[DataProvider('datas')]
    public function test_normalizar_data(mixed $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, SincronizacaoCadastroDocumentoService::normalizarData($entrada));
    }

    public function test_pre_condicao_do_cpf_de_teste(): void
    {
        $this->assertTrue(Cpf::valido(self::CPF_VALIDO));
    }

    // ------------------------------------------------------------------ aluno

    public function test_data_do_aluno_em_formato_brasileiro_e_gravada_sem_trocar_dia_e_mes(): void
    {
        [$documento, , $dependente] = $this->cenario(['data_nascimento' => '05/03/2015'], documentoDoAluno: true);

        $resultado = $this->servico()->sincronizar($documento);

        $this->assertSame('2015-03-05', $dependente->fresh()->data_nascimento->toDateString());
        $this->assertSame(['Data de nascimento do aluno (05/03/2015)'], $resultado['alterados']);
        $this->assertSame([], $resultado['ignorados']);
    }

    public function test_data_do_aluno_com_dia_maior_que_12_nao_lanca_excecao(): void
    {
        [$documento, , $dependente] = $this->cenario(['data_nascimento' => '15/03/2015'], documentoDoAluno: true);

        $this->servico()->sincronizar($documento);

        $this->assertSame('2015-03-15', $dependente->fresh()->data_nascimento->toDateString());
    }

    public function test_data_invalida_do_aluno_e_descartada_com_motivo(): void
    {
        [$documento, , $dependente] = $this->cenario(['data_nascimento' => '31/02/2015'], documentoDoAluno: true);

        $resultado = $this->servico()->sincronizar($documento);

        $this->assertNull($dependente->fresh()->data_nascimento);
        $this->assertSame([], $resultado['alterados']);
        $this->assertCount(1, $resultado['ignorados']);
        $this->assertStringContainsString('31/02/2015', $resultado['ignorados'][0]);
    }

    public function test_data_do_aluno_ja_preenchida_nao_e_sobrescrita(): void
    {
        [$documento, , $dependente] = $this->cenario(['data_nascimento' => '05/03/2015'], documentoDoAluno: true);
        $dependente->update(['data_nascimento' => '2014-01-10']);

        $resultado = $this->servico()->sincronizar($documento);

        $this->assertSame('2014-01-10', $dependente->fresh()->data_nascimento->toDateString());
        $this->assertSame([], $resultado['alterados']);
    }

    // ------------------------------------------------------------------ responsável

    public function test_cpf_rg_e_data_do_responsavel_sao_gravados_normalizados(): void
    {
        [$documento, $pessoa] = $this->cenario([
            'cpf' => self::CPF_VALIDO,
            'rg' => '  12.345.678-9 ',
            'data_nascimento' => '12/08/1985',
        ]);

        $resultado = $this->servico()->sincronizar($documento);

        $pessoa->refresh();
        $this->assertSame('52998224725', $pessoa->cpf);
        $this->assertSame('12.345.678-9', $pessoa->identidade);
        $this->assertSame('1985-08-12', substr((string) $pessoa->data_nascimento, 0, 10));
        $this->assertCount(3, $resultado['alterados']);
    }

    public function test_cpf_com_digito_verificador_invalido_e_descartado_mas_o_resto_e_aplicado(): void
    {
        [$documento, $pessoa] = $this->cenario(['cpf' => '529.982.247-26', 'rg' => '1234567']);

        $resultado = $this->servico()->sincronizar($documento);

        $pessoa->refresh();
        $this->assertNull($pessoa->cpf);
        $this->assertSame('1234567', $pessoa->identidade);
        $this->assertCount(1, $resultado['ignorados']);
        $this->assertStringContainsString('não é válido', $resultado['ignorados'][0]);
    }

    public function test_cpf_que_ja_pertence_a_outra_pessoa_e_descartado_sem_excecao(): void
    {
        Pessoa::create(['nome' => 'Outra Pessoa', 'cpf' => '52998224725']);
        [$documento, $pessoa] = $this->cenario(['cpf' => self::CPF_VALIDO]);

        $resultado = $this->servico()->sincronizar($documento);

        $this->assertNull($pessoa->fresh()->cpf);
        $this->assertSame([], $resultado['alterados']);
        $this->assertStringContainsString('já pertence a outra pessoa', $resultado['ignorados'][0]);
    }

    public function test_campos_ja_preenchidos_do_responsavel_nao_sao_sobrescritos(): void
    {
        [$documento, $pessoa] = $this->cenario(
            ['cpf' => self::CPF_VALIDO, 'rg' => '999', 'data_nascimento' => '12/08/1985'],
            pessoa: ['cpf' => '11144477735', 'identidade' => '111', 'data_nascimento' => '1980-01-01'],
        );

        $resultado = $this->servico()->sincronizar($documento);

        $pessoa->refresh();
        $this->assertSame('11144477735', $pessoa->cpf);
        $this->assertSame('111', $pessoa->identidade);
        $this->assertSame('1980-01-01', substr((string) $pessoa->data_nascimento, 0, 10));
        $this->assertSame([], $resultado['alterados']);
        $this->assertSame([], $resultado['ignorados']);
    }

    public function test_rg_em_formato_inesperado_e_descartado(): void
    {
        [$documento, $pessoa] = $this->cenario(['rg' => str_repeat('9', 60)]);

        $resultado = $this->servico()->sincronizar($documento);

        $this->assertNull($pessoa->fresh()->identidade);
        $this->assertStringContainsString('formato inesperado', $resultado['ignorados'][0]);
    }

    // ------------------------------------------------------------------ geral

    public function test_sem_dados_extraidos_nao_faz_nada(): void
    {
        [$documento] = $this->cenario([]);

        $this->assertSame(
            ['sem_dados' => true, 'alterados' => [], 'ignorados' => []],
            $this->servico()->sincronizar($documento),
        );
    }

    public function test_alteracao_do_cadastro_fica_registrada_na_linha_do_tempo_como_automatica(): void
    {
        [$documento, , ] = $this->cenario(['rg' => '1234567']);

        $this->servico()->sincronizar($documento, null);

        $registro = HistoricoContato::where('interessado_id', $documento->interessado_id)->latest('id')->first();
        $this->assertNotNull($registro);
        $this->assertTrue($registro->automatico);
        $this->assertStringContainsString('RG (1234567)', $registro->relato);
    }

    public function test_nada_alterado_nao_gera_registro_na_linha_do_tempo(): void
    {
        [$documento] = $this->cenario(['cpf' => '000.000.000-00']);

        $this->servico()->sincronizar($documento);

        $this->assertSame(0, HistoricoContato::where('interessado_id', $documento->interessado_id)->count());
    }
}
