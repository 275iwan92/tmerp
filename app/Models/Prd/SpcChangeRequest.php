<?php

/*
 * REMARK
 * Tujuan       : Satu baris = satu permintaan perubahan spesifikasi sesudah CO Confirm (kelas 1 spesifikasi customer, 2 substitusi bahan, 3 routing) yang menunggu persetujuan OTP.
 * Sumber hulu  : App\Services\Production\SpecChange\SpecChangeService (membuat), SpecOtpService (menyetujui).
 * Input        : -
 * Output       : payload sebelum / sesudah, alasan, dampak biaya, status PENDING | APPLIED | REJECTED, pemohon dan penyetuju.
 * Dipakai oleh : SpecChangeService, SpecOtpService, layar Form WO dan antrean persetujuan.
 * Status       : Aktif.
 */

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpcChangeRequest extends Model
{
    public const CLASS_CUSTOMER_SPEC = 1;

    public const CLASS_MATERIAL = 2;

    public const CLASS_ROUTING = 3;

    protected $connection = 'tm2prd';

    protected $table = 'spc_change_request';

    protected $primaryKey = 'id_request';

    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'cost_impact' => 'array', 'save_backup' => 'boolean', 'requested_at' => 'datetime', 'decided_at' => 'datetime'];

    public function otps(): HasMany
    {
        return $this->hasMany(SpcOtp::class, 'request_id', 'id_request');
    }
}
