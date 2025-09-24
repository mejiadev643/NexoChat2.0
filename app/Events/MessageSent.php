<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message->load('user', 'conversation');
        $this->message->unread_count = $this->getUnreadCount($message->conversation_id, $message->user_id);

        // ✅ LOG para verificar que el evento se dispara
        \Log::info('🚀 Evento MessageSent disparado', [
            'message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'user_id' => $message->user_id,
            'channel' => 'conversation.' . $message->conversation_id
        ]);
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('conversation.' . $this->message->conversation_id);
    }
    protected function getUnreadCount($userId): int
    {
        return Message::where('conversation_id', $this->message->conversation_id)
            ->where('user_id', '!=', $userId)
            ->whereDoesntHave('deletedByUsers', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->where('created_at', '>', function ($query) use ($userId) {
                $query->select('last_read_at')
                    ->from('conversation_participants')
                    ->where('conversation_id', $this->message->conversation_id)
                    ->where('user_id', $userId);
            })
            ->count();
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message->toArray(),
            'debug' => 'Evento recibido correctamente'
        ];
    }
}
