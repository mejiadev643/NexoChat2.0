<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessageNotification implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $recipientId;

    public function __construct(Message $message, $recipientId)
    {
        $this->message = $message->load('user', 'conversation');
        $this->recipientId = $recipientId;// ID del usuario que debe recibir la notificación
    }

    public function broadcastOn(): Channel
    {
        // Canal privado para el usuario específico
        return new PrivateChannel('user.' . $this->recipientId);
    }

    public function broadcastAs(): string
    {
        return 'message.new';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'conversation_id' => $this->message->conversation_id,
            'unread_count' => $this->getUnreadCount($this->recipientId)
        ];
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
}
