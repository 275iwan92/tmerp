<?php

use App\Models\Master\AttFingerRaw;
use App\Models\Master\AttMobileGpsLog;
use App\Services\Hrm\AttendanceCalculationService;
use App\Services\Hrm\GpsHaversineService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// Mobile GPS Clock-in (Modul 09) -- interface diadopsi dari
// docs/payroll_timeattendance/mockup_09_mobile_gps_clockin.html (kartu jarak
// ke kantor, tombol absen), TAPI backend sungguhan (bukan simulasi Artifact
// yang dikirim sebelumnya) -- Haversine nyata via GpsHaversineService,
// hasil masuk ke att_mobile_gps_log, dan kalau dalam radius langsung
// ditulis ke att_finger_raw + dihitung AttendanceCalculationService (jadi
// "sumber" presensi yang setara data mesin finger fisik). Kalau di luar
// radius, status PENDING -- tidak menulis presensi dulu, menunggu approval
// HR (hrm.gps-approval).
new #[Layout('layouts::app')] #[Title('GPS Attendance — TELASIH MAKMUR ERP')] class extends Component
{
    public ?int $employeeId = null;

    public ?float $latitude = null;

    public ?float $longitude = null;

    public ?float $distanceMeters = null;

    public bool $isInRadius = false;

    public bool $locationChecked = false;

    public ?string $locationError = null;

    public ?string $resultMessage = null;

    public function mount(): void
    {
        $this->employeeId = Auth::user()->employee_id;
    }

    #[Computed]
    public function todayLogs()
    {
        if (! $this->employeeId) {
            return collect();
        }

        return AttMobileGpsLog::where('employee_id', $this->employeeId)
            ->whereDate('created_at', now())
            ->orderBy('created_at')
            ->get();
    }

    public function checkLocation(float $lat, float $lng): void
    {
        $this->latitude = $lat;
        $this->longitude = $lng;
        $this->locationError = null;

        $result = app(GpsHaversineService::class)->nearestLocation($lat, $lng);
        $this->distanceMeters = $result['distance_meters'];
        $this->isInRadius = $result['is_in_radius'];
        $this->locationChecked = true;
    }

    public function reportLocationError(string $message): void
    {
        $this->locationError = $message;
        $this->locationChecked = false;
    }

    public function clockIn(string $clockType): void
    {
        if (! $this->employeeId || ! $this->locationChecked) {
            return;
        }

        $approvalStatus = $this->isInRadius ? 'AUTO_APPROVED' : 'PENDING';

        $log = AttMobileGpsLog::create([
            'employee_id' => $this->employeeId,
            'clock_type' => $clockType,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'distance_meters' => (int) round($this->distanceMeters),
            'geofence_status' => $this->isInRadius ? 'IN_RADIUS' : 'OUT_OF_RADIUS',
            'hr_approval_status' => $approvalStatus,
        ]);

        if ($this->isInRadius) {
            $this->writeAttendanceFromGps($log);
            $this->resultMessage = t('Attendance saved — within the office radius.');
        } else {
            $this->resultMessage = t('Location outside the office radius — the request was sent to HR for approval, attendance is not recorded until approved.');
        }

        unset($this->todayLogs);
    }

    private function writeAttendanceFromGps(AttMobileGpsLog $log): void
    {
        AttFingerRaw::create([
            'id_import' => null,
            'employee_id' => $log->employee_id,
            'scan_datetime' => $log->created_at ?? now(),
            'scan_type' => $log->clock_type,
            'raw_device_code' => 'MOBILE-GPS',
        ]);

        $today = Carbon::today();
        app(AttendanceCalculationService::class)->calculateForDate($log->employee_id, $today, $today->copy()->startOfWeek(Carbon::MONDAY));
    }
};
?>

