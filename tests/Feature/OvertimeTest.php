<?php

namespace Tests\Feature;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpOvertimeRequest;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\Overtime;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Overtime v2 (C6): alur DRAFT -> SUBMITTED -> APPROVED / REJECTED. Pengajuan uji memakai tanggal 2099 dan dibersihkan lagi.
class OvertimeTest extends TestCase
{
    private const DATE = '2099-12-31';

    protected function tearDown(): void
    {
        EmpOvertimeRequest::query()->whereDate('overtime_date', self::DATE)->delete();
        parent::tearDown();
    }

    private function input(array $over = []): array
    {
        return $over + ['employee_id' => EmpEmployee::query()->firstOrFail()->employee_id, 'overtime_date' => self::DATE, 'ot_type' => 'AKHIR', 'duration_minutes' => 90];
    }

    public function test_draft_submit_approve_flow(): void
    {
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $o = Overtime::save(null, $this->input(), 'test');
        $this->assertSame('DRAFT', $o->status);

        Overtime::transition($o, 'submit', $admin);
        $this->assertSame('SUBMITTED', $o->fresh()->status);
        $this->assertTrue(Overtime::canDecide($o->fresh(), $admin));

        Overtime::transition($o->fresh(), 'approve', $admin);
        $o = $o->fresh();
        $this->assertSame('APPROVED', $o->status);
        $this->assertSame($admin->login, $o->approved_by);
        $this->assertFalse(Overtime::canEdit($o));
    }

    public function test_reject_then_reopen_and_edit(): void
    {
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $o = Overtime::save(null, $this->input(), 'test');
        Overtime::transition($o, 'submit', $admin);
        Overtime::transition($o->fresh(), 'reject', $admin);
        $this->assertSame('REJECTED', $o->fresh()->status);

        Overtime::transition($o->fresh(), 'reopen', $admin);
        $o = $o->fresh();
        $this->assertSame('DRAFT', $o->status);
        $this->assertNull($o->approved_by);
        $this->assertSame(120, Overtime::save($o, $this->input(['duration_minutes' => 120]), 'test')->duration_minutes);
    }

    public function test_one_request_per_employee_per_date(): void
    {
        Overtime::save(null, $this->input(), 'test');
        $this->expectException(ValidationException::class);
        Overtime::save(null, $this->input(['duration_minutes' => 30]), 'test');
    }

    public function test_duration_limits_and_type_are_validated(): void
    {
        $this->expectException(ValidationException::class);
        Overtime::save(null, $this->input(['duration_minutes' => 2000]), 'test');
    }

    public function test_cannot_approve_a_draft(): void
    {
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $o = Overtime::save(null, $this->input(), 'test');
        $this->expectException(ValidationException::class);
        Overtime::transition($o, 'approve', $admin);
    }
}
