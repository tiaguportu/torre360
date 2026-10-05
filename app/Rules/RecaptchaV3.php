<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Validação de tokens do Google reCAPTCHA v3.
 */
class RecaptchaV3 implements ValidationRule
{
    /**
     * Regra implícita: o Laravel só executa regras de classe em campos presentes e não vazios.
     * Sem isto, omitir `recaptcha_token` (ou enviá-lo vazio) pularia a verificação inteira.
     */
    public bool $implicit = true;

    public function __construct(
        public ?string $ip = null,
        public float $minScore = 0.3
    ) {
        $this->ip ??= request()->ip();
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $siteKey = config('services.recaptcha.site_key');
        $secret = config('services.recaptcha.secret');

        if (empty($siteKey) || empty($secret)) {
            Log::info('reCAPTCHA ignorado: Chaves não configuradas no ambiente');

            return;
        }

        if (empty($value) || ! is_string($value)) {
            Log::warning('reCAPTCHA falhou: Token ausente no request com chaves configuradas');
            $fail('Verificação de segurança ausente. Por favor, tente novamente.');

            return;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->connectTimeout(3)
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secret,
                    'response' => $value,
                    'remoteip' => $this->ip,
                ]);

            $result = $response->json();

            if (! ($result['success'] ?? false) || (float) ($result['score'] ?? 0) < $this->minScore) {
                Log::warning('reCAPTCHA falhou: Score baixo ou erro na API', ['result' => $result]);
                $fail('O sistema detectou uma atividade suspeita. Por favor, tente preencher o formulário novamente.');
            }
        } catch (\Throwable $e) {
            Log::error('Erro ao conectar com API do reCAPTCHA: '.$e->getMessage());
        }
    }
}
