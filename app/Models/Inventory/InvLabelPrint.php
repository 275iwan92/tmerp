<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

// Riwayat cetak label (lokasi / item): siapa mencetak apa, kapan, berapa lembar.
class InvLabelPrint extends Model
{
    public const TYPES = ['LOCATION' => 'Location', 'ITEM' => 'Item'];

    protected $connection = 'tm2inv';

    protected $table = 'inv_label_print';

    protected $primaryKey = 'id_label_print';

    public $timestamps = false;

    protected $guarded = ['id_label_print'];

    protected $casts = ['printed_at' => 'datetime', 'qty' => 'float', 'copies' => 'integer'];
}
