<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class DocRmprefix3 extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2mst';

    protected $table = 'doc_rmprefix3';

    protected $primaryKey = 'idprefix';

    protected $fillable = ['category1', 'category2', 'category3', 'prefix1', 'prefix2', 'prefix3', 'note'];
}
