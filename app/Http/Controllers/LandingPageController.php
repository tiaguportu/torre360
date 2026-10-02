<?php

namespace App\Http\Controllers;

use App\Models\LandingLead;
use App\Rules\RecaptchaV3;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function index(): View
    {
        return view('landing');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'mensagem' => ['nullable', 'string'],
            'recaptcha_token' => ['nullable', new RecaptchaV3($request->ip())],
        ]);

        LandingLead::create($data);

        return back()->with('success', 'Sua solicitação foi enviada com sucesso! Em breve entraremos em contato.');
    }
}
