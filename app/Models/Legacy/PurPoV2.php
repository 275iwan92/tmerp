<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

// Header PO paling lengkap (rincian pajak) di tm2old -- tapi baris/line-nya
// TIDAK PERNAH dimigrasi ke pur_po_detail_v2 (0 baris, dicek langsung ke DB).
// Baris line tetap diambil dari pur_po_detail (v1) via po_no=doc_no.
class PurPoV2 extends Model
{
    protected $connection = 'tm2old';

    protected $table = 'pur_po_v2';

    protected $primaryKey = 'doc_id';

    public $timestamps = false;
}
