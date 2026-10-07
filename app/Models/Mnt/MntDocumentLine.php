<?php

namespace App\Models\Mnt;

use Illuminate\Database\Eloquent\Model;

class MntDocumentLine extends Model
{
    protected $connection = 'tm2mnt';

    protected $table = 'mnt_document_line';

    protected $primaryKey = 'id_document_line';

    protected $guarded = ['id_document_line'];
}
