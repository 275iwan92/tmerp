<?php

/*
 * REMARK
 * Tujuan       : Route layar HRM v2 yang menggantikan halaman v1: keputusan HR atas presensi GPS di luar radius (hrm-gps-form) atas pengajuan koreksi data pegawai (hrm-correction-form) dan atas pengajuan cuti / sakit / izin (hrm-leave-form), koreksi presensi harian (hrm-attendance-form, kartu Recalculate di hrm-attendance-upload) register SOP / WI perusahaan (hrm-sop-form) lowongan kerja (hrm-rcr-vacancy-form) undangan Talent Pool (rcr-talent-pool-form) hasil Psikotes / Interview (hrm-rcr-assessment-form) provisioning pegawai baru (hrm-rcr-provision-form) jadwal interview (hrm-rcr-interview-form) permohonan tenaga kerja (hrm-rcr-requisition-form) penawaran kerja (hrm-rcr-offer-form) dan master gaji pegawai (hrm-salary-form).
 * Sumber hulu  : App\Services\Hrm\GpsApprovalService, CorrectionRequestService dan LeaveRequestService, AttendanceOverrideService dan Maintenance\MntDocumentService dan Recruitment\RecruitmentVacancyService dan Recruitment\RecruitmentInviteService dan Recruitment\AssessmentResultService dan Recruitment\HireProvisionService dan Recruitment\InterviewScheduleService dan Recruitment\RequisitionService dan Recruitment\OfferService dan Payroll\EmployeeSalarySettingService (dan layanan HRM berikutnya).
 * Input        : POST form (id, action approve atau reject, _reason dari dialog Reject).
 * Output       : Redirect ke form (id) dengan flash v2_flash / errors.
 * Dipakai oleh : routes/web.php (dimuat di baris yang sama dengan v2_finance.php supaya nomor baris web.php yang dikutip kartu TG tidak bergeser); layar hrm-gps-form, hrm-correction-form, hrm-leave-form, hrm-attendance-form, hrm-attendance-upload, hrm-sop-form, hrm-rcr-vacancy-form, rcr-talent-pool-form, hrm-rcr-assessment-form, hrm-rcr-provision-form, hrm-rcr-interview-form, hrm-rcr-requisition-form, hrm-rcr-offer-form dan hrm-salary-form.
 * Status       : Aktif. Dibuat 2026-10-19 (T7 HRM, Jo): menggantikan halaman v1 pages::hrm.gps-approval, correction-requests, leave-requests, attendance-daily, sop-documents, recruitment.vacancy, recruitment.talent-pool, recruitment.assessment-result, recruitment.hire-provision, recruitment.interview-schedule dan recruitment.requisition (list, entry) dan recruitment.offer (list, entry) dan payroll-employee-salary.
 */

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppInterviewSchedule;
use App\Models\Hrr\AppOffer;
use App\Models\Hrr\CndCandidate;
use App\Models\Hrr\RcrRequisition;
use App\Models\Hrr\VacVacancy;
use App\Models\Master\AttDailyStatus;
use App\Models\Master\AttMobileGpsLog;
use App\Models\Master\EmpCorrectionRequest;
use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpLeaveRequest;
use App\Models\Mnt\MntDocument;
use App\Services\Hrm\AttendanceOverrideService;
use App\Services\Hrm\CorrectionRequestService;
use App\Services\Hrm\GpsApprovalService;
use App\Services\Hrm\LeaveRequestService;
use App\Services\Maintenance\MntDocumentService;
use App\Services\Payroll\EmployeeSalarySettingService;
use App\Services\Payroll\PayrollAccess;
use App\Services\Recruitment\AssessmentResultService;
use App\Services\Recruitment\HireProvisionService;
use App\Services\Recruitment\InterviewScheduleService;
use App\Services\Recruitment\OfferService;
use App\Services\Recruitment\RecruitmentInviteService;
use App\Services\Recruitment\RecruitmentVacancyService;
use App\Services\Recruitment\RequisitionService;
use Devlib\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

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

// Talent Pool: undang kandidat yang ada ke lowongan terbuka (lamaran baru APPLIED, tahap 1). Aturan = RecruitmentInviteService (dipindah dari v1 pages::recruitment.talent-pool).
Route::prefix('v2/hrm/talent-pool')->name('v2.hrm.talentpool.')->middleware('module:HRM')->group(function () {
    Route::post('/invite', function (Request $r, RecruitmentInviteService $svc) {
        $c = CndCandidate::findOrFail((int) $r->input('id'));
        $a = $svc->invite($c, $r->input('id_vacancy'));

        return redirect(route('v2.screen', 'rcr-talent-pool-form').'?id='.$c->id_candidate)->with('v2_flash', tf('Invited to :vacancy.', ['vacancy' => $a->vacancy->title]));
    })->name('invite');
});

