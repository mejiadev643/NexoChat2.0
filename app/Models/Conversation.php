<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = ['name', 'is_group', 'avatar', 'created_by'];

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withTimestamps()
            ->withPivot('last_read_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->latest();
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function unreadMessagesCount($userId): int
    {
        return $this->messages()
            ->where('user_id', '!=', $userId)
            ->whereDoesntHave('deletedByUsers', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->where('created_at', '>', function ($query) use ($userId) {
                $query->select('last_read_at')
                    ->from('conversation_participants')
                    ->where('conversation_id', $this->id)
                    ->where('user_id', $userId);
            })
            ->count();
    }

    public function scopeWithUnreadCount($query, $userId)
    {
        return $query->withCount(['messages as unread_count' => function($query) use ($userId) {
            $query->where('user_id', '!=', $userId)
                ->whereDoesntHave('deletedByUsers', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->where('created_at', '>', function ($q) use ($userId) {
                    $q->select('last_read_at')
                        ->from('conversation_participants')
                        ->whereColumn('conversation_id', 'conversations.id')
                        ->where('user_id', $userId);
                });
        }]);
    }
}
