<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

// Jawaban QA atas satu komplain customer (tm2sdi.crm_complaint, relasi logis lintas-DB): keputusan, akar masalah, tindakan
// perbaikan / pencegahan, dan nasib barang yang kembali. Confirm = jawaban final, terbaca Sales di dokumen komplainnya.
class QcComplaintReview extends Model
{
    public const VERDICTS = ['ACCEPTED' => 'Accepted (our fault)', 'PARTIAL' => 'Partly accepted', 'REJECTED' => 'Not accepted'];

    public const DISPOSITIONS = ['REWORK' => 'Rework', 'SCRAP' => 'Scrap', 'RESTOCK' => 'Back to stock', 'NONE' => 'No goods returned'];

    protected $connection = 'tm2prd';

    protected $table = 'qc_complaint_review';

    protected $primaryKey = 'id_complaint_review';

    protected $guarded = ['id_complaint_review'];

    protected $casts = ['stat_confirm' => 'boolean', 'reviewed_at' => 'datetime'];
}
