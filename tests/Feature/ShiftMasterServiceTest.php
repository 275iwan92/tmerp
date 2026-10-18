<?php

/*
 * REMARK
 * Tujuan       : Menjaga Master Shift v2: simpan shift dan jam kerja tujuh hari (kode wajib dan unik, menit istirahat dihitung dari Break start / Break end, jam masuk tanpa jam pulang ditolak, hari kosong = tidak beroperasi), aktif / nonaktif, dan tidak ada jalur ke halaman v1 master.shift.
 * Sumber hulu  : App\Services\Master\ShiftMasterService, layar md-shift-form, grid md-shift, routes/v2_master.php.
 * Input        : User johiwan (admin); shift uji bernama ZZTEST* di tm2mst.emp_shift (+ emp_shift_detail), dihapus di tearDown.
 * Output       : Lulus bila aturan terpenuhi dan jam bersih sama dengan hitungan papan roster (EmpShift::weekMinutes).
 * Dipakai oleh : php artisan test --filter=ShiftMasterServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Master\EmpShift;
use App\Models\Master\EmpShiftDetail;
use App\Models\Sys\SecUser;
use App\Services\Master\ShiftMasterService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShiftMasterServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        $ids = EmpShift::where('shift_name', 'like', 'ZZTEST%')->pluck('shift_id');
        EmpShiftDetail::whereIn('shift_id', $ids)->delete();
        EmpShift::whereIn('shift_id', $ids)->delete();
        $this->assertSame(0, EmpShift::where('shift_name', 'like', 'ZZTEST%')->count(), 'shift uji harus bersih');
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function input(array $over = []): array
    {
        $in = ['shift_name' => 'ZZTEST Shift', 'department_id' => 1, 'shift_code' => 'zzt1'];
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $d) {
            $in += ['start_'.$d => '05:00', 'bout_'.$d => '09:30', 'bin_'.$d => '10:00', 'end_'.$d => '13:15'];
        }

        return $over + $in;
    }

    public function test_shift_is_saved_with_days_break_minutes_and_net_hours(): void
    {
        $svc = app(ShiftMasterService::class);
        $s = $svc->save(null, $this->input(['start_Saturday' => '21:15', 'end_Saturday' => '05:00']), $this->admin());

        $this->assertSame('ZZT1', $s->shift_code);
        $this->assertTrue((bool) $s->activate);
        $this->assertSame('johiwan', $s->created_by);
        $mon = EmpShiftDetail::where('shift_id', $s->shift_id)->where('day_of_week', 'Monday')->firstOrFail();
        $this->assertSame(30, (int) $mon->break_minutes);
        $this->assertSame('05:00', $mon->start_time->format('H:i'));
        $sat = EmpShiftDetail::where('shift_id', $s->shift_id)->where('day_of_week', 'Saturday')->firstOrFail();
        $this->assertNull($sat->break_minutes);
        $this->assertSame(6, EmpShiftDetail::where('shift_id', $s->shift_id)->count());

        // 5 x (8h15 - 30m) + overnight 21:15-05:00 (7h45) = 38h45 + 7h45
        $fresh = EmpShift::with('details')->find($s->shift_id);
        $this->assertSame(5 * 465 + 465, $fresh->weekMinutes());
        $this->assertSame(6, $fresh->activeDayCount());

        // Ubah istirahat: break_minutes ikut berubah; kosongkan hari: baris detail hilang.
        $svc->save($fresh, $this->input(['bout_Monday' => '09:00', 'bin_Monday' => '10:00', 'start_Tuesday' => '', 'end_Tuesday' => '', 'bout_Tuesday' => '', 'bin_Tuesday' => '']), $this->admin());
        $this->assertSame(60, (int) EmpShiftDetail::where('shift_id', $s->shift_id)->where('day_of_week', 'Monday')->value('break_minutes'));
        $this->assertSame(0, EmpShiftDetail::where('shift_id', $s->shift_id)->where('day_of_week', 'Tuesday')->count());
        $svc->save($fresh, $this->input(['bout_Monday' => '', 'bin_Monday' => '']), $this->admin());
        $this->assertNull(EmpShiftDetail::where('shift_id', $s->shift_id)->where('day_of_week', 'Monday')->value('break_minutes'));

        $svc->setActive($s, false, $this->admin());
        $this->assertFalse((bool) $s->fresh()->activate);
    }

    public function test_rules_are_refused(): void
    {
        $svc = app(ShiftMasterService::class);
        $existing = EmpShift::whereNotNull('shift_code')->value('shift_code');
        $cases = [
            ['shift_name' => ''], ['department_id' => ''], ['shift_code' => ''], ['shift_code' => 'bad code!'],
            ['shift_code' => (string) $existing], ['start_Monday' => '25:00'], ['end_Monday' => ''], ['start_Sunday' => '08:00'],
            ['bout_Monday' => ''], ['bout_Monday' => '10:00', 'bin_Monday' => '10:00'], ['bout_Sunday' => '09:00', 'bin_Sunday' => '09:30'],
        ];
        foreach ($cases as $bad) {
            try {
                $svc->save(null, $this->input($bad), $this->admin());
                $this->fail('harus ditolak: '.json_encode($bad));
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertSame(0, EmpShift::where('shift_name', 'like', 'ZZTEST%')->count(), 'penolakan tidak boleh meninggalkan data');
    }

    public function test_forms_and_routes_work_for_admin(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('v2.screen', 'md-shift'))->assertOk();
        $this->get(route('v2.screen', 'md-shift-form').'?new=1')->assertOk()->assertSee('New Shift');

        $this->post(route('v2.md.shift.save'), $this->input(['shift_name' => 'ZZTEST Route', 'shift_code' => 'ZZR']))->assertRedirect();
        $s = EmpShift::where('shift_name', 'ZZTEST Route')->firstOrFail();
        foreach (['shift', 'hours'] as $tab) {
            $this->get(route('v2.screen', 'md-shift-form').'?id='.$s->shift_id.'&tab='.$tab)->assertOk()->assertSee('ZZTEST Route')->assertSee('ZZR');
        }
        $this->get(route('v2.screen', 'md-shift-form').'?id='.$s->shift_id.'&tab=hours')->assertSee('09:30')->assertSee('13:15');
        $this->post(route('v2.md.shift.save'), $this->input(['id' => $s->shift_id, 'shift_name' => 'ZZTEST Route', 'shift_code' => 'ZZR', 'end_Monday' => '']))->assertSessionHasErrors('start_Monday');
        $this->post(route('v2.md.shift.save'), ['id' => $s->shift_id, 'action' => 'deactivate'])->assertRedirect();
        $this->assertFalse((bool) $s->fresh()->activate);
        $this->post(route('v2.md.shift.save'), ['id' => $s->shift_id, 'action' => 'activate'])->assertRedirect();
        $this->assertTrue((bool) $s->fresh()->activate);
    }

    public function test_grid_and_v1_state(): void
    {
        $grid = Grids::find('md-shift');
        $this->assertStringContainsString('/v2/s/md-shift-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/md-shift-form?new=1', $grid->newUrl());

        $this->assertFileDoesNotExist(resource_path('views/pages/master/shift.blade.php'));
        $this->assertArrayNotHasKey('MD_SHIFT', MenuRoutes::map());
        $this->actingAs($this->admin())->get('/master/shift')->assertRedirect('/v2/s/md-shift');
    }
}
