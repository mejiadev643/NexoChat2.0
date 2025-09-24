<?php

namespace App\Http\Controllers;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\NewMessageNotification;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    public function conversations()
    {
        $user = Auth::user();

        $conversations = $user->conversations()
            ->with(['participants', 'latestMessage'])
            ->withCount(['messages as unread_count' => function ($query) use ($user) {
                $query->where('user_id', '!=', $user->id)
                    ->whereDoesntHave('deletedByUsers', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })
                    ->where('created_at', '>', function ($q) use ($user) {
                        $q->select('last_read_at')
                            ->from('conversation_participants')
                            ->whereColumn('conversation_id', 'conversations.id')
                            ->where('user_id', $user->id);
                    });
            }])
            ->orderByDesc(function ($query) {
                $query->select('created_at')
                    ->from('messages')
                    ->whereColumn('conversation_id', 'conversations.id')
                    ->orderByDesc('created_at')
                    ->limit(1);
            })
            ->get();

        return response()->json($conversations);
    }

    public function messages($conversationId)
    {
        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (!$conversation->participants->contains($user->id)) {
            return response()->json(['error' => 'No access to this conversation'], 403);
        }

        // Marcar mensajes como leídos
        $conversation->participants()->updateExistingPivot($user->id, [
            'last_read_at' => now()
        ]);

        event(new MessageRead($conversationId, $user->id));

        $messages = Message::where('conversation_id', $conversationId)
            ->with('user')
            ->whereDoesntHave('deletedByUsers', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            // ->whereDoesntHave('deletedByUsers') //si se quiere dejar de ver los mensajes eliminados por el usuario
            ->orderBy('created_at', 'asc')
            //limitar a los ultimos 50 mensajes
            //->take(50)
            ->get();
        //take last 50 messages
        $messages = $messages->slice(-50)->values();
        return response()->json($messages);
    }

    public function sendMessage(Request $request, $conversationId)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'type' => 'sometimes|in:text,image,video,file',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (!$conversation->participants->contains($user->id)) {
            return response()->json(['error' => 'No access to this conversation'], 403);
        }

        $message = Message::create([
            'conversation_id' => $conversationId,
            'user_id' => $user->id,
            'content' => $request->content,
            'type' => $request->type ?? 'text',
            'file_path' => $request->file_path
        ]);

        event(new MessageSent($message));

        // Eventos de notificación para cada usuario (excepto el remitente)
        foreach ($conversation->participants as $participant) {
            if ($participant->id !== auth()->id()) {
                broadcast(new NewMessageNotification($message, $participant->id));
            }
        }

        // Verificar que el evento se disparó
        Log::info('Evento MessageSent disparado para mensaje: ' . $message->id);

        return response()->json($message->load('user'), 201);
    }

    public function createConversation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'name' => 'required_if:is_group,true|string|max:255|nullable',
            'is_group' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $userIds = $request->user_ids;

        // Para chat individual, verificar si ya existe una conversación
        if (!$request->is_group && count($userIds) === 1) {
            $existingConversation = $user->conversations()
                ->where('is_group', false)
                ->whereHas('participants', function ($query) use ($userIds) {
                    $query->whereIn('user_id', $userIds);
                }, '=', count($userIds))
                ->first();

            if ($existingConversation) {
                return response()->json($existingConversation->load('participants'));
            }
        }

        DB::beginTransaction();

        try {
            //verificar si es grupo y tiene nombre, revisar si tiene miembros y solo actualizar los miembros
            if ($request->is_group && $request->name) {
                //verificar el grupo con el mismo nombre, revisar si tiene miembros y solo actualizar los miembros
                $existingGroup = Conversation::where('name', $request->name)
                    ->where('is_group', true)
                    ->first();
                if ($existingGroup) {
                    //agregar miembros si no están ya en el grupo
                    $existingMembers = $existingGroup->participants->pluck('id')->toArray();
                    $newMembers = array_diff($userIds, $existingMembers);
                    if (!empty($newMembers)) {
                        $existingGroup->participants()->attach($newMembers);
                    }
                    DB::commit();
                    return response()->json($existingGroup->load('participants'));
                }
                //crear nuevo grupo
                $conversation = Conversation::create([
                    'name' => $request->name,
                    'is_group' => true,
                    'created_by' => $user->id
                ]);
            } elseif ($request->is_group && !$request->name) {
                return response()->json(['error' => 'Group conversations require a name'], 422);
            } elseif (!$request->is_group && count($userIds) === 1) {
                $conversation = Conversation::create([
                    'is_group' => false,
                    'created_by' => $user->id
                ]);
                $conversation->participants()->attach($user->id);
            } else {
                return response()->json(['error' => 'Invalid conversation parameters'], 422);
            }

            DB::commit();

            return response()->json($conversation->load('participants'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create conversation'], 500);
        }
    }

    public function deleteMessage($messageId)
    {
        $user = Auth::user();
        $message = Message::findOrFail($messageId);

        if ($message->user_id !== $user->id) {
            return response()->json(['error' => 'Cannot delete other users messages'], 403);
        }

        $message->deletedByUsers()->attach($user->id);

        return response()->json(['message' => 'Message deleted successfully']);
    }
}
