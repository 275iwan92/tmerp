<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdMasterCardFile extends Model
{
    public const KINDS = ['SOURCE' => 'Excel File', 'PHOTO' => 'Card Photo', 'PROOF' => 'Proof', 'CUT_PLAN' => 'Cutting Plan', 'ARTWORK' => 'Part Artwork'];

    protected $connection = 'tm2mst';

    protected $table = 'prod_master_card_file';

    protected $primaryKey = 'id_master_card_file';

    protected $guarded = ['id_master_card_file'];

    public function card(): BelongsTo
    {
        return $this->belongsTo(ProdMasterCard::class, 'master_card_id', 'id_master_card');
    }

    public function url(): string
    {
        return asset('storage/'.$this->path);
    }
}
