<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;

class CndDocument extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2hrr';

    protected $table = 'cnd_document';

    protected $primaryKey = 'id_doc';

    protected $fillable = ['id_candidate', 'doc_type', 'file_path', 'uploaded_at'];

    protected $casts = ['uploaded_at' => 'datetime'];
}
