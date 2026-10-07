<?php

namespace Tests\Feature;

use App\Models\Master\AttMobileGpsLog;
use App\Models\Master\EmpEmployee;
use App\Models\Master\SysGeofenceLocation;
use App\Support\V2\Data\GpsClock;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// GPS Clock-In (C6): objek x-dl::geo-clock + keputusan geofence di server. Jalur dalam-radius menulis presensi sungguhan, jadi hanya jalur
// di-luar-radius (PENDING, hanya satu baris att_mobile_gps_log yang dibersihkan lagi) yang diuji dengan penulisan.
class GeoClockTest extends TestCase
{
    /** @var list<int> */
    private array $logs = [];

    protected function tearDown(): void
    {
        AttMobileGpsLog::query()->whereIn('id_gps_log', $this->logs)->delete();
        parent::tearDown();
    }

    public function test_component_renders_urls_texts_and_history(): void
    {
        $html = Blade::render('<x-dl::geo-clock check-url="/c" clock-url="/k" :radius="150" :history="[[\'type\' => \'IN\', \'time\' => raw(\'07:30:00\'), \'status\' => raw(\'AUTO_APPROVED\'), \'tone\' => \'success\']]" />');
        $this->assertStringContainsString('data-dl-geo', $html);
        $this->assertStringContainsString('data-check-url="/c"', $html);
        $this->assertStringContainsString('data-radius="150"', $html);
        $this->assertStringContainsString('07:30:00', $html);
        $this->assertStringNotContainsString("\u{E000}", $html);
        $this->assertStringContainsString('data-geo-clock="IN"', $html);
    }

    public function test_check_is_inside_at_a_geofence_location_and_outside_far_away(): void
    {
        $loc = SysGeofenceLocation::where('activate', true)->first();
        if (! $loc) {
            $this->markTestSkipped('No active geofence location in this database.');
        }
        $at = GpsClock::check((float) $loc->latitude, (float) $loc->longitude);
        $this->assertTrue($at['in_radius']);
        $this->assertSame(0.0, (float) $at['distance']);
        $this->assertFalse(GpsClock::check(0.0, 0.0)['in_radius']);
    }

    public function test_clock_needs_an_employee_and_a_valid_type(): void
    {
        $this->expectException(ValidationException::class);
        GpsClock::clock(null, 'IN', 0.0, 0.0);
    }

    public function test_invalid_type_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        GpsClock::clock(1, 'SIDEWAYS', 0.0, 0.0);
    }

    public function test_outside_radius_is_pending_and_not_written_as_attendance(): void
    {
        $employee = EmpEmployee::query()->firstOrFail();
        $before = AttMobileGpsLog::where('employee_id', $employee->employee_id)->pluck('id_gps_log')->all();

        $r = GpsClock::clock($employee->employee_id, 'IN', 0.0, 0.0);
        $new = AttMobileGpsLog::where('employee_id', $employee->employee_id)->whereNotIn('id_gps_log', $before)->get();
        $this->logs = $new->pluck('id_gps_log')->all();

        $this->assertSame('warn', $r['tone']);
        $this->assertCount(1, $new);
        $this->assertSame('PENDING', $new[0]->hr_approval_status);
        $this->assertSame('OUT_OF_RADIUS', $new[0]->geofence_status);
        $this->assertNotEmpty($r['history']);
    }
}
