<?php

namespace Chat\Models;

// sender = Directory::key() pengirim. Lampiran: file di disk config('chat.disk'), di sini hanya alamat + nama asli + ukuran.
class Message extends Model
{
    protected $table = 'sys_chat_messages';

    const CREATED_AT = 'sent_at';

    const UPDATED_AT = null;

    protected $fillable = ['conversation_id', 'sender', 'body', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size'];

    protected $casts = ['sent_at' => 'datetime', 'attachment_size' => 'integer'];
}
