<?php

/*
 * REMARK
 * Tujuan       : Satu baris = satu berkas .md yang ditinjau Jo (usulan Claude + keputusan Jo).
 * Sumber hulu  : docs/ringkas/md_review_tabel.md lewat php artisan review:import.
 * Input        : decision (KEEP / CONDENSE / DISCARD) dari layar MD Review; decided_by / decided_at diisi MdReviewLookup::saved.
 * Output       : Antrean keputusan untuk php artisan review:requests; title + summary ringkasan berkas (2026-10-14).
 * Dipakai oleh : App\Support\V2\Grid\Setup\MdReviewLookup, App\Services\Sys\ReviewRequestService.
 * Status       : Aktif (ddt*).
 */

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

class SysMdReview extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_md_review';

    protected $primaryKey = 'id_md_review';

    protected $guarded = [];
}
