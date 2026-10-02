<?php

namespace Chat\Models;

// pair_key = "<id user kecil>:<id user besar>" (UNIQUE): satu percakapan per pasangan user. NULL = grup (belum dipakai).
class Conversation extends Model
{
    protected $table = 'sys_chat_conversations';

    const UPDATED_AT = null;

    protected $fillable = ['is_group', 'title', 'pair_key'];

    protected $casts = ['is_group' => 'boolean'];
}
