<?php

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\MensagemWhatsappTemplate;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Services\ConsultorWhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InteressadoConsultorWhatsappTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ConsultorWhatsappService
    {
        return app(ConsultorWhatsappService::class);
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function consultor(?string $telefone = '(11) 98888-7777', string $nome = 'Carla Souza'): User
    {
        $user = User::factory()->create(['name' => $nome, 'activated_at' => now()]);
        $pessoa = Pessoa::factory()->create(['nome' => $nome, 'telefone' => $telefone, 'user_id' => $user->id]);
        $user->pessoas()->attach($pessoa);

        return $user;
    }

    private function lead(?User $consultor, array $pessoa = [], array $atributos = []): Interessado
    {
        $status = StatusInteressado::firstOrCreate(
            ['nome' => 'Em Atendimento'],
            ['cor' => 'warning', 'ordem' => 2, 'is_final' => false, 'is_ganho' => false],
        );
        $origem = OrigemInteressado::firstOrCreate(['nome' => 'Instagram']);
        $responsavel = Pessoa::factory()->create(array_merge(['nome' => 'Maria Responsável', 'telefone' => '(21) 99999-1111'], $pessoa));

        return Interessado::create(array_merge([
            'pessoa_id' => $responsavel->id,
            'usuario_id' => $consultor?->id,
            'origem_interessado_id' => $origem->id,
            'status_interessado_id' => $status->id,
            'temperatura' => 'quente',
        ], $atributos));
    }

    private function registrarContato(Interessado $lead, string $relato, \DateTimeInterface $quando, ?string $resultado = null): void
    {
        $tipo = TipoContatoInteressado::firstOrCreate(['nome' => 'WhatsApp']);

        $lead->historicos()->create([
            'tipo_contato_interessado_id' => $tipo->id,
            'relato' => $relato,
            'data_contato' => $quando,
            'resultado' => $resultado,
        ]);
    }

    /**
     * Decodifica a mensagem de um link wa.me para comparar o texto legível.
     */
    private function mensagemDaUrl(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return rawurldecode((string) ($query['text'] ?? ''));
    }

    public function test_normaliza_telefone_para_wa_me(): void
    {
        $service = $this->service();

        $this->assertSame('5511988887777', $service->normalizarTelefone('(11) 98888-7777'));
        $this->assertSame('551133334444', $service->normalizarTelefone('11 3333-4444'));
        $this->assertSame('5511988887777', $service->normalizarTelefone('+55 11 98888-7777'));
        $this->assertNull($service->normalizarTelefone(''));
        $this->assertNull($service->normalizarTelefone(null));
        $this->assertNull($service->normalizarTelefone('sem número'));
    }

    public function test_telefone_do_consultor_vem_da_pessoa_vinculada(): void
    {
        $this->assertSame('5511988887777', $this->service()->telefoneDoConsultor($this->consultor()));
    }

    public function test_telefone_do_consultor_e_nulo_sem_pessoa_ou_sem_telefone(): void
    {
        $this->assertNull($this->service()->telefoneDoConsultor(null));
        $this->assertNull($this->service()->telefoneDoConsultor(User::factory()->create()));
        $this->assertNull($this->service()->telefoneDoConsultor($this->consultor(null)));
    }

    public function test_telefone_do_consultor_prefere_a_pessoa_que_e_o_proprio_usuario(): void
    {
        $consultor = $this->consultor('(11) 98888-7777');
        $outraPessoa = Pessoa::factory()->create(['telefone' => '(11) 97777-0000']);
        $consultor->pessoas()->attach($outraPessoa);

        $this->assertSame('5511988887777', $this->service()->telefoneDoConsultor($consultor->fresh()));
    }

    public function test_telefone_do_consultor_usa_outra_pessoa_quando_a_propria_nao_tem_telefone(): void
    {
        $consultor = $this->consultor(null);
        $consultor->pessoas()->attach(Pessoa::factory()->create(['telefone' => '(11) 97777-0000']));

        $this->assertSame('5511977770000', $this->service()->telefoneDoConsultor($consultor->fresh()));
    }

    public function test_mensagem_traz_contato_direto_do_lead_e_resumo_dos_ultimos_tres_contatos(): void
    {
        $lead = $this->lead($this->consultor(), atributos: ['observacoes' => 'Prefere conversar à noite.']);

        $this->registrarContato($lead, 'Primeiro contato antigo', now()->subDays(9));
        $this->registrarContato($lead, 'Pediu valores da mensalidade', now()->subDays(6));
        $this->registrarContato($lead, 'Enviei a tabela de valores', now()->subDays(4));
        $this->registrarContato($lead, 'Quer conhecer a escola', now()->subDays(2), 'agendou_visita');

        $mensagem = $this->service()->mensagemDoInteressado($lead->fresh());

        $this->assertStringContainsString('Olá, Carla!', $mensagem);
        $this->assertStringContainsString('*Lead:* Maria Responsável', $mensagem);
        $this->assertStringContainsString('https://wa.me/5521999991111', $mensagem);
        $this->assertStringContainsString('*Status:* Em Atendimento', $mensagem);
        $this->assertStringContainsString('*Origem:* Instagram', $mensagem);
        $this->assertStringContainsString('🔥 Quente', $mensagem);
        $this->assertStringContainsString('Quer conhecer a escola', $mensagem);
        $this->assertStringContainsString('→ Agendou Visita', $mensagem);
        $this->assertStringContainsString('(WhatsApp)', $mensagem);
        $this->assertStringContainsString('Enviei a tabela de valores', $mensagem);
        $this->assertStringContainsString('Pediu valores da mensalidade', $mensagem);
        $this->assertStringNotContainsString('Primeiro contato antigo', $mensagem);
        $this->assertStringContainsString('*Observações:* Prefere conversar à noite.', $mensagem);

        // O contato mais recente vem primeiro.
        $this->assertLessThan(
            strpos($mensagem, 'Pediu valores da mensalidade'),
            strpos($mensagem, 'Quer conhecer a escola'),
        );
    }

    public function test_mensagem_inclui_alunos_proximo_contato_atrasado_e_visita(): void
    {
        $lead = $this->lead($this->consultor(), atributos: ['data_proximo_contato' => now()->subDay()]);
        InteressadoDependente::create(['interessado_id' => $lead->id, 'nome_crianca' => 'Pedro']);
        InteressadoDependente::create(['interessado_id' => $lead->id, 'nome_crianca' => 'Ana']);
        VisitaInteressado::factory()->create(['interessado_id' => $lead->id, 'data_hora' => now()->addDays(2)->setTime(10, 0)]);

        $mensagem = $this->service()->mensagemDoInteressado($lead->fresh());

        $this->assertStringContainsString('*Alunos:* Pedro, Ana', $mensagem);
        $this->assertStringContainsString('(atrasado)', $mensagem);
        $this->assertStringContainsString('*Visita agendada:* '.now()->addDays(2)->format('d/m/Y').' às 10:00h', $mensagem);
    }

    public function test_mensagem_sem_historico_informa_que_nao_ha_contatos_e_trata_lead_sem_telefone(): void
    {
        $lead = $this->lead($this->consultor(), pessoa: ['telefone' => null]);

        $mensagem = $this->service()->mensagemDoInteressado($lead->fresh());

        $this->assertStringContainsString('Ainda sem contatos registrados', $mensagem);
        $this->assertStringContainsString('telefone não informado', $mensagem);
        $this->assertStringNotContainsString('https://wa.me/', $mensagem);
    }

    public function test_relato_longo_e_truncado_em_uma_unica_linha(): void
    {
        $lead = $this->lead($this->consultor());
        $this->registrarContato($lead, "Linha um\n\nLinha dois ".str_repeat('x', 400), now()->subDay());

        $mensagem = $this->service()->mensagemDoInteressado($lead->fresh());

        $this->assertStringContainsString('Linha um Linha dois', $mensagem);
        $this->assertStringNotContainsString(str_repeat('x', 300), $mensagem);
        $this->assertStringContainsString('...', $mensagem);
    }

    public function test_mensagem_respeita_o_teto_descartando_os_contatos_mais_antigos(): void
    {
        $lead = $this->lead($this->consultor(), atributos: ['observacoes' => str_repeat('o', 400)]);

        foreach (range(1, 12) as $n) {
            InteressadoDependente::create(['interessado_id' => $lead->id, 'nome_crianca' => "Aluno número {$n} com sobrenome bem comprido"]);
        }

        foreach ([8, 6, 4] as $dias) {
            $this->registrarContato($lead, "Contato de {$dias} dias atrás ".str_repeat('y', 170), now()->subDays($dias));
        }

        $mensagem = $this->service()->mensagemDoInteressado($lead->fresh());

        $this->assertLessThanOrEqual(1500, mb_strlen($mensagem));
        $this->assertStringContainsString('Contato de 4 dias atrás', $mensagem);
        $this->assertStringNotContainsString('Contato de 8 dias atrás', $mensagem);
    }

    public function test_url_usa_o_telefone_do_consultor_ou_abre_sem_destinatario(): void
    {
        $this->assertStringStartsWith('https://wa.me/5511988887777?text=', $this->service()->urlParaInteressado($this->lead($this->consultor())->fresh()));

        $semTelefone = $this->service()->urlParaInteressado($this->lead($this->consultor(null))->fresh());
        $this->assertStringStartsWith('https://wa.me/?text=', $semTelefone);
        $this->assertStringContainsString('Maria Responsável', $this->mensagemDaUrl($semTelefone));
    }

    public function test_botao_whatsapp_do_interessado_continua_abrindo_o_wa_me_com_telefone_normalizado(): void
    {
        $lead = $this->lead($this->consultor());
        $template = MensagemWhatsappTemplate::create(['nome' => 'Boas-vindas', 'conteudo' => 'Olá [Nome do Responsável]!', 'ativo' => true]);

        $url = 'https://wa.me/5521999991111?text='.urlencode('Olá Maria Responsável!');

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->callTableAction('enviarWhatsapp', $lead, data: ['mensagem_whatsapp_template_id' => $template->id])
            ->assertJs('window.open('.json_encode($url).", '_blank')");
    }

    public function test_acao_da_linha_abre_o_whatsapp_do_consultor_em_nova_aba(): void
    {
        $lead = $this->lead($this->consultor());
        $this->registrarContato($lead, 'Quer conhecer a escola', now()->subDay());

        $url = $this->service()->urlParaInteressado($lead->fresh());

        $this->assertStringStartsWith('https://wa.me/5511988887777?text=', $url);
        $this->assertStringContainsString('https://wa.me/5521999991111', $this->mensagemDaUrl($url));
        $this->assertStringContainsString('Quer conhecer a escola', $this->mensagemDaUrl($url));

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->assertTableActionVisible('enviarAoConsultor', $lead)
            ->assertTableActionHasColor('enviarAoConsultor', 'success', $lead)
            ->assertTableActionHasUrl('enviarAoConsultor', $url, $lead)
            ->assertTableActionShouldOpenUrlInNewTab('enviarAoConsultor', $lead);
    }

    public function test_acao_da_linha_alerta_quando_o_consultor_esta_sem_telefone(): void
    {
        $lead = $this->lead($this->consultor(null));

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->assertTableActionVisible('enviarAoConsultor', $lead)
            ->assertTableActionHasColor('enviarAoConsultor', 'warning', $lead);
    }

    public function test_acao_da_linha_fica_oculta_sem_consultor(): void
    {
        $lead = $this->lead(null);

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->assertTableActionHidden('enviarAoConsultor', $lead);
    }

    public function test_agrupa_leads_por_consultor_com_uma_mensagem_por_consultor(): void
    {
        $carla = $this->consultor('(11) 98888-7777', 'Carla Souza');
        $bruno = $this->consultor(null, 'Bruno Lima');

        $leadDaCarla1 = $this->lead($carla, ['nome' => 'Lead Um']);
        $leadDaCarla2 = $this->lead($carla, ['nome' => 'Lead Dois']);
        $leadDoBruno = $this->lead($bruno, ['nome' => 'Lead Tres']);
        $leadSemConsultor = $this->lead(null, ['nome' => 'Lead Quatro']);

        $resultado = $this->service()->agruparPorConsultor(
            Interessado::with('usuario')->whereKey([$leadDaCarla1->id, $leadDaCarla2->id, $leadDoBruno->id, $leadSemConsultor->id])->get()
        );

        $this->assertCount(2, $resultado['grupos']);
        $this->assertSame([$leadSemConsultor->id], $resultado['semConsultor']->pluck('id')->all());

        $grupoCarla = $resultado['grupos']->firstWhere('consultor.id', $carla->id);
        $this->assertCount(2, $grupoCarla['interessados']);
        $this->assertTrue($grupoCarla['temTelefone']);
        $this->assertStringStartsWith('https://wa.me/5511988887777?text=', $grupoCarla['url']);
        $mensagemCarla = $this->mensagemDaUrl($grupoCarla['url']);
        $this->assertStringContainsString('Seguem 2 lead(s)', $mensagemCarla);
        $this->assertStringContainsString('Lead Um', $mensagemCarla);
        $this->assertStringContainsString('Lead Dois', $mensagemCarla);
        $this->assertStringNotContainsString('Lead Tres', $mensagemCarla);

        $grupoBruno = $resultado['grupos']->firstWhere('consultor.id', $bruno->id);
        $this->assertFalse($grupoBruno['temTelefone']);
        $this->assertStringStartsWith('https://wa.me/?text=', $grupoBruno['url']);
    }

    public function test_mensagem_em_lote_respeita_o_teto_e_avisa_quantos_ficaram_de_fora(): void
    {
        $consultor = $this->consultor();
        $leads = collect(range(1, 40))->map(fn (int $n): Interessado => $this->lead($consultor, ['nome' => "Lead numero {$n} com nome comprido para ocupar espaco"]));

        $mensagem = $this->service()->mensagemEmLote($consultor, Interessado::with('pessoa', 'status')->whereKey($leads->pluck('id'))->get());

        $this->assertLessThanOrEqual(1500, mb_strlen($mensagem));
        $this->assertStringContainsString('Seguem 40 lead(s)', $mensagem);
        $this->assertMatchesRegularExpression('/\.\.\. e mais \d+ lead\(s\)\./', $mensagem);
    }

    public function test_acao_em_lote_mostra_um_link_por_consultor_e_lista_leads_sem_consultor(): void
    {
        $carla = $this->consultor('(11) 98888-7777', 'Carla Souza');
        $bruno = $this->consultor(null, 'Bruno Lima');

        $leadDaCarla = $this->lead($carla, ['nome' => 'Lead da Carla']);
        $leadDoBruno = $this->lead($bruno, ['nome' => 'Lead do Bruno']);
        $leadSemConsultor = $this->lead(null, ['nome' => 'Lead Sem Dono']);

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->mountTableBulkAction('enviarAosConsultores', [$leadDaCarla, $leadDoBruno, $leadSemConsultor])
            ->assertMountedActionModalSee([
                'Enviar leads aos consultores',
                'Carla Souza',
                'Bruno Lima',
                'Lead da Carla',
                'Lead do Bruno',
                'Lead Sem Dono',
                '1 lead sem consultor',
                'sem telefone cadastrado',
                'href="https://wa.me/5511988887777?text=',
                'href="https://wa.me/?text=',
            ], escape: false);
    }

    public function test_acao_em_lote_avisa_quando_nenhum_lead_selecionado_tem_consultor(): void
    {
        $leadSemConsultor = $this->lead(null, ['nome' => 'Lead Sem Dono']);

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->mountTableBulkAction('enviarAosConsultores', [$leadSemConsultor])
            ->assertMountedActionModalSee([
                'Nenhum dos leads selecionados tem consultor responsável.',
                '1 lead sem consultor',
            ], escape: false)
            ->assertMountedActionModalDontSee('Abrir WhatsApp');
    }
}
