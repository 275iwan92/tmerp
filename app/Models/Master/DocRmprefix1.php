<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class DocRmprefix1 extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2mst';

    protected $table = 'doc_rmprefix1';

    protected $primaryKey = 'idprefix';

    protected $fillable = ['category1', 'prefix1', 'note', 'stock_class', 'default_coa_account_id'];
}
