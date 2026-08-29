<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

class SysSupportTicketAttachment extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2sys';

    protected $table = 'sys_support_ticket_attachment';

    protected $primaryKey = 'id_attachment';

    protected $fillable = ['ticket_id', 'file_path', 'uploaded_at'];

    protected $casts = ['uploaded_at' => 'datetime'];
}
