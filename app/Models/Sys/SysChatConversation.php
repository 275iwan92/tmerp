<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SysChatConversation extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_chat_conversations';

    const UPDATED_AT = null;

    protected $fillable = [
        'is_group',
        'title',
    ];

    protected $casts = [
        'is_group' => 'boolean',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(SysChatMessage::class, 'conversation_id')->orderBy('sent_at');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(SysChatParticipant::class, 'conversation_id');
    }
}
