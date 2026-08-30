<?php

namespace App\Models\Crp;

use Illuminate\Database\Eloquent\Model;

class CrpDocument extends Model
{
    protected $connection = 'tm2crp';

    protected $table = 'crp_document';

    protected $primaryKey = 'id_doc';

    public $timestamps = false;

    protected $fillable = ['submission_uuid', 'doc_type', 'file_path', 'uploaded_at'];

    protected $casts = ['uploaded_at' => 'datetime'];
}
