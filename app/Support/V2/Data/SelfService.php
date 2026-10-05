<?php

namespace App\Support\V2\Data;

use App\Models\Master\EmpBadgePhotoRequest;
use App\Models\Master\EmpCorrectionRequest;
use App\Models\Master\EmpDisciplinary;
use App\Models\Master\EmpDocument;
use App\Models\Master\EmpEducation;
use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpFamily;
use App\Models\Master\EmpFamilyRequest;
use App\Models\Master\EmpLeaveBalance;
use App\Models\Master\EmpLeaveRequest;
use App\Models\Master\EmpOutPermitRequest;
use App\Models\Master\EmpResignRequest;
use App\Models\Master\EmpShiftRoster;
use App\Models\Master\EmpShiftSwapRequest;
use App\Models\Master\SopDocument;
use App\Models\Sys\SecUser;
use App\Services\Hrm\EmployeeBankService;
use App\Services\Security\SecurityRecoveryService;
use App\Support\Branding\Brand;
use Carbon\Carbon;
use Devlib\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// Edit profile v2 (Profile > Edit profile > full screen): portal self-service karyawan sebagai layar DevLib.
// Logika & aturan = salinan apa adanya dari pages/self-service/index.blade.php (masteremployee_tier1 §5, tier2 §B.4-B.7,
// SOP cuti 002/RP/TM/VII/2026): data pribadi read-only, semua perubahan lewat pengajuan + approval HR; akun login
// (sec_user) dan pertanyaan pemulihan diubah langsung. Nilai field_name koreksi tetap teks v1 (dibaca layar review HR).
final class SelfService
{
    public const TABS = ['profile', 'jobdesc', 'sop', 'family', 'documents', 'leave', 'outing', 'swap', 'schedule', 'badge', 'security'];

    // ?tab= lama (v1) tetap diterima.
    private const LEGACY_TABS = ['profil' => 'profile', 'keluarga' => 'family', 'dokumen' => 'documents', 'cuti' => 'leave',
        'izinkeluar' => 'outing', 'tukarshift' => 'swap', 'jadwal' => 'schedule'];

    public const DOC_TYPES = ['KTP' => 'ID card (e-KTP)', 'NPWP' => 'Tax ID (NPWP)', 'SIM' => 'Driving licence (SIM C)', 'KK' => 'Family card (KK)', 'PASSPORT' => 'Passport', 'SKCK' => 'Police clearance (SKCK)'];

    public static function tab(?string $tab): string
    {
        $tab = self::LEGACY_TABS[$tab] ?? $tab;

        return in_array($tab, self::TABS, true) ? $tab : 'profile';
    }

    public static function employee(SecUser $u): ?EmpEmployee
    {
        return $u->employee_id
            ? EmpEmployee::with(['position.department', 'company', 'families', 'documents', 'educations', 'bankAccounts', 'bpjsKesehatan', 'bpjsKetenagakerjaan'])->find($u->employee_id)
            : null;
    }

    public static function balance(int $employeeId): EmpLeaveBalance
    {
        return EmpLeaveBalance::firstOrCreate(
            ['employee_id' => $employeeId, 'year' => (int) now()->year],
            ['entitlement_days' => 12, 'used_days' => 0, 'carried_over_days' => 0]
        );
    }

    public static function sopDocuments(EmpEmployee $e)
    {
        $departmentId = $e->position?->department_id;

        return SopDocument::with('department')->where('activate', true)
            ->where(fn ($q) => $q->whereNull('department_id')->when($departmentId, fn ($q2) => $q2->orWhere('department_id', $departmentId)))
            ->orderBy('title')->get();
    }

    public static function corrections(int $id)
    {
        return EmpCorrectionRequest::where('employee_id', $id)->orderByDesc('requested_at')->get();
    }

    public static function familyRequests(int $id)
    {
        return EmpFamilyRequest::where('employee_id', $id)->orderByDesc('requested_at')->get();
    }

    public static function leaveRequests(int $id)
    {
        return EmpLeaveRequest::where('employee_id', $id)->orderByDesc('requested_at')->get();
    }

    public static function outPermits(int $id)
    {
        return EmpOutPermitRequest::where('employee_id', $id)->orderByDesc('requested_at')->get();
    }

    public static function swapRequests(int $id)
    {
        return EmpShiftSwapRequest::with(['targetEmployee', 'requesterRoster.shift', 'targetRoster.shift'])
            ->where('requester_employee_id', $id)->orderByDesc('requested_at')->get();
    }

    public static function upcomingRoster(int $employeeId)
    {
        return EmpShiftRoster::query()->with(['shift', 'batch', 'machine'])
            ->where('employee_id', $employeeId)
            ->where('roster_date', '>=', now()->toDateString())
            ->whereHas('batch', fn ($q) => $q->where('status', 'CONFIRMED'))
            ->orderBy('roster_date')->get();
    }