// Hasil Psikotes / Interview: Save = catat hasil lamaran yang menunggu (tahap dari server); hasil yang ada baca-saja. Aturan = AssessmentResultService (dipindah dari v1 pages::recruitment.assessment-result).
Route::prefix('v2/hrm/assessment')->name('v2.hrm.assessment.')->middleware('module:HRM')->group(function () {
    Route::post('/save', function (Request $r, AssessmentResultService $svc) {
        $in = $r->only(['id_application', 'recommendation', 'notes']) + ['score' => filled($r->input('score')) ? Locale::input($r->input('score')) : null];
        $res = $svc->record($in, (int) Auth::id());

        return redirect(route('v2.screen', 'hrm-rcr-assessment-form').'?id='.$res->id_result)->with('v2_flash', t('Assessment result saved.'));
    })->name('save');
});

// Provisioning pegawai baru dari lamaran dengan penawaran ACCEPTED: pegawai + kontrak + dokumen + login dalam satu transaksi, hanya user berhak payroll. Aturan = HireProvisionService (dipindah dari v1 pages::recruitment.hire-provision).
Route::prefix('v2/hrm/provision')->name('v2.hrm.provision.')->middleware('module:HRM')->group(function () {
    Route::post('/save', function (Request $r, HireProvisionService $svc) {
        $a = AppApplication::findOrFail((int) $r->input('id'));
        abort_unless(PayrollAccess::canProcess(Auth::user()), 403, t('Only users with payroll rights can provision a new employee.'));
        [$e, $cred] = $svc->provision($a, (int) $r->input('position_id'), Auth::user());

        return redirect(route('v2.screen', 'hrm-employee-form').'?id='.$e->employee_id)->with('v2_flash', t('Employee provisioned. Complete the missing data below.'))
            ->with('v2_login_card', $cred ? $cred + ['code' => $e->employee_code] : null);
    })->name('save');
});

// Jadwal interview: Save = jadwalkan lamaran yang menunggu (tahap dari server, interviewer diberi notifikasi), action done / cancel hanya dari SCHEDULED. Aturan = InterviewScheduleService (dipindah dari v1 pages::recruitment.interview-schedule).
Route::prefix('v2/hrm/interview')->name('v2.hrm.interview.')->middleware('module:HRM')->group(function () {
    Route::post('/save', function (Request $r, InterviewScheduleService $svc) {
        if ($r->filled('id')) {
            $s = AppInterviewSchedule::findOrFail((int) $r->input('id'));
            $action = (string) $r->input('action');
            abort_unless(in_array($action, ['done', 'cancel'], true), 422);
            $action === 'done' ? $svc->markDone($s) : $svc->cancel($s);
            $msg = t($action === 'done' ? 'Interview marked as done.' : 'Interview schedule cancelled.');
        } else {
            $s = $svc->schedule($r->only(['id_application', 'date', 'time', 'interviewer_id', 'location']));
            $msg = t('Interview scheduled and the interviewer was notified.');
        }

        return redirect(route('v2.screen', 'hrm-rcr-interview-form').'?id='.$s->id_schedule)->with('v2_flash', $msg);
    })->name('save');
});

// Permohonan tenaga kerja: Save draft, Submit for Approval, Delete draft, Approve / Reject (alasan reject wajib) oleh approver yang ditugaskan. Aturan = RequisitionService + RecruitmentApprovalService (dipindah dari v1 pages::recruitment.requisition).
Route::prefix('v2/hrm/requisition')->name('v2.hrm.requisition.')->middleware('module:HRM')->group(function () {
    Route::post('/save', function (Request $r, RequisitionService $svc) {
        $user = Auth::user();
        $req = $r->filled('id') ? RcrRequisition::findOrFail((int) $r->input('id')) : null;
        $action = (string) $r->input('action', 'save');
        $form = route('v2.screen', 'hrm-rcr-requisition-form');
        abort_unless(in_array($action, ['save', 'submit', 'delete', 'approve', 'reject'], true), 422);
        abort_if(! $req && ! in_array($action, ['save', 'submit'], true), 422);

        if ($action === 'delete') {
            $svc->delete($req, $user);

            return redirect(route('v2.screen', 'hrm-rcr-requisition'))->with('v2_flash', t('Draft requisition deleted.'));
        }
        if ($action === 'approve' || $action === 'reject') {
            $svc->decide($req, $user, $action === 'approve' ? 'APPROVED' : 'REJECTED', (string) $r->input('_reason', ''));
            $msg = t($action === 'approve' ? 'Requisition approved.' : 'Requisition rejected.');
        } else {
            $req = $svc->save($req, $r->only(['id_track', 'department_id', 'position_title', 'headcount', 'qualification', 'reason']), $user);
            if ($action === 'submit') {
                try {
                    $svc->submit($req, $user);
                } catch (ValidationException $e) {
                    return redirect($form.'?id='.$req->id_requisition)->withErrors($e->errors())->with('v2_flash', t('Draft saved.'));
                }
            }
            $msg = t($action === 'submit' ? 'Requisition submitted for approval.' : 'Draft saved.');
        }

        return redirect($form.'?id='.$req->id_requisition)->with('v2_flash', $msg);
    })->name('save');
});

