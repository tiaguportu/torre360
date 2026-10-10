<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\VisitaInteressado;
use App\Services\CrmIaVendasService;
use App\Services\GeminiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Minimização dos dados enviados à IA no Dossiê e no Copiloto (item 6 da auditoria do CRM): a IA recebe o que
 * precisa para sugerir a próxima ação, não telefone, e-mail, sobrenomes nem números digitados em texto livre.
 */
class IaMinimizacaoDadosTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> trechos que nunca podem chegar ao Gemini */
    private function proibidos(): array
    {
        return [
            'telefone do cadastro' => '11988887777',
            'telefone com máscara' => '(11) 98888-7777',
            'e-mail do cadastro' => 'maria.privada@exemplo.com',
            'sobrenome do responsável' => 'Albuquerque',
            'sobrenome do aluno' => 'Fontenele',
            'telefone digitado na observação' => '21 97777-6666',
            'CPF digitado no relato' => '529.982.247-25',
            'e-mail digitado na visita' => 'avo.joana@exemplo.com',
        ];
    }

    private function lead(): Interessado
    {
        $pessoa = Pessoa::factory()->create([
            'nome' => 'Maria Albuquerque',
            'telefone' => '(11) 98888-7777',
            'email' => 'maria.privada@exemplo.com',
        ]);

        $lead = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'status_interessado_id' => StatusInteressado::factory()->create(['nome' => 'Novo'])->id,
            'origem_interessado_id' => OrigemInteressado::firstOrCreate(['nome' => 'Site'])->id,
            'observacoes' => 'Ligar para o pai no 21 97777-6666 depois das 18h. Mensalidade de R$ 1.250,00.',
        ]);

        InteressadoDependente::create(['interessado_id' => $lead->id, 'nome_crianca' => 'Lucas Fontenele', 'data_nascimento' => now()->subYears(7)]);
        HistoricoContato::create([
            'interessado_id' => $lead->id,
            'tipo_contato_interessado_id' => TipoContatoInteressado::firstOrCreate(['nome' => 'WhatsApp'])->id,
            'relato' => 'Mãe passou o CPF 529.982.247-25 para a simulação em 12/10/2026 às 14:30.',
            'data_contato' => now()->subDay(),
        ]);
        VisitaInteressado::create([
            'interessado_id' => $lead->id,
            'data_hora' => now()->addDays(3),
            'observacoes' => 'Vem com a avó, contato avo.joana@exemplo.com',
        ]);

        return $lead;
    }

    /**
     * Executa $acao com o Gemini mockado e devolve todo o texto enviado (instrução + conteúdo).
     */
    private function textoEnviadoAoGemini(callable $acao): string
    {
        $enviado = '';
        $mock = Mockery::mock(GeminiAgentService::class);
        $mock->shouldReceive('callGeminiApi')->andReturnUsing(function (array $payload) use (&$enviado): array {
            $enviado .= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return ['candidates' => [['content' => ['parts' => [['text' => json_encode([
                'resumo_executivo' => 'ok', 'temperatura_sugerida' => 'morno', 'proxima_acao_sugerida' => 'ok', 'dossie_markdown' => '### ok',
            ])]]]]]];
        });
        $this->app->instance(GeminiAgentService::class, $mock);

        $acao(app(CrmIaVendasService::class));

        return $enviado;
    }

    public function test_dossie_nao_envia_contatos_sobrenomes_nem_numeros_digitados_em_texto_livre(): void
    {
        $lead = $this->lead();

        $enviado = $this->textoEnviadoAoGemini(fn (CrmIaVendasService $s) => $s->gerarDossie($lead->fresh()));

        $this->assertNotSame('', $enviado, 'O Gemini deveria ter sido chamado.');
        foreach ($this->proibidos() as $descricao => $trecho) {
            $this->assertStringNotContainsString($trecho, $enviado, "Vazou para a IA: {$descricao}.");
        }
    }

    public function test_copiloto_nao_envia_contatos_sobrenomes_nem_numeros_digitados_em_texto_livre(): void
    {
        $lead = $this->lead();

        $enviado = $this->textoEnviadoAoGemini(fn (CrmIaVendasService $s) => $s->gerarMensagemCopiloto($lead->fresh(), 'fechamento'));

        $this->assertNotSame('', $enviado);
        foreach ($this->proibidos() as $descricao => $trecho) {
            $this->assertStringNotContainsString($trecho, $enviado, "Vazou para a IA: {$descricao}.");
        }
    }

    public function test_o_que_a_ia_precisa_continua_no_contexto(): void
    {
        $lead = $this->lead();

        $enviado = $this->textoEnviadoAoGemini(fn (CrmIaVendasService $s) => $s->gerarDossie($lead->fresh()));

        $this->assertStringContainsString('Maria', $enviado, 'O primeiro nome do responsável segue, para a saudação.');
        $this->assertStringContainsString('Lucas (7 anos)', $enviado, 'Primeiro nome e idade do aluno seguem.');
        $this->assertStringContainsString('Mensalidade de R$ 1.250,00', $enviado, 'Valores em reais não são confundidos com telefone.');
        $this->assertStringContainsString('12/10/2026 às 14:30', $enviado, 'Datas e horários não são confundidos com telefone.');
        $this->assertStringContainsString('[telefone omitido]', $enviado);
        $this->assertStringContainsString('[CPF omitido]', $enviado);
        $this->assertStringContainsString('[e-mail omitido]', $enviado);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function textosParaOcultar(): array
    {
        return [
            'celular com DDD e máscara' => ['Ligar (11) 98888-7777 hoje', 'Ligar [telefone omitido] hoje'],
            // 11 dígitos seguidos servem tanto para celular com DDD quanto para CPF: o marcador é o do CPF, o dado some do mesmo jeito.
            'celular só com dígitos' => ['Zap 11988887777', 'Zap [CPF omitido]'],
            'celular com +55' => ['+55 11 98888-7777', '[telefone omitido]'],
            'fixo sem DDD' => ['Fixo 3333-4444', 'Fixo [telefone omitido]'],
            'cpf com máscara' => ['CPF 529.982.247-25 ok', 'CPF [CPF omitido] ok'],
            'cpf só dígitos' => ['CPF 52998224725', 'CPF [CPF omitido]'],
            'e-mail' => ['Escreva para ana.souza+crm@escola.com.br', 'Escreva para [e-mail omitido]'],
            'data com barras' => ['Visita em 28/09/2026 14:30', 'Visita em 28/09/2026 14:30'],
            'data ISO' => ['Nascimento 2016-08-20', 'Nascimento 2016-08-20'],
            'valor em reais' => ['Mensalidade R$ 2.500,00', 'Mensalidade R$ 2.500,00'],
            'série' => ['Quer o 1º Ano em 2027', 'Quer o 1º Ano em 2027'],
            'texto sem dados' => ['Família interessada no período integral.', 'Família interessada no período integral.'],
        ];
    }

    #[DataProvider('textosParaOcultar')]
    public function test_ocultar_dados_pessoais_troca_so_contatos_e_documentos(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, CrmIaVendasService::ocultarDadosPessoais($entrada));
    }
}