    public static function swapColleagues(EmpEmployee $e)
    {
        if (! $e->position?->department) {
            return collect();
        }

        return EmpEmployee::query()
            ->where('employee_id', '!=', $e->employee_id)
            ->whereHas('position.department', fn ($q) => $q->where('division_id', $e->position->department->division_id))
            ->where('activate', true)->orderBy('full_name')->get();
    }

    public static function rosterLabel(EmpShiftRoster $r): string
    {
        return Locale::d($r->roster_date).' — '.($r->shift?->shift_name ?? '');
    }

    // Field yang boleh diajukan koreksi: field_name v1 => [label English, nilai sekarang]. Posisi hanya HR.
    public static function correctableFields(EmpEmployee $e): array
    {
        $bank = EmployeeBankService::primary($e->employee_id);

        return [
            'Nama Lengkap' => ['Full name', $e->full_name],
            'Tanggal Lahir' => ['Date of birth', Locale::d($e->birth_date)],
            'NIK KTP' => ['ID card no. (NIK)', $e->nik_ktp],
            'Agama' => ['Religion', $e->religion],
            'Telepon / WA' => ['Phone / WhatsApp', $e->phone],
            'Alamat KTP' => ['ID card address', $e->address_detail_ktp],
            'Alamat Domisili' => ['Residential address', $e->address_detail_domisili],
            'No. BPJS Kesehatan' => ['BPJS Health no.', $e->bpjsKesehatan?->no_kepesertaan],
            'No. BPJS Ketenagakerjaan' => ['BPJS Employment no.', $e->bpjsKetenagakerjaan?->no_kepesertaan],
            'Kontak Darurat' => ['Emergency contact', trim(($e->emergency_contact_name ?: '').($e->emergency_contact_relation ? ' ('.$e->emergency_contact_relation.')' : ''))],
            EmpCorrectionRequest::FIELD_BANK => ['Payroll bank account', $bank ? trim($bank->bank_name).' · '.$bank->account_no.' · a.n. '.$bank->account_holder_name : 'Cash'],
        ];
    }

    public static function primaryBank(int $employeeId)
    {
        return EmployeeBankService::primary($employeeId);
    }

    public static function bankOptions(): array
    {
        return EmployeeBankService::bankOptions();
    }

    private static function store(Request $r, string $key, int $employeeId, string $folder): ?string
    {
        return $r->hasFile($key) ? $r->file($key)->store("hrm/{$employeeId}/{$folder}", 'public') : null;
    }

    private static function fail(array $data, array $rules, array $names = []): ?string
    {
        $v = Validator::make($data, $rules, [], array_map('t', $names));

        return $v->fails() ? $v->errors()->first() : null;
    }

