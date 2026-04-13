<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MobileTokenController extends Controller
{
    public function store(Request $request)
    {
        Log::info('--- TENTATIVA DE REGISTRO DE TOKEN FCM ---');
        Log::info('IP: '.$request->ip());
        Log::info('Dados recebidos: ', $request->all());

        $request->validate([
            'token' => 'required|string',
            'platform' => 'nullable|string',
        ]);

        $user = $request->user();

        if ($user) {
            Log::info('Usuário identificado: '.$user->email);
            $user->update([
                'fcm_token' => $request->token,
                'device_type' => $request->platform,
            ]);

            return response()->json(['message' => 'Token registrado com sucesso']);
        }

        Log::warning('Token recebido, mas nenhum usuário estava logado na sessão/API.');

        return response()->json([
            'message' => 'Token recebido pelo servidor, mas você não está logado.',
            'debug_received_token' => $request->token,
        ], 200); // Retornamos 200 para o app não achar que deu erro
    }
}
