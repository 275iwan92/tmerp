<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SysChatMessage extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_chat_messages';

    const CREATED_AT = 'sent_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'conversation_id',
        'sender',
        'body',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SysChatConversation::class, 'conversation_id');
    }
}
