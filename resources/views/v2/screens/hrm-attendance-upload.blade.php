{{--
 REMARK
 Tujuan       : Mengunggah file absensi mesin finger, pratinjau, lalu menyimpan dan menjalankan pencocokan shift; kartu kedua Recalculate Attendance menghitung ulang presensi semua pegawai aktif untuk satu rentang tanggal (override manual HR tetap dipertahankan; dulu tombol di halaman v1 Daily Attendance).
 Sumber hulu  : App\Support\V2\Data\AttendanceUpload (tm2mst.att_finger_import, att_finger_raw, emp_employee); AttendanceCalculationService.
 Input        : File .csv / .txt / .xlsx (kolom device_pin, tanggal, jam_masuk, jam_keluar); session att_done, v2_flash, errors.
 Output       : x-dl::card + x-dl::action-form (posting ke v2.attendance-upload.parse dan rute simpan, serta v2.hrm.attendance.recalculate), pratinjau maksimal 500 baris, tombol ke Attendance Log.
 Dipakai oleh : Rute v2.screen dengan slug 'hrm-attendance-upload' (menu v2:hrm-attendance-upload di sys_app_menu), dirender view layar generik v2.
 Status       : Aktif (kartu Recalculate ditambah T7e HRM, 2026-10-19).
--}}
@php
    use App\Support\V2\Data\AttendanceUpload;
    use App\Support\V2\Src;

    $pending = AttendanceUpload::pending(auth()->id());
    $shown = 500;
    $columns = 'device_pin, tanggal, jam_masuk, jam_keluar';
    $src = Src::of('tm2mst.att_finger_import', 'tm2mst.att_finger_raw', 'tm2mst.emp_employee');
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::card title="Upload Fingerprint Attendance File" :description="raw(tf('Format: .csv / .txt / .xlsx with the columns :columns. The system matches device_pin to the Employee Master automatically.', ['columns' => $columns]))">
    @if (session('att_done'))
        <x-dl::action-bar>
            <x-dl::button icon="fa-arrow-right" label="Open Attendance Log" :href="route('v2.screen', 'hrm-attendance')" />
            <x-dl::button variant="secondary" icon="fa-upload" label="Upload Another File" :href="route('v2.screen', 'hrm-attendance-upload')" />
        </x-dl::action-bar>
    @else
        <x-dl::action-form action="{{ route('v2.attendance-upload.parse') }}" enctype="multipart/form-data">
            <x-dl::field.upload name="file" label="Attendance File" accept=".csv,.txt,.xlsx" hint="Up to 5 MB." :preview="false" required :source="$src" />
            <x-dl::action-bar><x-dl::button icon="fa-magnifying-glass" label="Read File" /></x-dl::action-bar>
        </x-dl::action-form>

        @if ($pending)
            <x-dl::alert :tone="$pending['unmatched'] ? 'warn' : 'info'" :message="raw(tf('Preview of :file: :rows rows, :unmatched unknown device_pin.', ['file' => $pending['file_name'], 'rows' => count($pending['rows']), 'unmatched' => count($pending['unmatched'])]))" />
            @if (count($pending['rows']) > $shown)
                <x-dl::alert tone="info" :message="raw(tf('Showing the first :shown rows; all rows are processed.', ['shown' => $shown]))" />
            @endif
            <x-dl::table :columns="['device_pin' => 'Device PIN', 'employee_name' => 'Employee', 'tanggal' => 'Date', 'jam_masuk' => 'Time In', 'jam_keluar' => 'Time Out']"
                :rows="array_map(fn ($r) => ['device_pin' => raw($r['device_pin']), 'employee_name' => $r['matched'] ? raw($r['employee_name']) : t('Unknown'), 'tanggal' => raw((string) $r['tanggal']),
                    'jam_masuk' => raw((string) $r['jam_masuk']), 'jam_keluar' => raw($r['jam_keluar'] ?: '—')], array_slice($pending['rows'], 0, $shown))" :source="$src" />
            <x-dl::action-bar>
                <x-dl::action-form action="{{ route('v2.attendance-upload.process') }}">
                    <x-dl::button icon="fa-floppy-disk" label="Save & Run Shift Matching" data-dl-confirm="{{ t('Save the matched rows and calculate attendance now?') }}" />
                </x-dl::action-form>
                <x-dl::action-form action="{{ route('v2.attendance-upload.discard') }}">
                    <x-dl::button variant="secondary" icon="fa-xmark" label="Discard" />
                </x-dl::action-form>
            </x-dl::action-bar>
        @endif
    @endif
</x-dl::card>

<x-dl::card title="Recalculate Attendance" description="Recalculates the daily attendance of every active employee for the dates below from the fingerprint scans, shifts, approved leave and sanctions. Manual overrides are kept. Up to 92 days.">
    <x-dl::action-form action="{{ route('v2.hrm.attendance.recalculate') }}">
        <x-dl::form.row :cols="3">
            <x-dl::field.date name="from" label="From date" required :value="old('from', now()->startOfWeek(Carbon\Carbon::MONDAY)->toDateString())" :source="$src" />
            <x-dl::field.date name="to" label="To date" required :value="old('to', now()->toDateString())" :source="$src" />
        </x-dl::form.row>
        <x-dl::action-bar><x-dl::button icon="fa-rotate" label="Recalculate Attendance" data-dl-confirm="{{ t('Recalculate attendance for the selected dates?') }}" /></x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>
