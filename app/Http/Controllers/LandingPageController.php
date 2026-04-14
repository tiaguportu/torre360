<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LandingPageController extends Controller
{
    public function index()
    {
        return view('landing');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'whatsapp' => 'nullable|string|max:20',
            'mensagem' => 'nullable|string',
        ]);

        \App\Models\LandingLead::create($data);

        return back()->with('success', 'Sua solicitação foi enviada com sucesso! Em breve entraremos em contato.');
    }
}
