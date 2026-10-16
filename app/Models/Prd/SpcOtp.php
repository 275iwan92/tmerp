<?php

/*
 * REMARK
 * Tujuan       : Kode OTP sekali pakai untuk menyetujui satu permintaan perubahan spesifikasi; hanya hash yang disimpan.
 * Sumber hulu  : App\Services\Production\SpecChange\SpecOtpService.
 * Input        : -
 * Output       : code_hash, pemberi, kedaluwarsa, jumlah percobaan salah, pemakai.
 * Dipakai oleh : SpecOtpService.
 * Status       : Aktif.
 */

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

class SpcOtp extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'spc_otp';

    protected $primaryKey = 'id_otp';

    protected $guarded = [];

    protected $casts = ['issued_at' => 'datetime', 'expires_at' => 'datetime', 'used_at' => 'datetime'];
}
