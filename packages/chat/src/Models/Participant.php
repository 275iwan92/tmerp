<?php

namespace Chat\Models;

// user_id = Directory::key() peserta. last_read_message_id = pesan terakhir yang sudah dibaca peserta ini.
class Participant extends Model
{
    protected $table = 'sys_chat_participants';

    protected $primaryKey = 'id_participant';

    public $timestamps = false;

    protected $fillable = ['conversation_id', 'user_id', 'last_read_at', 'last_read_message_id'];

    protected $casts = ['last_read_at' => 'datetime'];
}
