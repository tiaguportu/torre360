<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MobileTokenController extends Controller
{
    public function store(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('--- TENTATIVA DE REGISTRO DE TOKEN FCM ---');
        \Illuminate\Support\Facades\Log::info('IP: ' . $request->ip());
        \Illuminate\Support\Facades\Log::info('Dados recebidos: ', $request->all());

        $request->validate([
            'token' => 'required|string',
            'platform' => 'nullable|string',
        ]);

        $user = $request->user();

        if ($user) {
            \Illuminate\Support\Facades\Log::info('Usuário identificado: ' . $user->email);
            $user->update([
                'fcm_token' => $request->token,
                'device_type' => $request->platform,
            ]);

            return response()->json(['message' => 'Token registrado com sucesso']);
        }

        \Illuminate\Support\Facades\Log::warning('Token recebido, mas nenhum usuário estava logado na sessão/API.');
        return response()->json([
            'message' => 'Token recebido pelo servidor, mas você não está logado.',
            'debug_received_token' => $request->token
        ], 200); // Retornamos 200 para o app não achar que deu erro
    }
}
