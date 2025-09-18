<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class Message extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'content', 'type', 'file_path', 'is_read'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deletedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'deleted_messages')->withTimestamps();
    }

    public function isDeletedBy($userId): bool
    {
        return $this->deletedByUsers()->where('user_id', $userId)->exists();
    }

}
