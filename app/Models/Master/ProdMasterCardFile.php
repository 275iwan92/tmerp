<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdMasterCardFile extends Model
{
    public const KINDS = ['SOURCE' => 'File Excel', 'PHOTO' => 'Foto kartu', 'PROOF' => 'Proof', 'CUT_PLAN' => 'Rencana potong', 'ARTWORK' => 'Artwork part'];

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
