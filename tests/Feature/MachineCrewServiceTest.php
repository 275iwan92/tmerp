<?php

/*
 * REMARK
 * Tujuan       : Bukti bahwa aturan Assign Crew bekerja: mesin Offset saja, nickname wajib, crew ke-5 ditolak, periode bertabrakan, anggota maks 10, orang dua kali, peran kosong, bukan karyawan produksi, bentrok orang yang sama pada shift dan periode yang sama.
 * Sumber hulu  : MachineCrewService (savePeriod, addCrew, deactivate), ProdMachineCrew::name().
 * Input        : Mesin Offset, shift produksi, dan karyawan produksi dari database dummy; semuanya di transaksi tm2mst yang di-rollback.
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test (skip di tm2*).
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineCrew;
use App\Models\Sys\SecUser;
use App\Services\Scheduling\Crew\MachineCrewService as Crews;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MachineCrewServiceTest extends TestCase
{
    private $mst;

    private ProdMachine $machine;

    private int $shift;

    /** @var array<int, int> employee ids of production staff */
    private array $people;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mst = DB::connection('tm2mst');
        if (! str_starts_with($this->mst->getDatabaseName(), 'ddt')) {
            $this->markTestSkipped('Dummy database only.');
        }
        $this->actingAs(SecUser::where('is_admin', 1)->firstOrFail());
        $this->mst->beginTransaction();

        $this->machine = Crews::assignableMachines()->orderBy('machine_id')->firstOrFail();
        $this->machine->forceFill(['nickname' => 'TESTNICK'])->save();
        $this->shift = (int) array_key_first(Crews::shifts());
        $this->people = array_keys(Crews::employees());
        $this->assertGreaterThanOrEqual(12, count($this->people), 'Need at least 12 production employees in the dummy database.');
        $this->mst->table('prod_machine_crew')->where('machine_id', $this->machine->machine_id)->delete();
    }

    protected function tearDown(): void
    {
        $this->mst?->rollBack();
        parent::tearDown();
    }

    private function members(int $count, int $offset = 0): array
    {
        return array_map(fn ($id) => ['employee_id' => $id, 'role' => Crews::ROLES[0]], array_slice($this->people, $offset, $count));
    }

    private function assertRejected(callable $do, string $fragment): void
    {
        try {
            $do();
        } catch (ValidationException $e) {
            $this->assertStringContainsString($fragment, $e->errors()['crew'][0]);

            return;
        }
        $this->fail('Expected a ValidationException containing: '.$fragment);
    }

    public function test_saves_a_period_with_members_and_names_the_crew(): void
    {
        $crew = Crews::addCrew($this->machine, $this->shift);
        $period = Crews::savePeriod($crew, null, '2026-01-01', null, $this->members(3));

        $this->assertCount(3, $period->members);
        $this->assertSame('TESTNICK - '.Crews::shifts()[$this->shift], $crew->name());
    }

    public function test_empty_rows_are_ignored_and_ten_people_are_allowed(): void
    {
        $crew = Crews::addCrew($this->machine, $this->shift);
        $rows = array_merge($this->members(10), [['employee_id' => '', 'role' => '']]);

        $this->assertCount(10, Crews::savePeriod($crew, null, '2026-01-01', null, $rows)->members);
    }

    public function test_eleven_people_are_rejected(): void
    {
        $crew = Crews::addCrew($this->machine, $this->shift);

        $this->assertRejected(fn () => Crews::savePeriod($crew, null, '2026-01-01', null, $this->members(11)), '10 people');
    }

    public function test_same_person_twice_and_missing_role_are_rejected(): void
    {
        $crew = Crews::addCrew($this->machine, $this->shift);
        $one = $this->members(1);

        $this->assertRejected(fn () => Crews::savePeriod($crew, null, '2026-01-01', null, [$one[0], $one[0]]), 'twice');
        $this->assertRejected(fn () => Crews::savePeriod($crew, null, '2026-01-01', null, [['employee_id' => $one[0]['employee_id'], 'role' => '']]), 'role');
    }

    public function test_non_production_employee_is_rejected(): void
    {
        $crew = Crews::addCrew($this->machine, $this->shift);
        $outsider = (int) $this->mst->table('emp_employee')->whereNotIn('employee_id', $this->people)->value('employee_id');

        $this->assertRejected(fn () => Crews::savePeriod($crew, null, '2026-01-01', null, [['employee_id' => $outsider, 'role' => Crews::ROLES[0]]]), 'production employees');
    }

    public function test_dates_must_be_filled_and_in_order(): void
    {
        $crew = Crews::addCrew($this->machine, $this->shift);

        $this->assertRejected(fn () => Crews::savePeriod($crew, null, null, null, []), 'start date');
        $this->assertRejected(fn () => Crews::savePeriod($crew, null, '2026-02-01', '2026-01-01', []), 'before the start');
    }

    public function test_overlapping_periods_are_rejected_but_adjacent_ones_are_fine(): void
    {
        $crew = Crews::addCrew($this->machine, $this->shift);
        Crews::savePeriod($crew, null, '2026-01-01', '2026-01-31', $this->members(1));

        $this->assertRejected(fn () => Crews::savePeriod($crew, null, '2026-01-31', '2026-02-28', []), 'Overlaps');
        $this->assertRejected(fn () => Crews::savePeriod($crew, null, '2025-12-01', null, []), 'Overlaps');
        $this->assertSame('2026-02-01', Crews::savePeriod($crew, null, '2026-02-01', null, [])->valid_from->toDateString());
    }

    public function test_editing_a_period_does_not_clash_with_itself(): void
    {
        $crew = Crews::addCrew($this->machine, $this->shift);
        $period = Crews::savePeriod($crew, null, '2026-01-01', null, $this->members(2));

        $again = Crews::savePeriod($crew, $period->id_crew_period, '2026-01-01', '2026-06-30', $this->members(3));

        $this->assertSame($period->id_crew_period, $again->id_crew_period);
        $this->assertCount(3, $again->fresh()->members);
    }

    public function test_same_person_cannot_be_in_two_crews_on_the_same_shift_and_period(): void
    {
        $other = Crews::assignableMachines()->where('machine_id', '!=', $this->machine->machine_id)->whereNotNull('nickname')->where('nickname', '!=', '')->first();
        $this->assertNotNull($other, 'Need a second Offset machine with a nickname.');
        $this->mst->table('prod_machine_crew')->where('machine_id', $other->machine_id)->delete();

        $a = Crews::addCrew($this->machine, $this->shift);
        $b = Crews::addCrew($other, $this->shift);
        Crews::savePeriod($a, null, '2026-01-01', null, $this->members(1));

        $this->assertRejected(fn () => Crews::savePeriod($b, null, '2026-03-01', null, $this->members(1)), 'already in crew');
        $this->assertCount(1, Crews::savePeriod($b, null, '2026-03-01', null, $this->members(1, 1))->members);
    }

    public function test_fifth_crew_and_empty_nickname_are_rejected_and_deactivated_index_is_not_reused(): void
    {
        $shifts = array_keys(Crews::shifts());
        foreach (range(1, Crews::MAX_CREWS) as $i) {
            Crews::addCrew($this->machine, $shifts[0]);
        }
        $this->assertRejected(fn () => Crews::addCrew($this->machine, $shifts[0]), 'maximum');

        $first = ProdMachineCrew::query()->where('machine_id', $this->machine->machine_id)->orderBy('crew_index')->first();
        Crews::deactivate($first);
        $this->assertSame(Crews::MAX_CREWS + 1, Crews::addCrew($this->machine, $shifts[0])->crew_index);

        $this->machine->forceFill(['nickname' => ''])->save();
        $this->assertRejected(fn () => Crews::addCrew($this->machine, $shifts[0]), 'nickname');
    }
}
