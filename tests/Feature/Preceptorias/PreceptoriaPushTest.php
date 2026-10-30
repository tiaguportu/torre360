<?php

namespace Tests\Feature\Preceptorias;

use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use App\Models\Preceptoria;
use App\Models\User;
use App\Notifications\Preceptorias\LembretePreceptoriaNotification;
use App\Notifications\Preceptorias\PreceptoriaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O FcmChannel engole qualquer exceção do toPush (só grava no log), então um toPush que lança
 * deixa o push sem sair e sem erro visível. Estes testes chamam o toPush direto para pegar isso.
 */
class PreceptoriaPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_o_push_de_agendamento_leva_o_link_do_registro(): void
    {
        $preceptoria = Preceptoria::factory()->create();

        $push = (new PreceptoriaNotification($preceptoria, 'agendamento'))->toPush(User::factory()->create());

        $this->assertSame(PreceptoriaResource::getUrl('view', ['record' => $preceptoria], isAbsolute: false), $push['data']['url']);
        $this->assertSame($preceptoria->id, $push['data']['preceptoria_id']);
    }

    public function test_o_push_de_liberacao_leva_a_listagem(): void
    {
        $preceptoria = Preceptoria::factory()->create();

        $push = (new PreceptoriaNotification($preceptoria, 'liberacao'))->toPush(User::factory()->create());

        $this->assertSame('/admin/preceptorias', $push['data']['url']);
    }

    public function test_o_push_do_lembrete_leva_o_link_do_registro(): void
    {
        $preceptoria = Preceptoria::factory()->create();

        $push = (new LembretePreceptoriaNotification($preceptoria))->toPush(User::factory()->create());

        $this->assertSame(PreceptoriaResource::getUrl('view', ['record' => $preceptoria], isAbsolute: false), $push['data']['url']);
        $this->assertSame($preceptoria->id, $push['data']['preceptoria_id']);
    }

    public function test_o_link_do_push_e_relativo_ao_dominio(): void
    {
        $preceptoria = Preceptoria::factory()->create();

        $push = (new LembretePreceptoriaNotification($preceptoria))->toPush(User::factory()->create());

        $this->assertStringStartsWith('/', $push['data']['url']);
        $this->assertStringNotContainsString('://', $push['data']['url']);
    }
}
