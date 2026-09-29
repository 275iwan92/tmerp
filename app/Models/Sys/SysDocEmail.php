<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

class SysDocEmail extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_doc_email';

    protected $primaryKey = 'id_email';

    protected $fillable = ['share_id', 'to_list', 'cc_list', 'bcc_list', 'subject', 'message', 'with_link', 'with_image', 'with_pdf', 'status', 'error', 'created_by', 'updated_by'];

    protected $casts = ['with_link' => 'boolean', 'with_image' => 'boolean', 'with_pdf' => 'boolean'];
}
