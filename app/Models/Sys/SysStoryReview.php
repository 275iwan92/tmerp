<?php

/*
 * REMARK
 * Tujuan       : Satu baris = satu pesan panjang Jo (cerita lama tentang ERP) yang ditinjau: adopsi, nanti, atau abaikan.
 * Sumber hulu  : docs/ringkas/cerita_lama_penuh.md (kutipan riwayat sesi Claude Code) lewat php artisan review:import.
 * Input        : decision (ADOPT / LATER / IGNORE) dari layar Old Stories Review; decided_by / decided_at diisi StoryReviewLookup::saved.
 * Output       : Antrean keputusan untuk php artisan review:requests.
 * Dipakai oleh : App\Support\V2\Grid\Setup\StoryReviewLookup, App\Services\Sys\ReviewRequestService.
 * Status       : Aktif (ddt*).
 */

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

class SysStoryReview extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_story_review';

    protected $primaryKey = 'id_story_review';

    protected $guarded = [];
}
