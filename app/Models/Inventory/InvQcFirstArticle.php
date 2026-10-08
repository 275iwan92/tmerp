<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

// Hasil first article di mesin untuk satu WO dan satu batch bahan.
class InvQcFirstArticle extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'inv_qc_first_article';

    protected $primaryKey = 'id_first_article';

    protected $guarded = ['id_first_article'];

    protected $casts = ['checked_at' => 'datetime'];

    public $timestamps = false;
}
