<?php

/*
 * REMARK
 * Tujuan       : Route layar HRM v2 yang menggantikan halaman v1: keputusan HR atas presensi GPS di luar radius (hrm-gps-form) dan atas pengajuan koreksi data pegawai (hrm-correction-form).
 * Sumber hulu  : App\Services\Hrm\GpsApprovalService dan CorrectionRequestService (dan layanan HRM berikutnya).
 * Input        : POST form (id, action approve atau reject, _reason dari dialog Reject).
 * Output       : Redirect ke form (id) dengan flash v2_flash / errors.
 * Dipakai oleh : routes/web.php (dimuat di baris yang sama dengan v2_finance.php supaya nomor baris web.php yang dikutip kartu TG tidak bergeser); layar hrm-gps-form dan hrm-correction-form.
 * Status       : Aktif. Dibuat 2026-10-19 (T7 HRM, Jo): menggantikan halaman v1 pages::hrm.gps-approval dan pages::hrm.correction-requests.
 */

use App\Models\Master\AttMobileGpsLog;
use App\Models\Master\EmpCorrectionRequest;
use App\Services\Hrm\CorrectionRequestService;
use App\Services\Hrm\GpsApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// GPS approval: Approve (menulis presensi) atau Reject (alasan wajib). Aturan = GpsApprovalService (dipindah dari v1 pages::hrm.gps-approval).
Route::prefix('v2/hrm/gps')->name('v2.hrm.gps.')->middleware('module:HRM')->group(function () {
    Route::post('/decide', function (Request $r, GpsApprovalService $svc) {
        $g = AttMobileGpsLog::findOrFail((int) $r->input('id'));
        $action = (string) $r->input('action');
        $user = Auth::user();

        if ($action === 'approve') {
            $svc->approve($g, $user);
            $msg = t('Attendance approved and recorded.');
        } elseif ($action === 'reject') {
            $svc->reject($g, (string) $r->input('_reason', ''), $user);
            $msg = t('GPS attendance rejected.');
        } else {
            abort(422);
        }

        return redirect(route('v2.screen', 'hrm-gps-form').'?id='.$g->id_gps_log)->with('v2_flash', $msg);
    })->name('decide');
});

// Koreksi data pegawai: Approve (menerapkan nilai baru) atau Reject (alasan wajib, notifikasi ke pegawai). Aturan = CorrectionRequestService (dipindah dari v1 pages::hrm.correction-requests).
Route::prefix('v2/hrm/correction')->name('v2.hrm.correction.')->middleware('module:HRM')->group(function () {
    Route::post('/decide', function (Request $r, CorrectionRequestService $svc) {
        $c = EmpCorrectionRequest::findOrFail((int) $r->input('id'));
        $action = (string) $r->input('action');
        $user = Auth::user();

        if ($action === 'approve') {
            $svc->approve($c, $user);
            $msg = t('Correction approved.');
        } elseif ($action === 'reject') {
            $svc->reject($c, (string) $r->input('_reason', ''), $user);
            $msg = t('Correction rejected and the employee was notified.');
        } else {
            abort(422);
        }

        return redirect(route('v2.screen', 'hrm-correction-form').'?id='.$c->request_id)->with('v2_flash', $msg);
    })->name('decide');
});
