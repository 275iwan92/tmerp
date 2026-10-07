{{-- HRM > Payroll > Attendance Upload (v2). Data & aturan = App\Support\V2\Data\AttendanceUpload (att_finger_import / att_finger_raw +
     AttendanceCalculationService), sama dengan layar v1: unggah file mesin finger -> pratinjau -> Simpan & Eksekusi Matching Shift. --}}
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
