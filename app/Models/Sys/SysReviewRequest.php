<?php

/*
 * REMARK
 * Tujuan       : Antrean permintaan "Process with Claude": satu baris terbuka (REQUESTED) per cakupan (MD / STORY).
 * Sumber hulu  : Tombol di header layar MD Review dan Old Stories Review.
 * Input        : ReviewRequestService::request() (jumlah keputusan, siapa, kapan); ReviewRequestService::done() saat Claude selesai.
 * Output       : Baris yang dibaca php artisan review:requests dan ditampilkan sebagai status di layar.
 * Dipakai oleh : App\Services\Sys\ReviewRequestService, layar v2.screens.setup-review.
 * Status       : Aktif (ddt*).
 */

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

class SysReviewRequest extends Model
{
    public const REQUESTED = 'REQUESTED';

    public const DONE = 'DONE';

    protected $connection = 'tm2sys';

    protected $table = 'sys_review_request';

    protected $primaryKey = 'id_review_request';

    protected $guarded = [];

    protected $casts = ['requested_at' => 'datetime', 'handled_at' => 'datetime'];
}
