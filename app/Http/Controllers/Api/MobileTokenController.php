<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MobileTokenController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'platform' => 'nullable|string',
        ]);

        $user = $request->user();

        if ($user) {
            $user->update([
                'fcm_token' => $request->token,
                'device_type' => $request->platform,
            ]);

            return response()->json(['message' => 'Token registrado com sucesso']);
        }

        Log::warning('Tentativa de registro de token FCM sem usuário autenticado.', ['ip' => $request->ip()]);

        return response()->json([
            'message' => 'Token recebido pelo servidor, mas você não está logado.',
        ], 200); // Retornamos 200 para o app não achar que deu erro
    }
}
