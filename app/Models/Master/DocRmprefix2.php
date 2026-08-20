<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class DocRmprefix2 extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2mst';

    protected $table = 'doc_rmprefix2';

    protected $primaryKey = 'idprefix';

    protected $fillable = ['category1', 'category2', 'prefix1', 'prefix2', 'note'];
}
