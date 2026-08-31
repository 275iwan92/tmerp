<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Model baru 2026-08-30 -- tabel sys_support_replies SUDAH ADA di skema
// (dari awal sys_support_ticket dibangun) tapi belum pernah dipakai kode
// mana pun (tak ada model, tak ada UI tulis/baca). Instruksi user: gabung
// "Help & Support" (tiket) + Help Center (dokumentasi) jadi 1 tempat,
// dengan riwayat balasan developer/system analyst di tiket -- inilah yang
// mengisi tabel ini pertama kali.
class SysSupportReply extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_support_replies';

    protected $primaryKey = 'id_reply';

    public $timestamps = false;

    protected $fillable = ['ticket_id', 'author', 'body'];

    protected $casts = ['created_at' => 'datetime'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SysSupportTicket::class, 'ticket_id', 'id_ticket');
    }
}
