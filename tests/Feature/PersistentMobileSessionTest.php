<?php

namespace Tests\Feature;

use App\Http\Middleware\PersistentMobileSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PersistentMobileSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_estende_sessao_para_30_dias_em_requisicoes_do_app_mobile(): void
    {
        config(['session.lifetime' => 120]);

        $middleware = new PersistentMobileSession;
        $request = Request::create('/portal/dashboard', 'GET');
        $request->headers->set('User-Agent', 'Torre360App/1.0 (Android)');

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(PersistentMobileSession::DURACAO_MOBILE_MINUTOS, config('session.lifetime'));
        $this->assertFalse(config('session.expire_on_close'));
    }

    public function test_bloqueia_extensao_de_sessao_em_rotas_administrativas_mesmo_com_user_agent_mobile(): void
    {
        config(['session.lifetime' => 120]);

        $middleware = new PersistentMobileSession;
        $request = Request::create('/admin/login', 'GET');
        $request->headers->set('User-Agent', 'Torre360App/1.0 (Android)');

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(120, config('session.lifetime'), 'Não deve alterar o tempo de sessão em rotas /admin');
    }

    public function test_bloqueia_extensao_de_sessao_para_usuarios_com_papeis_administrativos(): void
    {
        config(['session.lifetime' => 120]);

        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($roleAdmin);

        $middleware = new PersistentMobileSession;
        $request = Request::create('/portal/algo', 'GET');
        $request->headers->set('User-Agent', 'Torre360App/1.0');
        $request->setUserResolver(fn () => $admin);

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(120, config('session.lifetime'), 'Usuários administrativos não devem ter a sessão prolongada');
    }
}
