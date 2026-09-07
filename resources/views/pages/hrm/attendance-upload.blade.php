<?php

use App\Models\Master\AttFingerImport;
use App\Models\Master\AttFingerRaw;
use App\Models\Master\EmpEmployee;
use App\Services\Hrm\AttendanceCalculationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Upload Presensi (Modul 01) -- interface diadopsi dari docs/payroll_timeattendance/
// mockup_01_upload.html (dropzone, preview tabel, tombol "Simpan & Eksekusi
// Matching Shift"). Format kolom mengikuti pola raw export mesin finger fisik
// (docs/ddl/tmhrm.sql::tma_rawdata) yg disederhanakan: device_pin, tanggal,
// jam_masuk, jam_keluar -- BUKAN kolom bebas, krn upload presensi harus
// terstruktur (beda dari mockup yg cuma menampilkan angka acak).
new #[Title('Upload Presensi — TELASIH MAKMUR ERP')] class extends Component
{
    use WithFileUploads;

    public $file = null;

    public array $preview = [];

    public array $unmatched = [];

    public bool $processed = false;

    public string $importSummary = '';

    public function updatedFile(): void
    {
        $this->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx']]);

        $rows = $this->parseRows($this->file->getRealPath(), $this->file->getClientOriginalExtension());
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0] ?? []);
        $deviceIdx = array_search('device_pin', $header, true);
        $dateIdx = array_search('tanggal', $header, true);
        $inIdx = array_search('jam_masuk', $header, true);
        $outIdx = array_search('jam_keluar', $header, true);

        if ($deviceIdx === false || $dateIdx === false || $inIdx === false) {
            $this->addError('file', 'Kolom wajib: device_pin, tanggal, jam_masuk (jam_keluar opsional). Cek header baris pertama file.');
            $this->reset('file');

            return;
        }

        $devicePins = collect($rows)->slice(1)->pluck($deviceIdx)->filter()->unique()->all();
        $employeesByPin = EmpEmployee::whereIn('device_pin', $devicePins)->get()->keyBy('device_pin');

        $this->preview = [];
        $this->unmatched = [];
        foreach (array_slice($rows, 1) as $row) {
            if (empty($row[$deviceIdx])) {
                continue;
            }
            $pin = (string) $row[$deviceIdx];
            $employee = $employeesByPin->get($pin);
            $this->preview[] = [
                'device_pin' => $pin,
                'employee_name' => $employee?->full_name ?? '⚠ Tidak dikenal',
                'tanggal' => $row[$dateIdx] ?? null,
                'jam_masuk' => $row[$inIdx] ?? null,
                'jam_keluar' => $outIdx !== false ? ($row[$outIdx] ?? null) : null,
                'matched' => (bool) $employee,
            ];
            if (! $employee) {
                $this->unmatched[$pin] = true;
            }
        }
    }

    // Simpan ke att_finger_import/att_finger_raw lalu langsung jalankan
    // AttendanceCalculationService per pegawai+tanggal yg ter-upload --
    // "Simpan & Eksekusi Matching Shift" pada mockup_01.
    public function processImport(): void
    {
        if (empty($this->preview)) {
            return;
        }

        $matchedRows = array_filter($this->preview, fn ($r) => $r['matched']);

        $import = AttFingerImport::create([
            'import_date' => now()->toDateString(),
            'file_name' => $this->file?->getClientOriginalName() ?? 'upload.csv',
            'imported_by' => Auth::user()->login,
            'row_count' => count($matchedRows),
            'status' => 'PROCESSED',
            'processed_at' => now(),
        ]);

        $employeesByPin = EmpEmployee::whereIn('device_pin', array_column($matchedRows, 'device_pin'))->get()->keyBy('device_pin');
        $affected = []; // [employeeId => [dates]]

        foreach ($matchedRows as $row) {
            $employee = $employeesByPin->get($row['device_pin']);
            $date = Carbon::parse($row['tanggal']);

            if (! empty($row['jam_masuk'])) {
                AttFingerRaw::create([
                    'id_import' => $import->id_import,
                    'employee_id' => $employee->employee_id,
                    'scan_datetime' => $date->copy()->setTimeFromTimeString($row['jam_masuk']),
                    'scan_type' => 'IN',
                ]);
            }
            if (! empty($row['jam_keluar'])) {
                AttFingerRaw::create([
                    'id_import' => $import->id_import,
                    'employee_id' => $employee->employee_id,
                    'scan_datetime' => $date->copy()->setTimeFromTimeString($row['jam_keluar']),
                    'scan_type' => 'OUT',
                ]);
            }

            $affected[$employee->employee_id][] = $date->toDateString();
        }

        $service = app(AttendanceCalculationService::class);
        $calculated = 0;
        foreach ($affected as $employeeId => $dates) {
            $periodStart = Carbon::parse(min($dates))->startOfWeek(Carbon::MONDAY);
            foreach (array_unique($dates) as $dateStr) {
                $service->calculateForDate((int) $employeeId, Carbon::parse($dateStr), $periodStart);
                $calculated++;
            }
        }

        $this->processed = true;
        $this->importSummary = "{$import->row_count} baris presensi tersimpan, {$calculated} hari presensi berhasil dihitung status kehadirannya.";
    }

    // Baca CSV/TXT manual (fgetcsv, tanpa dependensi) atau .xlsx via
    // PhpSpreadsheet langsung -- maatwebsite/excel v4 Excel::toArray() minta
    // instance class Import utk kasus sesimpel ini, PhpSpreadsheet native
    // lebih ringkas.
    private function parseRows(string $path, string $extension): array
    {
        if (in_array(strtolower($extension), ['csv', 'txt'], true)) {
            $rows = [];
            if (($handle = fopen($path, 'r')) !== false) {
                while (($line = fgetcsv($handle)) !== false) {
                    $rows[] = $line;
                }
                fclose($handle);
            }

            return $rows;
        }

        return IOFactory::load($path)->getActiveSheet()->toArray();
    }

    public function reset_(): void
    {
        $this->reset('file', 'preview', 'unmatched', 'processed', 'importSummary');
    }
};
?>

