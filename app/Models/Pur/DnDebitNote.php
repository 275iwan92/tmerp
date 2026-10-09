<?php

namespace App\Models\Pur;

use Illuminate\Database\Eloquent\Model;

// Draft debit note ke pemasok atas kurang kirim yang ditolak / lewat jendela (ShortageFollowUp::debitNote).
class DnDebitNote extends Model
{
    protected $connection = 'tm2pur';

    protected $table = 'dn_debit_note';

    protected $primaryKey = 'dn_id';

    protected $guarded = ['dn_id'];

    protected $casts = ['i_doc_date' => 'date'];
}
