<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SysSupportTicket extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_support_ticket';

    protected $primaryKey = 'id_ticket';

    protected $fillable = [
        'ticket_no', 'created_by_user_id', 'screen_context', 'description',
        'status', 'resolution_note', 'resolved_by', 'resolved_at',
    ];

    protected $casts = ['resolved_at' => 'datetime'];

    // SPT + yymm + urut 4 digit per bulan (sama dengan Help Center v1).
    public static function nextNo(): string
    {
        $seq = static::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count() + 1;

        return 'SPT'.now()->format('ym').str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(SecUser::class, 'created_by_user_id', 'id_user');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SysSupportTicketAttachment::class, 'ticket_id', 'id_ticket');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SysSupportReply::class, 'ticket_id', 'id_ticket')->orderBy('created_at');
    }
}
