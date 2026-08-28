<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

// Tipe reject/waste (F15) sebagai DATA -- PPIC/QA kelola sendiri lewat layar
// Kelola Tipe Scrap, bukan enum di kode. Data awal (migration
// 2026_08_28_000013) diambil dari kategori umum print-industry MIS
// (Systrade/PrintVis/eProductivity), disusul daftar riil user.
class PrdScrapType extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'prd_scrap_type';

    protected $primaryKey = 'type_id';

    protected $fillable = [
        'category', 'type_code', 'type_name', 'activate', 'sort_order', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];

    public function scopeReject($query)
    {
        return $query->where('category', 'REJECT');
    }

    public function scopeWaste($query)
    {
        return $query->where('category', 'WASTE');
    }

    public function scopeActive($query)
    {
        return $query->where('activate', true);
    }
}
