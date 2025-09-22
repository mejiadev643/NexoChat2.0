<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Canal para el modelo User (opcional)
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal de conversación privada - CORREGIDO
Broadcast::channel('conversation.{conversationId}', function (User $user, $conversationId) {
    \Log::info('Validando acceso a conversación', [
        'user_id' => $user->id,
        'conversation_id' => $conversationId
    ]);

    $exists = $user->conversations()->where('conversations.id', $conversationId)->exists();

    \Log::info('Resultado validación', [
        'user_id' => $user->id,
        'conversation_id' => $conversationId,
        'tiene_acceso' => $exists
    ]);

    return $exists;
});

// Canal privado del usuario - CORREGIDO
Broadcast::channel('user.{userId}', function (User $user, $userId) {
    \Log::info('Validando acceso a canal usuario', [
        'user_actual' => $user->id,
        'user_solicitado' => $userId
    ]);

    $tieneAcceso = (int) $user->id === (int) $userId;

    \Log::info('Resultado validación usuario', [
        'tiene_acceso' => $tieneAcceso
    ]);

    return $tieneAcceso;
});