<div class="max-w-[420px] mx-auto my-6 px-4">
    @if (! $employeeId)
        <div class="bg-white border border-[#d7dbe2] rounded-xl p-6 text-center text-sm text-muted">
            {{ t('This account is not linked to employee data yet — contact HR to enable mobile attendance.') }}
        </div>
    @else
        <div class="bg-[#16140f] text-[#f3ecdc] rounded-3xl p-5 shadow-xl" x-data="gpsClockIn()">
            <div class="flex items-center justify-between border-b border-[#332d20] pb-3 mb-4">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-widest text-[#f0812c]">{{ t('ESS Mobile Attendance') }}</div>
                    <div class="text-base font-bold">{{ t('GPS Geofencing Clock-in') }}</div>
                </div>
            </div>

            <div class="bg-[#1f1c15] border border-[#332d20] rounded-2xl p-4 mb-4">
                <div class="text-[10px] uppercase tracking-wide text-[#a89d86] mb-1">{{ t('Distance to Work Location') }}</div>

                <template x-if="!checked && !error">
                    <div class="text-xs text-[#a89d86]">{{ t('Waiting for GPS location permission…') }}</div>
                </template>

                <template x-if="error">
                    <div class="text-xs text-[#ef6a5b]" x-text="error"></div>
                </template>

                @if ($locationChecked)
                    <div class="font-mono text-2xl font-semibold">{{ $distanceMeters }}<span class="text-xs text-[#a89d86] ml-1">{{ t('meters') }}</span></div>
                    <div class="h-2 rounded bg-[#332d20] mt-2 overflow-hidden">
                        <div class="h-full {{ $isInRadius ? 'bg-[#3fcf8e]' : 'bg-[#f0812c]' }}" style="width: {{ min(100, ($distanceMeters / 200) * 100) }}%"></div>
                    </div>
                    <div class="inline-flex items-center gap-1.5 mt-2 text-[11px] font-semibold px-2.5 py-1 rounded-full {{ $isInRadius ? 'bg-[#163425] text-[#3fcf8e]' : 'bg-[#3a1f1c] text-[#ef6a5b]' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                        {{ $isInRadius ? t('Within the office radius') : t('Outside the office radius') }}
                    </div>
                @endif

                <button type="button" @click="checkLocation()" class="w-full mt-3 text-[11px] text-[#a89d86] underline">↻ {{ t('Recheck GPS location') }}</button>
            </div>

            @if ($resultMessage)
                <div class="text-xs {{ $isInRadius ? 'text-[#3fcf8e]' : 'text-[#f0812c]' }} bg-[#1f1c15] border border-[#332d20] rounded-xl p-3 mb-4">{{ $resultMessage }}</div>
            @endif

            <div class="grid grid-cols-2 gap-2 mb-4">
                <button type="button" wire:click="clockIn('IN')" @if(!$locationChecked) disabled @endif
                    class="py-3.5 rounded-2xl font-bold text-sm {{ $locationChecked ? 'bg-[#f0812c] text-[#100f0c]' : 'bg-[#332d20] text-[#746a58]' }}">{{ t('Record Clock In') }}</button>
                <button type="button" wire:click="clockIn('OUT')" @if(!$locationChecked) disabled @endif
                    class="py-3.5 rounded-2xl font-bold text-sm border border-[#332d20] {{ $locationChecked ? 'text-[#f3ecdc]' : 'text-[#746a58]' }}">{{ t('Record Clock Out') }}</button>
            </div>

            <div class="text-[10px] uppercase tracking-wide text-[#a89d86] mb-2">{{ t('Today\'s History') }}</div>
            <div class="flex flex-col gap-1.5">
                @forelse ($this->todayLogs as $log)
                    <div class="flex justify-between text-xs bg-[#1f1c15] border border-[#332d20] rounded-lg px-3 py-2">
                        <span>{{ $log->clock_type === 'IN' ? t('Clock In') : t('Clock Out') }}</span>
                        <span class="font-mono">{{ $log->created_at->format('H:i:s') }}</span>
                        <span class="text-[10px] px-2 py-0.5 rounded-full {{ $log->hr_approval_status === 'PENDING' ? 'bg-[#3a1f1c] text-[#ef6a5b]' : 'bg-[#163425] text-[#3fcf8e]' }}">{{ $log->hr_approval_status }}</span>
                    </div>
                @empty
                    <div class="text-xs text-[#746a58] text-center py-2">{{ t('No attendance today yet.') }}</div>
                @endforelse
            </div>
        </div>
    @endif

    <script>
        function gpsClockIn() {
            return {
                checked: false,
                error: null,
                init() { this.checkLocation(); },
                checkLocation() {
                    this.error = null;
                    if (!navigator.geolocation) {
                        this.error = @js(t('This device does not support GPS.'));
                        this.$wire.reportLocationError(this.error);
                        return;
                    }
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            this.checked = true;
                            this.$wire.checkLocation(pos.coords.latitude, pos.coords.longitude);
                        },
                        (err) => {
                            this.checked = false;
                            this.error = @js(t('Location permission denied or GPS could not be read.'));
                            this.$wire.reportLocationError(this.error);
                        },
                        { enableHighAccuracy: true, timeout: 10000 }
                    );
                },
            };
        }
    </script>
</div>
