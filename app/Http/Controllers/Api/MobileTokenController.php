<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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

        return response()->json(['message' => 'Usuário não autenticado'], 401);
    }
}
