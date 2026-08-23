<?php

namespace App\Http\Controllers;

use App\Models\LandingLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LandingPageController extends Controller
{
    public function index()
    {
        return view('landing');
    }

    public function store(Request $request)
    {
        $this->verificarRecaptcha($request);

        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'whatsapp' => 'nullable|string|max:20',
            'mensagem' => 'nullable|string',
        ]);

        LandingLead::create($data);

        return back()->with('success', 'Sua solicitação foi enviada com sucesso! Em breve entraremos em contato.');
    }

    /**
     * Verifica o token reCAPTCHA v3 com a API do Google.
     */
    private function verificarRecaptcha(Request $request): void
    {
        $siteKey = config('services.recaptcha.site_key');
        $secret = config('services.recaptcha.secret');

        if (empty($siteKey) || empty($secret)) {
            Log::info('reCAPTCHA ignorado: Chaves não configuradas no .env');

            return;
        }

        $token = $request->input('recaptcha_token');

        if (empty($token)) {
            Log::warning('reCAPTCHA falhou: Token ausente no request com chaves configuradas');
            abort(422, 'Verificação de segurança ausente. Por favor, tente novamente.');
        }

        try {
            $response = Http::asForm()->timeout(5)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

            $result = $response->json();

            if (! ($result['success'] ?? false) || ($result['score'] ?? 0) < 0.3) {
                Log::warning('reCAPTCHA falhou: Score baixo ou erro na API', ['result' => $result]);
                abort(422, 'O sistema detectou uma atividade suspeita. Por favor, tente preencher o formulário novamente.');
            }
        } catch (\Exception $e) {
            Log::error('Erro ao conectar com API do reCAPTCHA: '.$e->getMessage());
        }
    }
}
