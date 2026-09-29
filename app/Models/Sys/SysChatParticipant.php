<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SysChatParticipant extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_chat_participants';

    protected $primaryKey = 'id_participant';

    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'last_read_at',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SysChatConversation::class, 'conversation_id');
    }
}
