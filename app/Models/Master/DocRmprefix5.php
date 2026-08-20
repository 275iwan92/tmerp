<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class DocRmprefix5 extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2mst';

    protected $table = 'doc_rmprefix5';

    protected $primaryKey = 'idprefix';

    protected $fillable = ['category1', 'category2', 'category3', 'category4', 'category5', 'prefix1', 'prefix2', 'prefix3', 'prefix4', 'prefix5', 'note'];
}