    // ============== Data Saya: koreksi & resign ==============
    public static function saveCorrection(EmpEmployee $e, Request $r): ?string
    {
        $fields = self::correctableFields($e);
        $field = (string) $r->input('field_name');
        if (! isset($fields[$field])) {
            return t('Choose the field to correct.');
        }
        $old = (string) $fields[$field][1];
        $new = (string) $r->input('new_value');

        if ($field === EmpCorrectionRequest::FIELD_BANK) {
            if ($err = self::fail($r->all(), [
                'bank_supplier_id' => ['required', 'integer', 'exists:tm2mst.sup_supplier,id_supplier'],
                'bank_account_no' => ['required', 'string', 'max:50'],
                'bank_holder' => ['required', 'string', 'max:150'],
                'reason' => ['nullable', 'string', 'max:255'],
            ], ['bank_supplier_id' => 'Bank', 'bank_account_no' => 'Account no.', 'bank_holder' => 'Account holder'])) {
                return $err;
            }
            $new = json_encode([
                'bank_supplier_id' => (int) $r->input('bank_supplier_id'),
                'account_no' => trim((string) $r->input('bank_account_no')),
                'account_holder_name' => trim((string) $r->input('bank_holder')),
            ]);
        }

        if ($err = self::fail(['new_value' => $new, 'reason' => $r->input('reason')], [
            'new_value' => ['required', 'string', 'max:255'], 'reason' => ['nullable', 'string', 'max:255'],
        ], ['new_value' => 'New value', 'reason' => 'Reason'])) {
            return $err;
        }

        EmpCorrectionRequest::create([
            'employee_id' => $e->employee_id,
            'field_name' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'reason' => $r->input('reason') ?: null,
            'requires_hr_approval' => true, // masteremployee_tier2.md B.4: semua koreksi lewat HR
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        return null;
    }

    public static function saveResign(EmpEmployee $e, Request $r): ?string
    {
        if ($err = self::fail($r->all(), [
            'effective_date' => ['required', 'date', 'after:today'], 'reason' => ['required', 'string', 'max:255'],
        ], ['effective_date' => 'Effective date', 'reason' => 'Reason'])) {
            return $err;
        }

        EmpResignRequest::create([
            'employee_id' => $e->employee_id,
            'effective_date' => $r->input('effective_date'),
            'reason' => $r->input('reason'),
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        return null;
    }

    // ============== Keluarga ==============
    public static function saveFamily(EmpEmployee $e, Request $r): ?string
    {
        $type = (string) $r->input('request_type'); // LAHIR | MENINGGAL | PASANGAN
        if (! in_array($type, ['LAHIR', 'MENINGGAL', 'PASANGAN'], true)) {
            return t('Unknown family request type.');
        }
        $rules = ['relation' => ['required', 'in:SUAMI,ISTRI,ANAK']];
        if ($type === 'MENINGGAL') {
            $rules['family_id'] = ['required', 'integer', 'exists:tm2mst.emp_family,family_id'];
            $rules['deceased_date'] = ['required', 'date'];
        } else {
            $rules['full_name'] = ['required', 'string', 'max:150'];
            $rules['birth_date'] = ['nullable', 'date'];
        }
        $in = $r->all();
        if ($type === 'MENINGGAL') {
            $in['relation'] = EmpFamily::find((int) $r->input('family_id'))?->relation ?? '';
        }
        if ($err = self::fail($in, $rules, ['relation' => 'Relation', 'family_id' => 'Family member', 'deceased_date' => 'Date of death', 'full_name' => 'Full name', 'birth_date' => 'Date of birth'])) {
            return $err;
        }

        if ($type === 'LAHIR' && $in['relation'] === 'ANAK'
            && EmpFamily::where('employee_id', $e->employee_id)->where('relation', 'ANAK')->count() >= 20) {
            return t('Maximum 20 children per employee.');
        }

        $familyId = $type === 'MENINGGAL' ? (int) $r->input('family_id') : null;
        $family = $familyId ? EmpFamily::find($familyId) : null;

        EmpFamilyRequest::create([
            'employee_id' => $e->employee_id,
            'request_type' => $type,
            'family_id' => $familyId,
            'relation' => $in['relation'],
            'full_name' => $type === 'MENINGGAL' ? $family?->full_name : $r->input('full_name'),
            'birth_date' => $type === 'MENINGGAL' ? null : ($r->input('birth_date') ?: null),
            'deceased_date' => $type === 'MENINGGAL' ? $r->input('deceased_date') : null,
            'attachment_path' => self::store($r, 'attachment', $e->employee_id, 'family'),
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        return null;
    }

    // ============== Dokumen & pendidikan ==============
    public static function saveDocument(EmpEmployee $e, Request $r): ?string
    {
        $type = (string) $r->input('document_type');
        if (! isset(self::DOC_TYPES[$type])) {
            return t('Choose the document type.');
        }
        if ($err = self::fail($r->all(), [
            'file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
            'document_no' => ['nullable', 'string', 'max:50'],
            'expiry_date' => ['nullable', 'date'],
        ], ['file' => 'File', 'document_no' => 'Document no.', 'expiry_date' => 'Valid until'])) {
            return $err;
        }

        $existing = EmpDocument::where('employee_id', $e->employee_id)->where('document_type', $type)->first();
        if ($existing && $existing->uploaded_by_role === 'HR') {
            return t('This document was uploaded and locked by HR — ask HR for a correction; it cannot be replaced here.');
        }

        EmpDocument::updateOrCreate(
            ['employee_id' => $e->employee_id, 'document_type' => $type],
            [
                'document_no' => $r->input('document_no') ?: null,
                'file_path' => self::store($r, 'file', $e->employee_id, 'documents'),
                'expiry_date' => $r->input('expiry_date') ?: null,
                'uploaded_by_role' => 'SELF',
                'activate' => true,
            ]
        );

        return null;
    }

    public static function saveEducation(EmpEmployee $e, Request $r): ?string
    {
        $in = $r->all();
        $in['cost'] = Locale::input($r->input('cost', '0')) ?? '0';
        if ($err = self::fail($in, [
            'activity_name' => ['required', 'string', 'max:200'],
            'activity_date' => ['required', 'date'],
            'cost' => ['required', 'numeric', 'min:0'],
            'funded_by' => ['required', 'in:PERUSAHAAN,PRIBADI,SHARING'],
            'organizer' => ['nullable', 'string', 'max:150'],
        ], ['activity_name' => 'Training / certification', 'activity_date' => 'Date', 'cost' => 'Cost', 'funded_by' => 'Funded by', 'organizer' => 'Organizer'])) {
            return $err;
        }

        EmpEducation::create([
            'employee_id' => $e->employee_id,
            'education_type' => 'PELATIHAN',
            'activity_name' => $in['activity_name'],
            'organizer' => $in['organizer'] ?: null,
            'activity_date' => $in['activity_date'],
            'cost' => $in['cost'],
            'funded_by' => $in['funded_by'],
            'uploaded_by_role' => 'SELF',
        ]);

        return null;
    }

    // ============== Cuti / sakit / izin (engine SOP 002/RP/TM/VII/2026) ==============
    public static function izinLocked(int $employeeId): bool
    {
        return self::balance($employeeId)->remainingDays() > 0;
    }

    // Dihitung dari BULAN pengajuan (date_from), bukan bulan kalender saat ini.
    private static function izinCountForMonth(int $employeeId, Carbon $ref): int
    {
        return EmpLeaveRequest::where('employee_id', $employeeId)->where('leave_type', 'IZIN')
            ->whereBetween('date_from', [$ref->copy()->startOfMonth(), $ref->copy()->endOfMonth()])->count();
    }

    // Izin ke-4 dst dalam bulan yang sama DIANGGAP ALPHA: pengajuan diblok, kejadiannya dicatat ke emp_disciplinary
    // (dedup per employee + tanggal).
    private static function recordAutoAlpha(int $employeeId, Carbon $date, int $attempt): void
    {
        $exists = EmpDisciplinary::where('employee_id', $employeeId)->where('type', 'ALPHA')->where('incident_date', $date->toDateString())->exists();
        if ($exists) {
            return;
        }
        EmpDisciplinary::create([
            'employee_id' => $employeeId,
            'type' => 'ALPHA',
            'incident_date' => $date->toDateString(),
            'reason' => "Percobaan pengajuan Izin ke-{$attempt} bulan {$date->format('F Y')} — melebihi batas 3x/bulan sesuai SOP Perizinan 002/RP/TM/VII/2026, otomatis tercatat Alpha.",
            'issued_by' => 'SYSTEM',
        ]);
    }

    public static function submitLeave(EmpEmployee $e, Request $r): ?string
    {
        $id = $e->employee_id;
        $type = (string) $r->input('leave_type');
        if (! in_array($type, ['CUTI', 'SAKIT', 'IZIN', 'CUTI_KHUSUS'], true)) {
            return t('Choose the request type.');
        }
        $from = $r->input('date_from') ? Carbon::parse($r->input('date_from')) : null;
        $to = $r->input('date_to') ? Carbon::parse($r->input('date_to')) : null;
        if (! $from || ! $to || $to->lt($from)) {
            return t('The dates are not valid.');
        }

        // Hari kerja = tanpa Minggu; hari libur nasional belum tervalidasi di sini (DELTA, sama dengan v1).
        $totalDays = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if (! $d->isSunday()) {
                $totalDays++;
            }
        }

        $payload = [
            'employee_id' => $id, 'leave_type' => $type, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString(),
            'total_days' => $totalDays, 'reason' => $r->input('reason') ?: null, 'status' => 'PENDING', 'requested_at' => now(),
        ];

        if ($type === 'CUTI') {
            if ($from->lt(now()->addDays(7)->startOfDay())) {
                return t('Annual leave must be requested at least 7 days ahead (H+7).');
            }
            if ($totalDays > 3) {
                return t('Maximum 3 working days per annual leave request.');
            }
            if (! $r->hasFile('att_surat_cuti')) {
                return t('The leave request letter must be uploaded (administrative requirement).');
            }
            $payload['attachment_surat_cuti'] = self::store($r, 'att_surat_cuti', $id, 'leave');
            $payload['is_paid'] = true;
            $payload['deducts_leave_balance'] = true;
        } elseif ($type === 'SAKIT') {
            if ($r->boolean('sakit_via_jkn')) {
                if (! $r->hasFile('att_jkn_screenshot')) {
                    return t('The JKN history screenshot must be uploaded.');
                }
                $payload['attachment_jkn_screenshot'] = self::store($r, 'att_jkn_screenshot', $id, 'leave');
            } else {
                if (! $r->hasFile('att_surat_dokter') || ! $r->hasFile('att_kwitansi_resep')) {
                    return t("Both the doctor's note AND the receipt / prescription must be uploaded.");
                }
                $payload['attachment_surat_dokter'] = self::store($r, 'att_surat_dokter', $id, 'leave');
                $payload['attachment_kwitansi_resep'] = self::store($r, 'att_kwitansi_resep', $id, 'leave');
            }
            $payload['sakit_lapor_jam'] = $r->input('sakit_lapor_jam') ?: null;
            $payload['is_paid'] = true;
            $payload['deducts_leave_balance'] = false;
        } elseif ($type === 'IZIN') {
            if (self::izinLocked($id)) {
                return t('You still have annual leave left — unpaid leave can only be requested when this year\'s balance is 0.');
            }
            if ($totalDays > 3) {
                return t('Maximum 3 consecutive days per unpaid leave request.');
            }
            $count = self::izinCountForMonth($id, $from);
            if ($count >= 3) {
                self::recordAutoAlpha($id, $from, $count + 1);

                return t('Already 3 unpaid leave requests this month — this attempt is recorded automatically as Alpha in your discipline record.');
            }
            $sifat = (string) $r->input('izin_sifat', 'terjadwal');
            if ($sifat === 'terjadwal' && $from->lt(now()->addDays(7)->startOfDay())) {
                return t('Planned unpaid leave must be requested at least 7 days ahead (H-7).');
            }
            if ($sifat === 'mendadak') {
                $payload['izin_category'] = $r->input('izin_category');
                if ($r->input('izin_category') === 'INSTANSI_RESMI') {
                    $payload['izin_relasi_pemohon'] = $r->input('izin_relasi_pemohon');
                    $payload['izin_instansi_asal'] = $r->input('izin_instansi_asal');
                }
                if (! $r->hasFile('att_generic')) {
                    return t('A proof / letter attachment is required for sudden unpaid leave.');
                }
                $payload['attachment_path'] = self::store($r, 'att_generic', $id, 'leave');
            }
            $payload['is_paid'] = false;
            $payload['deducts_leave_balance'] = false;
        } else { // CUTI_KHUSUS
            if (! $r->hasFile('att_generic')) {
                return t('A proof attachment (marriage certificate, birth certificate, ...) is required.');
            }
            $payload['khusus_event_type'] = $r->input('khusus_event_type');
            $payload['attachment_path'] = self::store($r, 'att_generic', $id, 'leave');
            $payload['is_paid'] = true;
            $payload['deducts_leave_balance'] = false;
        }

        EmpLeaveRequest::create($payload);

        return null;
    }

    // ============== Izin keluar ==============
    public static function submitOutPermit(EmpEmployee $e, Request $r): ?string
    {
        if ($err = self::fail($r->all(), [
            'permit_date' => ['required', 'date'], 'out_time' => ['required', 'date_format:H:i'],
            'return_time' => ['nullable', 'date_format:H:i'], 'reason' => ['required', 'string', 'max:255'],
        ], ['permit_date' => 'Date', 'out_time' => 'Time out', 'return_time' => 'Time back', 'reason' => 'Purpose'])) {
            return $err;
        }

        EmpOutPermitRequest::create([
            'employee_id' => $e->employee_id,
            'permit_date' => $r->input('permit_date'),
            'planned_out_time' => $r->input('out_time'),
            'planned_return_time' => $r->input('return_time') ?: null,
            'reason' => $r->input('reason'),
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        return null;
    }

    // ============== Tukar shift ==============
    public static function submitSwap(EmpEmployee $e, Request $r): ?string
    {
        if ($err = self::fail($r->all(), [
            'requester_roster_id' => ['required', 'integer', 'exists:tm2mst.emp_shift_roster,roster_id'],
            'target_employee_id' => ['required', 'integer'],
            'target_roster_id' => ['required', 'integer', 'exists:tm2mst.emp_shift_roster,roster_id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], ['requester_roster_id' => 'My shift', 'target_employee_id' => 'Colleague', 'target_roster_id' => "Colleague's shift", 'reason' => 'Reason'])) {
            return $err;
        }
        $target = EmpShiftRoster::find((int) $r->input('target_roster_id'));
        if (! $target || (int) $target->employee_id !== (int) $r->input('target_employee_id')) {
            return t("Choose the colleague's shift that belongs to the chosen colleague.");
        }

        $mine = EmpShiftRoster::find((int) $r->input('requester_roster_id'));
        $onTime = $mine && now()->lt($mine->roster_date->copy()->subDay());

        EmpShiftSwapRequest::create([
            'requester_employee_id' => $e->employee_id,
            'requester_roster_id' => (int) $r->input('requester_roster_id'),
            'target_employee_id' => (int) $r->input('target_employee_id'),
            'target_roster_id' => (int) $r->input('target_roster_id'),
            'reason' => $r->input('reason') ?: null,
            'submitted_on_time' => $onTime,
            'partner_status' => 'PENDING',
            'ppic_status' => 'PENDING',
            'requested_at' => now(),
        ]);

        return null;
    }

    // ============== Kartu pengenal ==============
    public static function saveBadgePhoto(EmpEmployee $e, Request $r): ?string
    {
        if ($err = self::fail($r->all(), ['photo' => ['required', 'image', 'max:5120']], ['photo' => 'Photo'])) {
            return $err;
        }
        [$width, $height] = @getimagesize($r->file('photo')->getRealPath()) ?: [null, null];

        EmpBadgePhotoRequest::create([
            'employee_id' => $e->employee_id,
            'photo_path' => self::store($r, 'photo', $e->employee_id, 'badge'),
            'width_px' => $width,
            'height_px' => $height,
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        return null;
    }

    // Isi objek form khusus x-mp::hrm.self-service (Edit profile): satu array untuk kesebelas bagian + pop-up pengajuan.
    // 'linked' false = akun belum terhubung ke pegawai, hanya bagian Security yang ada. Semua teks pilihan sudah diterjemahkan
    // dan semua nilai sudah diformat di sini; objeknya hanya menampilkan. Hanya membaca.
    public static function desk(SecUser $u, string $home): array
    {
        $e = self::employee($u);
        $tab = self::tab(request('tab'));
        $sec = self::securityDefaults($u);
        $tabs = $e ? [
            'profile' => ['My Data', 'fa-user'], 'jobdesc' => ['Job Description', 'fa-clipboard-list'], 'sop' => ['SOP', 'fa-book'],
            'family' => ['Family & Status', 'fa-people-roof'], 'documents' => ['Documents & Education', 'fa-file-lines'],
            'leave' => ['Leave / Sick / Permit', 'fa-calendar-days'], 'outing' => ['Out Permit', 'fa-door-open'], 'swap' => ['Shift Swap', 'fa-arrows-rotate'],
            'schedule' => ['My Schedule', 'fa-calendar'], 'badge' => ['ID Card', 'fa-id-badge'], 'security' => ['Security', 'fa-shield-halved'],
        ] : ['security' => ['Security', 'fa-shield-halved']];
        $desk = [
            'home' => $home, 'linked' => (bool) $e, 'tabs' => $tabs, 'tab' => isset($tabs[$tab]) ? $tab : 'security',
            'flash' => session('v2_flash'), 'error' => ($errors = session('errors')) && $errors->any() ? $errors->first() : null,
            'security' => [
                'accountAction' => route('v2.profile.account'), 'passwordAction' => route('v2.profile.password'), 'recoveryAction' => route('v2.profile.security'),
                'emailUrl' => route('v2.account.email'), 'name' => old('name', $u->name), 'email' => old('email', $u->email), 'phone' => old('phone', $u->phone),
                'verified' => $u->email_verified ? t('Verified') : t('Not verified'),
                'setup' => $sec['setup_at'] ? t('Last set').' '.Locale::dt($sec['setup_at']) : null,
                'birthDate' => old('birth_date', $sec['birth_date']), 'motherName' => old('mother_name'), 'recoveryPhone' => old('phone', $sec['phone']),
                'questionId' => old('question_id', $sec['question_id']), 'questions' => $sec['questions'], 'answer' => old('answer'),
            ],
        ];
        if (! $e) {
            return $desk;
        }
        $id = $e->employee_id;
        $balance = self::balance($id);
        $fields = self::correctableFields($e);
        $bank = self::primaryBank($id);
        $roster = self::upcomingRoster($id);
        $sops = self::sopDocuments($e);
        $izinLocked = self::izinLocked($id);
        $pick = fn (array $keys) => array_map(fn (string $k) => [$fields[$k][0], $fields[$k][1] ?: '—'], $keys);
        $targetId = (int) old('target_employee_id', request('colleague'));
        $targetRoster = $targetId ? self::upcomingRoster($targetId) : collect();
        $rosterOptions = fn ($list) => $list->mapWithKeys(fn ($r) => [$r->roster_id => self::rosterLabel($r)])->all();

        return $desk + [
            'profile' => [
                'title' => $e->full_name.' · '.$e->employee_code,
                'description' => ($e->position?->position_name ?? '—').' · '.($e->position?->department?->department_name ?? '—'),
                'stats' => [
                    ['Leave Left', (string) $balance->remainingDays().' '.t('days'), 'success', t('this year')],
                    ['Work Status', $e->work_status === 'WORKING' ? t('Active') : (string) $e->work_status, 'info', null],
                    ['Pending Requests', (string) (self::corrections($id)->where('status', 'PENDING')->count() + self::leaveRequests($id)->where('status', 'PENDING')->count()), 'warn', t('corrections + leave')],
                ],
                'main' => $pick(['Nama Lengkap', 'Tanggal Lahir', 'NIK KTP', 'Agama', 'Telepon / WA']), 'position' => $e->position?->position_name ?: '—',
                'address' => $pick(['Alamat KTP', 'Alamat Domisili', 'No. BPJS Kesehatan', 'No. BPJS Ketenagakerjaan', 'Kontak Darurat']),
                'corrections' => self::corrections($id)->map(fn ($c) => ['field' => t($fields[$c->field_name][0] ?? $c->field_name), 'old' => $c->old_value, 'new' => $c->new_value, 'date' => Locale::d($c->requested_at), 'status' => t((string) $c->status)])->all(),
            ],
            'jobdesc' => ['position' => $e->position?->position_name ?? '—', 'text' => $e->position?->job_description ?: null],
            'sop' => [
                'count' => $sops->count().' '.t('documents'),
                'rows' => $sops->map(fn ($d) => ['code' => $d->sop_code, 'title' => $d->title, 'rev' => $d->revision, 'dept' => $d->department_id ? $d->department?->department_name : t('All departments'), 'since' => Locale::d($d->effective_date) ?? '—'])->all(),
                'docs' => $sops->map(fn ($d) => ['id' => $d->sop_id, 'code' => $d->sop_code, 'title' => $d->title,
                    'line' => $d->sop_code.' · Rev '.$d->revision.($d->effective_date ? ' · '.t('effective').' '.Locale::d($d->effective_date) : ''), 'purpose' => $d->purpose ?: null, 'content' => $d->content ?: '—'])->values()->all(),
            ],
            'family' => [
                'rows' => $e->families->map(fn ($f) => ['rel' => $f->relation, 'name' => $f->full_name, 'birth' => Locale::d($f->birth_date) ?? '—', 'status' => $f->is_deceased ? t('Deceased').' — '.Locale::d($f->deceased_date) : t('Dependant')])->all(),
                'bank' => $bank ? trim($bank->bank_name).' · '.$bank->account_no.' · a.n. '.$bank->account_holder_name : t('Cash'),
                'requests' => self::familyRequests($id)->map(fn ($f) => ['type' => t(['LAHIR' => 'Birth', 'MENINGGAL' => 'Death', 'PASANGAN' => 'Spouse'][$f->request_type] ?? $f->request_type), 'name' => $f->full_name, 'date' => Locale::d($f->requested_at), 'status' => t((string) $f->status)])->all(),
                'alive' => $e->families->where('is_deceased', false)->mapWithKeys(fn ($f) => [$f->family_id => $f->full_name.' ('.$f->relation.')'])->all(),
                'action' => route('v2.profile.family'),
            ],
            'documents' => [
                'rows' => collect(self::DOC_TYPES)->map(function ($label, $type) use ($e) {
                    $d = $e->documents->firstWhere('document_type', $type);

                    return ['doc' => t($label), 'no' => $d ? ($d->document_no ?: t('Uploaded')) : t('No file yet'), 'valid' => $d?->expiry_date ? Locale::d($d->expiry_date) : '—', 'lock' => $d ? ($d->uploaded_by_role === 'HR' ? t('HR (locked)') : t('Me')) : '—'];
                })->values()->all(),
                'educations' => $e->educations->map(fn ($ed) => ['act' => $ed->activity_name, 'date' => Locale::d($ed->activity_date), 'cost' => (float) $ed->cost, 'fund' => t(['PRIBADI' => 'Personal', 'PERUSAHAAN' => 'Company', 'SHARING' => 'Shared'][$ed->funded_by] ?? $ed->funded_by)])->all(),
                'types' => array_map('t', self::DOC_TYPES), 'type' => old('document_type'), 'action' => route('v2.profile.document'), 'educationAction' => route('v2.profile.education'),
                'funds' => ['PRIBADI' => t('Personal'), 'PERUSAHAAN' => t('Company'), 'SHARING' => t('Shared')],
            ],
            'leave' => [
                'title' => t('Annual Leave Balance').' — '.now()->year, 'balance' => [(string) $balance->entitlement_days, (string) $balance->used_days, (string) $balance->remainingDays()],
                'action' => route('v2.profile.leave'), 'locked' => $izinLocked,
                'types' => ['CUTI' => t('Annual Leave (paid)'), 'SAKIT' => t('Sick'),
                    'IZIN' => t('Unpaid Leave').($izinLocked ? ' — '.t('locked, leave left').' '.$balance->remainingDays().' '.t('days') : ''),
                    'CUTI_KHUSUS' => t('Special Leave (wedding, bereavement, ...)')],
                'natures' => ['terjadwal' => t('Planned (min. H-7)'), 'mendadak' => t('Sudden (emergency / misfortune)')],
                'categories' => ['MUSIBAH' => t('Family misfortune / accident / disaster'), 'INSTANSI_RESMI' => t('Official government institution matter'), 'KEPENGURUSAN_MUSIBAH' => t('Bereavement / misfortune arrangements')],
                'relations' => ['DIRI_SENDIRI' => t('Myself'), 'SUAMI_ISTRI' => t('Spouse'), 'ANAK_KANDUNG' => t('Own child'), 'ORANG_TUA_KANDUNG' => t('Own parent'), 'MERTUA' => t('Parent-in-law')],
                'institutions' => ['BPJS' => 'BPJS', 'RUMAH_SAKIT' => t('Hospital'), 'SEKOLAH' => t('School'), 'KEPOLISIAN' => t('Police'), 'INSTANSI_PEMERINTAH_LAINNYA' => t('Other government institution')],
                'events' => ['MENIKAH' => t('Employee wedding'), 'MENIKAHKAN_KHITAN_ANAK' => t('Child wedding / circumcision'), 'ISTRI_MELAHIRKAN' => t('Wife giving birth / miscarriage'), 'KELUARGA_MENINGGAL' => t('Death of immediate family'), 'IBADAH' => t('Religious pilgrimage')],
                'old' => ['leave_type' => old('leave_type', 'CUTI'), 'date_from' => old('date_from'), 'date_to' => old('date_to'), 'sakit_lapor_jam' => old('sakit_lapor_jam'), 'sakit_via_jkn' => (bool) old('sakit_via_jkn'),
                    'izin_sifat' => old('izin_sifat', 'terjadwal'), 'izin_category' => old('izin_category', 'MUSIBAH'), 'izin_relasi_pemohon' => old('izin_relasi_pemohon', 'DIRI_SENDIRI'),
                    'izin_instansi_asal' => old('izin_instansi_asal', 'BPJS'), 'khusus_event_type' => old('khusus_event_type', 'MENIKAH'), 'reason' => old('reason')],
                'rows' => self::leaveRequests($id)->map(fn ($lr) => ['type' => t(['CUTI' => 'Annual leave', 'SAKIT' => 'Sick', 'IZIN' => 'Unpaid leave', 'CUTI_KHUSUS' => 'Special leave'][$lr->leave_type] ?? $lr->leave_type), 'dates' => Locale::d($lr->date_from).' – '.Locale::d($lr->date_to), 'days' => (int) $lr->total_days, 'pay' => $lr->is_paid ? t('Paid') : t('Unpaid'), 'status' => t((string) $lr->status)])->all(),
            ],
            'outing' => [
                'action' => route('v2.profile.outing'), 'old' => ['permit_date' => old('permit_date'), 'out_time' => old('out_time'), 'return_time' => old('return_time'), 'reason' => old('reason')],
                'rows' => self::outPermits($id)->map(fn ($op) => ['date' => Locale::d($op->permit_date), 'out' => substr((string) $op->planned_out_time, 0, 5), 'back' => $op->planned_return_time ? substr((string) $op->planned_return_time, 0, 5) : '—', 'status' => t((string) $op->status)])->all(),
            ],
            'swap' => [
                'action' => route('v2.profile.swap'), 'colleagues' => self::swapColleagues($e)->pluck('full_name', 'employee_id')->all(), 'targetId' => $targetId,
                'mine' => $rosterOptions($roster), 'theirs' => $rosterOptions($targetRoster),
                'theirsHint' => $targetId ? t('Choose the partner\'s shift...') : t('Choose a partner first'),
                'old' => ['requester_roster_id' => old('requester_roster_id'), 'target_roster_id' => old('target_roster_id'), 'reason' => old('reason')],
                'rows' => self::swapRequests($id)->map(fn ($s) => ['mine' => Locale::d($s->requesterRoster?->roster_date) ?? '—', 'with' => $s->targetEmployee?->full_name, 'partner' => t((string) $s->partner_status), 'ppic' => t((string) $s->ppic_status)])->all(),
            ],
            'schedule' => $roster->map(fn ($s) => ['date' => Locale::d($s->roster_date), 'shift' => $s->shift?->shift_name, 'ot' => $s->is_overtime ? t('Yes') : '—', 'station' => $s->machine?->machine_name ?? '—'])->all(),
            'badge' => ['name' => (string) $e->full_name, 'position' => $e->position?->position_name, 'company' => Brand::name(), 'code' => $e->employee_code,
                'department' => $e->position?->department?->department_name ?: '—', 'action' => route('v2.profile.badge')],
            'requests' => [
                'correctionAction' => route('v2.profile.correction'), 'resignAction' => route('v2.profile.resign'),
                'fields' => collect($fields)->except(EmpCorrectionRequest::FIELD_BANK)->map(fn ($f) => t($f[0]).' — '.($f[1] ?: '—'))->all(), 'field' => old('field_name'),
                'newValue' => old('new_value'), 'reason' => old('reason'), 'effectiveDate' => old('effective_date'),
                'bankField' => EmpCorrectionRequest::FIELD_BANK, 'bankCurrent' => $fields[EmpCorrectionRequest::FIELD_BANK][1], 'banks' => self::bankOptions(),
                'bankId' => old('bank_supplier_id', $bank?->bank_supplier_id), 'bankNo' => old('bank_account_no', $bank?->account_no), 'bankHolder' => old('bank_holder', $bank?->account_holder_name),
            ],
        ];
    }

    // ============== Security: pertanyaan pemulihan akun (sama dengan layar Setup Keamanan saat login) ==============
    public static function securityDefaults(SecUser $u): array
    {
        $s = app(SecurityRecoveryService::class);

        return [
            'birth_date' => $s->resolveBirthDateFromEmployee($u) ?: ($u->birth_date?->toDateString() ?? ''),
            'phone' => $s->resolvePhoneFromEmployee($u) ?: (string) $u->phone,
            'question_id' => $u->security_question_id,
            'questions' => $s->activeQuestions()->pluck('question_text', 'id_question')->all(),
            'setup_at' => $u->security_setup_at,
        ];
    }

    public static function saveSecurity(SecUser $u, Request $r): ?string
    {
        if ($err = self::fail($r->all(), [
            'birth_date' => ['required', 'date'],
            'mother_name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'question_id' => ['required', 'integer', 'exists:tm2sys.sec_recovery_question,id_question'],
            'answer' => ['required', 'string', 'min:2', 'max:150'],
        ], ['birth_date' => 'Date of birth', 'mother_name' => "Mother's maiden name", 'phone' => 'Registered phone', 'question_id' => 'Security question', 'answer' => 'Answer'])) {
            return $err;
        }
        $s = app(SecurityRecoveryService::class);
        if ($u->phone !== $r->input('phone')) {
            $u->phone = $r->input('phone');
            $u->save();
        }
        $s->saveSetup($u, $r->input('birth_date'), $r->input('mother_name'), (int) $r->input('question_id'), $r->input('answer'));
        $s->syncIdentityToEmployee($u, $r->input('birth_date'), $r->input('phone'));

        return null;
    }
}
