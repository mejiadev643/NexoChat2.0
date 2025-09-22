<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

class BroadcastAuthController extends Controller
{
    public function authenticate(Request $request)
    {
        Log::info('=== PUSHER AUTH REQUEST ===');

        // Debug de los datos recibidos
        Log::info('Request method: ' . $request->method());
        Log::info('Request content type: ' . $request->header('Content-Type'));
        Log::info('Request data:', $request->all());
        Log::info('Query parameters:', $request->query());

        // Pusher envía los datos como form-data, no como JSON
        $channelName = $request->input('channel_name');
        $socketId = $request->input('socket_id');

        Log::info('Datos extraídos:', [
            'channel_name' => $channelName,
            'socket_id' => $socketId
        ]);

        if (!$request->user()) {
            Log::error('Usuario no autenticado');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (empty($channelName) || empty($socketId)) {
            Log::error('Datos incompletos', [
                'channel_name' => $channelName,
                'socket_id' => $socketId
            ]);
            return response()->json(['error' => 'Invalid request'], 400);
        }

        try {
            // Forzar los valores en el request para que Broadcast::auth los encuentre
            $request->merge([
                'channel_name' => $channelName,
                'socket_id' => $socketId
            ]);

            Log::info('Autenticando usuario ' . $request->user()->id . ' para canal: ' . $channelName);

            return Broadcast::auth($request);

        } catch (\Exception $e) {
            Log::error('Error de autenticación:', [
                'message' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'channel' => $channelName
            ]);
            return response()->json(['error' => 'Access denied'], 403);
        }
    }
}