<div>
    <div class="max-w-[1100px] mx-auto my-5 px-4">
        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 p-5">
            <h1 class="text-sm font-bold text-[#1a1d21] mb-1">Unggah File Presensi Fingerprint</h1>
            <p class="text-[11px] text-muted mb-4">Format: .csv/.txt/.xlsx dengan kolom <code class="bg-[#f4f6fa] px-1 rounded">device_pin, tanggal, jam_masuk, jam_keluar</code>. Sistem mencocokkan device_pin ke Master Karyawan secara otomatis.</p>

            @if (! $processed)
                <label class="block border-2 border-dashed border-[#d7dbe2] rounded-lg p-8 text-center hover:border-accent transition cursor-pointer bg-[#fafbfd]">
                    <div class="text-3xl mb-2">📁</div>
                    <p class="text-xs font-semibold">Klik untuk pilih file hasil tarikan mesin finger</p>
                    <p class="text-[11px] text-muted mt-1">Maksimal 5 MB</p>
                    <input type="file" wire:model="file" class="hidden" accept=".csv,.txt,.xlsx">
                </label>
                @error('file') <div class="text-[11px] text-red-600 mt-2">{{ $message }}</div> @enderror

                <div wire:loading wire:target="file" class="text-xs text-muted mt-3">Membaca file...</div>

                @if (! empty($preview))
                    <div class="mt-5">
                        <div class="flex justify-between items-center mb-3">
                            <h2 class="text-xs font-bold">Preview Data ({{ count($preview) }} baris, {{ count($unmatched) }} device_pin tidak dikenal)</h2>
                            <button type="button" wire:click="processImport" class="bg-navy text-white text-xs px-4 py-2 rounded font-semibold">💾 Simpan & Eksekusi Matching Shift</button>
                        </div>
                        <div class="overflow-x-auto max-h-[360px] overflow-y-auto border border-[#eef1f5] rounded">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr>
                                        <th class="p-2 border-b border-[#eef1f5] bg-[#fafbfd] uppercase text-[11px] text-[#667] sticky top-0">Device PIN</th>
                                        <th class="p-2 border-b border-[#eef1f5] bg-[#fafbfd] uppercase text-[11px] text-[#667] sticky top-0">Pegawai</th>
                                        <th class="p-2 border-b border-[#eef1f5] bg-[#fafbfd] uppercase text-[11px] text-[#667] sticky top-0">Tanggal</th>
                                        <th class="p-2 border-b border-[#eef1f5] bg-[#fafbfd] uppercase text-[11px] text-[#667] sticky top-0">Jam Masuk</th>
                                        <th class="p-2 border-b border-[#eef1f5] bg-[#fafbfd] uppercase text-[11px] text-[#667] sticky top-0">Jam Keluar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($preview as $row)
                                        <tr class="border-b border-[#eef1f5] {{ ! $row['matched'] ? 'bg-[#fee2e2]' : '' }}">
                                            <td class="p-2 font-mono">{{ $row['device_pin'] }}</td>
                                            <td class="p-2">{{ $row['employee_name'] }}</td>
                                            <td class="p-2 font-mono">{{ $row['tanggal'] }}</td>
                                            <td class="p-2 font-mono">{{ $row['jam_masuk'] }}</td>
                                            <td class="p-2 font-mono">{{ $row['jam_keluar'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @else
                <div class="bg-[#dcfce7] border border-[#a7e5bf] text-[#166534] text-sm font-semibold px-4 py-3 rounded mb-4">✓ {{ $importSummary }}</div>
                <div class="flex gap-2">
                    <a href="{{ route('hrm.attendance-daily') }}" class="bg-navy text-white text-xs px-4 py-2 rounded font-semibold">Buka Grid Presensi Harian →</a>
                    <button type="button" wire:click="reset_" class="bg-[#e6eaf0] text-[#333] text-xs px-4 py-2 rounded font-semibold">Upload File Lain</button>
                </div>
            @endif
        </div>
    </div>
</div>