// Penawaran kerja per lamaran: Save draft, Submit for Approval, Approve / Reject (approver yang ditugaskan), Send, Candidate Accepted / Declined, Revise. Aturan = OfferService + RecruitmentApprovalService (dipindah dari v1 pages::recruitment.offer).
Route::prefix('v2/hrm/offer')->name('v2.hrm.offer.')->middleware('module:HRM')->group(function () {
    Route::post('/save', function (Request $r, OfferService $svc) {
        $user = Auth::user();
        $app = AppApplication::findOrFail((int) $r->input('application'));
        $o = $r->filled('id') ? AppOffer::where('id_application', $app->id_application)->findOrFail((int) $r->input('id')) : null;
        $action = (string) $r->input('action', 'save');
        abort_unless(in_array($action, ['save', 'submit', 'approve', 'reject', 'send', 'accepted', 'declined', 'revise'], true), 422);
        abort_if(! $o && ! in_array($action, ['save', 'submit'], true), 422);

        if (in_array($action, ['save', 'submit'], true)) {
            $in = $r->only(['employment_type', 'start_date']) + ['proposed_salary' => filled($r->input('proposed_salary')) ? Locale::input($r->input('proposed_salary')) : null];
            $o = $svc->save($app, $o, $in, $user);
            if ($action === 'submit') {
                try {
                    $svc->submit($o);
                } catch (ValidationException $e) {
                    return redirect(route('v2.screen', 'hrm-rcr-offer-form').'?application='.$app->id_application)->withErrors($e->errors())->with('v2_flash', t('Draft saved.'));
                }
            }
            $msg = t($action === 'submit' ? 'Offer submitted for approval.' : 'Draft saved.');
        } else {
            match ($action) {
                'approve' => $svc->decide($o, $user, 'APPROVED'),
                'reject' => $svc->decide($o, $user, 'REJECTED'),
                'send' => $svc->send($o),
                'accepted' => $svc->accepted($o),
                'declined' => $svc->declined($o),
                'revise' => $svc->revise($o, $user),
            };
            $msg = t(['approve' => 'Offer approved.', 'reject' => 'Offer rejected.', 'send' => 'Offer marked as sent to the candidate.', 'accepted' => 'Candidate accepted the offer.', 'declined' => 'Candidate declined the offer.', 'revise' => 'New offer round started.'][$action]);
        }

        return redirect(route('v2.screen', 'hrm-rcr-offer-form').'?application='.$app->id_application)->with('v2_flash', $msg);
    })->name('save');
});

// Master gaji dan pajak pegawai: Save = versi baru (versi lama ditutup), hanya user berhak payroll atas pegawai itu. Aturan = EmployeeSalarySettingService (dipindah dari v1 pages::hrm.payroll-employee-salary).
Route::prefix('v2/hrm/salary')->name('v2.hrm.salary.')->middleware(['module:HRM', 'payroll:process'])->group(function () {
    Route::post('/save', function (Request $r, EmployeeSalarySettingService $svc) {
        $e = EmpEmployee::findOrFail((int) $r->input('id'));
        $num = fn (string $k) => filled($r->input($k)) ? Locale::input($r->input($k)) : null;
        $in = ['ptkp_status' => $r->input('ptkp_status'), 'bpjs_active' => $r->boolean('bpjs_active'), 'pph21_active' => $r->boolean('pph21_active')];
        foreach (['basic_salary_monthly', 'tj_keahlian_monthly', 'tj_loyalitas_monthly', 'tj_prestasi_monthly', 'bonus_pagi_amount', 'bonus_hadir_amount'] as $k) {
            $in[$k] = $num($k);
        }
        $saved = $svc->save($e, $in, Auth::user());

        return redirect(route('v2.screen', 'hrm-salary-form').'?id='.$e->employee_id)->with('v2_flash', t($saved ? 'Salary and tax setting saved as a new version.' : 'No changes to save.'));
    })->name('save');
});
