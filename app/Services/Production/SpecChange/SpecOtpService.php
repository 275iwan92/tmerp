<?php

/*
 * REMARK
 * Tujuan       : Persetujuan perubahan spesifikasi lewat OTP: OPM, GM, atau Director memberi satu kode untuk satu permintaan; pemohon mengetikkannya (Jo 2026-10-16).
 * Sumber hulu  : tm2prd.spc_otp, spc_change_request; konstanta SPEC_CHANGE_APPROVER_LEVEL (4) dan SPEC_CHANGE_OTP_MINUTES (10).
 * Input        : permintaan + penyetuju (issue), permintaan + kode + pemakai (redeem).
 * Output       : kode 6 digit sekali tampil (issue); OTP terpakai (redeem). Kegagalan = DomainException berpesan jelas.
 * Dipakai oleh : SpecChangeService::approveWithOtp, layar antrean persetujuan.
 * Status       : Aktif. Hanya hash (HMAC kode + id permintaan) yang disimpan; kedaluwarsa, sekali pakai, 5 percobaan salah mengunci kode, pemberi bukan pemohon.
 */

namespace App\Services\Production\SpecChange;

use App\Models\Prd\SpcChangeRequest;
use App\Models\Prd\SpcOtp;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysConstant;
use DomainException;

final class SpecOtpService
{
    private const MAX_ATTEMPTS = 5;

    public static function canApprove(SecUser $user): bool
    {
        return $user->maxMgmtLevel() >= (int) SysConstant::number('SPEC_CHANGE_APPROVER_LEVEL', 4);
    }

    // Kode untuk satu permintaan; kode lama yang belum terpakai dibatalkan. Dikembalikan sekali saja, tidak bisa dibaca lagi.
    public static function issue(SpcChangeRequest $request, SecUser $approver): string
    {
        if ($request->status !== 'PENDING') {
            throw new DomainException(t('This request is no longer waiting for approval.'));
        }
        if (! self::canApprove($approver)) {
            throw new DomainException(t('Only an Operational Manager, General Manager or Director can give an OTP.'));
        }
        if ($approver->login === $request->requested_by) {
            throw new DomainException(t('You cannot approve your own request.'));
        }
        SpcOtp::query()->where('request_id', $request->id_request)->whereNull('used_at')->update(['expires_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        SpcOtp::query()->create(['request_id' => $request->id_request, 'code_hash' => self::hash($code, $request), 'issued_by' => $approver->login,
            'issued_at' => now(), 'expires_at' => now()->addMinutes((int) SysConstant::number('SPEC_CHANGE_OTP_MINUTES', 10))]);

        return $code;
    }

    // Menukar kode dengan persetujuan: mengembalikan OTP yang terpakai (issued_by = penyetuju).
    public static function redeem(SpcChangeRequest $request, string $code, SecUser $by): SpcOtp
    {
        $otp = SpcOtp::query()->where('request_id', $request->id_request)->whereNull('used_at')->where('expires_at', '>', now())->orderByDesc('id_otp')->first();
        if (! $otp) {
            throw new DomainException(t('There is no valid OTP for this request. Ask an approver for a new one.'));
        }
        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            throw new DomainException(t('This OTP is locked after too many wrong tries. Ask an approver for a new one.'));
        }
        $otp->increment('attempts');
        if (! hash_equals($otp->code_hash, self::hash(trim($code), $request))) {
            throw new DomainException(t('The OTP is not correct.'));
        }
        $otp->forceFill(['used_by' => $by->login, 'used_at' => now()])->save();

        return $otp;
    }

    private static function hash(string $code, SpcChangeRequest $request): string
    {
        return hash_hmac('sha256', $code.'|'.$request->id_request, (string) config('app.key'));
    }
}
