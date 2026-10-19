<?php

/*
 * REMARK
 * Tujuan       : Route layar HRM v2 yang menggantikan halaman v1: keputusan HR atas presensi GPS di luar radius (hrm-gps-form) atas pengajuan koreksi data pegawai (hrm-correction-form) dan atas pengajuan cuti / sakit / izin (hrm-leave-form), koreksi presensi harian (hrm-attendance-form, kartu Recalculate di hrm-attendance-upload) register SOP / WI perusahaan (hrm-sop-form) dan lowongan kerja (hrm-rcr-vacancy-form).
 * Sumber hulu  : App\Services\Hrm\GpsApprovalService, CorrectionRequestService dan LeaveRequestService, AttendanceOverrideService dan Maintenance\MntDocumentService dan Recruitment\RecruitmentVacancyService (dan layanan HRM berikutnya).
 * Input        : POST form (id, action approve atau reject, _reason dari dialog Reject).
 * Output       : Redirect ke form (id) dengan flash v2_flash / errors.
 * Dipakai oleh : routes/web.php (dimuat di baris yang sama dengan v2_finance.php supaya nomor baris web.php yang dikutip kartu TG tidak bergeser); layar hrm-gps-form, hrm-correction-form, hrm-leave-form, hrm-attendance-form, hrm-attendance-upload, hrm-sop-form dan hrm-rcr-vacancy-form.
 * Status       : Aktif. Dibuat 2026-10-19 (T7 HRM, Jo): menggantikan halaman v1 pages::hrm.gps-approval, correction-requests, leave-requests, attendance-daily, sop-documents dan recruitment.vacancy.
 */

use App\Models\Hrr\VacVacancy;
use App\Models\Master\AttDailyStatus;
use App\Models\Master\AttMobileGpsLog;
use App\Models\Master\EmpCorrectionRequest;
use App\Models\Master\EmpLeaveRequest;
use App\Models\Mnt\MntDocument;
use App\Services\Hrm\AttendanceOverrideService;
use App\Services\Hrm\CorrectionRequestService;
use App\Services\Hrm\GpsApprovalService;
use App\Services\Hrm\LeaveRequestService;
use App\Services\Maintenance\MntDocumentService;
use App\Services\Recruitment\RecruitmentVacancyService;
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

// Cuti / sakit / izin: Approve (menambah used_days bila memotong saldo) atau Reject (alasan wajib); notifikasi ke pegawai. Aturan = LeaveRequestService (dipindah dari v1 pages::hrm.leave-requests).
Route::prefix('v2/hrm/leave')->name('v2.hrm.leave.')->middleware('module:HRM')->group(function () {
    Route::post('/decide', function (Request $r, LeaveRequestService $svc) {
        $l = EmpLeaveRequest::findOrFail((int) $r->input('id'));
        $action = (string) $r->input('action');
        $user = Auth::user();

        if ($action === 'approve') {
            $svc->approve($l, $user);
            $msg = t('Request approved.');
        } elseif ($action === 'reject') {
            $svc->reject($l, (string) $r->input('_reason', ''), $user);
            $msg = t('Request rejected and the employee was notified.');
        } else {
            abort(422);
        }

        return redirect(route('v2.screen', 'hrm-leave-form').'?id='.$l->request_id)->with('v2_flash', $msg);
    })->name('decide');
});

// Presensi harian: Save = override status (alasan audit wajib), action recalculate = hitung ulang hari itu; recalculate range = kartu di hrm-attendance-upload. Aturan = AttendanceOverrideService (dipindah dari v1 pages::hrm.attendance-daily).
Route::prefix('v2/hrm/attendance')->name('v2.hrm.attendance.')->middleware('module:HRM')->group(function () {
    Route::post('/save', function (Request $r, AttendanceOverrideService $svc) {
        $d = AttDailyStatus::findOrFail((int) $r->input('id'));
        if ((string) $r->input('action') === 'recalculate') {
            $svc->recalculateDay($d);
            $msg = t('Attendance of this day was recalculated.');
        } else {
            $svc->override($d, (string) $r->input('status_code'), (string) $r->input('override_reason'), Auth::user());
            $msg = t('Status overridden.');
        }

        return redirect(route('v2.screen', 'hrm-attendance-form').'?id='.$d->id_daily)->with('v2_flash', $msg);
    })->name('save');

    Route::post('/recalculate', function (Request $r, AttendanceOverrideService $svc) {
        $res = $svc->recalculateRange((string) $r->input('from'), (string) $r->input('to'));

        return redirect(route('v2.screen', 'hrm-attendance-upload'))->with('v2_flash', tf('Attendance was recalculated for :days employee days (:kept manual overrides kept).', ['days' => $res['days'], 'kept' => $res['kept']]));
    })->name('recalculate');
});

// SOP / WI perusahaan: Save, Approve, Back to Draft, Activate / Deactivate; hanya HR Officer (sama dengan layar Maintenance). Aturan = MntDocumentService (diekstrak dari route v2.mnt.document.save dan v1 pages::hrm.sop-documents).
Route::prefix('v2/hrm/sop')->name('v2.hrm.sop.')->middleware('module:HRM')->group(function () {
    Route::post('/save', function (Request $r, MntDocumentService $svc) {
        $user = Auth::user();
        abort_unless($user->isHrOfficer(), 403, t('Only the HR Officer can change SOP and Work Instruction documents.'));
        $m = $r->filled('id') ? MntDocument::company()->findOrFail((int) $r->input('id')) : null;
        $action = (string) $r->input('action', 'save');

        if ($m && $action === 'approve') {
            $svc->approve($m, $user);
            $msg = t('Document approved.');
        } elseif ($m && $action === 'to_draft') {
            $svc->backToDraft($m, $user);
            $msg = t('Document is a draft again (new revision).');
        } elseif ($m && in_array($action, ['activate', 'deactivate'], true)) {
            $svc->setActive($m, $action === 'activate', $user);
            $msg = t($action === 'activate' ? 'Document activated.' : 'Document deactivated.');
        } else {
            $m = $svc->saveCompany($m, $r->except(['id', 'action', 'lines']), (array) $r->input('lines', []), $user);
            $msg = t('Document saved.');
        }

        return redirect(route('v2.screen', 'hrm-sop-form').'?id='.$m->id_document)->with('v2_flash', $msg);
    })->name('save');
});

// Lowongan kerja: Save = terbitkan dari requisition APPROVED, action close = tutup lowongan. Aturan = RecruitmentVacancyService (dipindah dari v1 pages::recruitment.vacancy).
Route::prefix('v2/hrm/vacancy')->name('v2.hrm.vacancy.')->middleware('module:HRM')->group(function () {
    Route::post('/save', function (Request $r, RecruitmentVacancyService $svc) {
        if ($r->filled('id')) {
            $v = VacVacancy::findOrFail((int) $r->input('id'));
            abort_unless((string) $r->input('action') === 'close', 422);
            $svc->close($v);
            $msg = t('Vacancy closed.');
        } else {
            $v = $svc->publish($r->only(['id_requisition', 'channel', 'title', 'description']));
            $msg = tf(':code published.', ['code' => $v->vacancy_code]);
        }

        return redirect(route('v2.screen', 'hrm-rcr-vacancy-form').'?id='.$v->id_vacancy)->with('v2_flash', $msg);
    })->name('save');
});
