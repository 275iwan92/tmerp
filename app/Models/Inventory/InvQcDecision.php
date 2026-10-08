<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

// Satu keputusan mutu atas satu batch (sampling di dok, first article, usage decision, deviasi). Riwayat lulus-gagal supplier dibaca dari sini.
class InvQcDecision extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'inv_qc_decision';

    protected $primaryKey = 'id_decision';

    protected $guarded = ['id_decision'];

    protected $casts = ['decided_at' => 'datetime'];

    public $timestamps = false;
}
