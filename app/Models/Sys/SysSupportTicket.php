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
