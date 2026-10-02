<?php

namespace Tests\Feature;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineDowntime;
use App\Models\Master\ProdProcess;
use App\Models\Master\ProdProcessMachine;
use App\Models\Master\ProdProcessPrecedence;
use App\Models\Master\ProdRoutingStep;
use App\Models\Master\ProdRoutingTemplate;
use App\Services\Production\MachineCapabilityService;
use App\Services\Production\MachineOeeService;
use App\Services\Production\RoutingTemplateService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Regresi matriks kapabilitas Proses x Mesin + routing template + OEE.
//
// Test memakai MySQL lokal sungguhan (CLAUDE.md) dan MEMBERSIHKAN SENDIRI di
// tearDown() -- tidak ada RefreshDatabase. Semua baris uji diberi prefix
// ZZTEST supaya cleanup bisa menargetkannya tanpa menyentuh data nyata
// (66 pasangan kapabilitas hasil migration HARUS tetap utuh).
class MachineProcessCapabilityTest extends TestCase
{
    private const TAG = 'ZZTEST-CAP';

    private ProdProcess $process;

    private ProdMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->machine = ProdMachine::create([
            // division_id NOT NULL tanpa default di skema warisan -> wajib diisi.
            'division_id' => DB::connection('tm2mst')->table('org_division')->min('division_id'),
            'machine_code' => 'ZZTEST-M1',
            'machine_name' => 'Mesin Uji Kapabilitas',
            'machine_type' => 'Offset Printing',
            'max_sheet_length_mm' => 1030,
            'max_sheet_width_mm' => 720,
            'min_sheet_length_mm' => 340,
            'min_sheet_width_mm' => 480,
            'gsm_min' => 100,
            'gsm_max' => 400,
            'max_speed_per_hour' => 15000,
            'speed_target_per_hour' => 12000,
            'hourly_rate' => 250000,
            'machine_status' => 'READY',
            'is_process_machine' => true,
            'activate' => true,
            'created_by' => self::TAG,
        ]);

        $this->process = ProdProcess::create([
            'process_code' => 'ZZT1',
            'process_name' => 'ZZTEST Proses Cetak',
            'process_stage' => 'PRESS',
            'output_uom' => 'LEMBAR',
            'default_setup_minutes' => 45,
            'default_speed_per_hour' => 10000,
            'default_waste_pct' => 2.5,
            'default_waste_fixed_qty' => 150,
            'default_manpower' => 3,
            'inhouse' => true,
            'activate' => true,
            'created_by' => self::TAG,
        ]);
    }

    protected function tearDown(): void
    {
        $db = DB::connection('tm2mst');

        // Urutan hapus mengikuti arah FK: anak dulu, induk terakhir.
        $tplIds = $db->table('prod_routing_template')->where('template_code', 'like', 'ZZTEST%')->pluck('template_id');
        $db->table('prod_routing_step')->whereIn('template_id', $tplIds)->delete();
        $db->table('prod_routing_template')->whereIn('template_id', $tplIds)->delete();

        $procIds = $db->table('prod_process')->where('process_name', 'like', 'ZZTEST%')->pluck('id_process');
        $machIds = $db->table('prod_machine')->where('machine_code', 'like', 'ZZTEST%')->pluck('machine_id');

        $db->table('prod_process_precedence')
            ->where(fn ($q) => $q->whereIn('process_id', $procIds)->orWhereIn('related_process_id', $procIds))
            ->delete();
        $db->table('prod_process_machine')
            ->where(fn ($q) => $q->whereIn('process_id', $procIds)->orWhereIn('machine_id', $machIds))
            ->delete();
        $db->table('prod_machine_downtime')->whereIn('machine_id', $machIds)->delete();
        $db->table('prod_machine_changeover')->whereIn('machine_id', $machIds)->delete();
        $db->table('prod_process')->whereIn('id_process', $procIds)->delete();
        $db->table('prod_machine')->whereIn('machine_id', $machIds)->delete();

        parent::tearDown();
    }

    private function pair(array $overrides = []): ProdProcessMachine
    {
        return ProdProcessMachine::create($overrides + [
            'process_id' => $this->process->id_process,
            'machine_id' => $this->machine->machine_id,
            'preference_rank' => 1,
            'is_certified' => true,
            'activate' => true,
            'created_by' => self::TAG,
        ]);
    }

    public function test_pilih_proses_mengembalikan_mesin_yang_bisa_mengerjakannya(): void
    {
        $this->pair();

        $rows = app(MachineCapabilityService::class)->machinesForProcess($this->process->id_process);

        $this->assertCount(1, $rows);
        $this->assertSame('ZZTEST-M1', $rows[0]['machine']->machine_code);
        $this->assertTrue($rows[0]['eligible']);
        $this->assertTrue($rows[0]['is_primary']);
    }

    public function test_arah_balik_pilih_mesin_mengembalikan_prosesnya(): void
    {
        $this->pair();

        $rows = app(MachineCapabilityService::class)->processesForMachine($this->machine->machine_id);

        $this->assertCount(1, $rows);
        $this->assertSame('ZZTEST Proses Cetak', $rows[0]->process->process_name);
    }

    public function test_parameter_diresolusi_tiga_lapis_pasangan_lalu_proses_lalu_mesin(): void
    {
        $svc = app(MachineCapabilityService::class);

        // Lapis 1: override pasangan menang.
        $cap = $this->pair(['speed_per_hour' => 9500, 'setup_minutes' => 30]);
        $p = $svc->resolveParams($cap->fresh(), $this->process);
        $this->assertSame('CAPABILITY', $p['speed_per_hour']['source']);
        $this->assertEquals(9500, $p['speed_per_hour']['value']);

        // Lapis 2: pasangan kosong -> default proses.
        DB::connection('tm2mst')->table('prod_process_machine')
            ->where('capability_id', $cap->capability_id)->update(['speed_per_hour' => null]);
        $p = $svc->resolveParams(ProdProcessMachine::find($cap->capability_id), $this->process);
        $this->assertSame('PROCESS', $p['speed_per_hour']['source']);
        $this->assertEquals(10000, $p['speed_per_hour']['value']);

        // Lapis 3: proses juga kosong -> angka mesin.
        DB::connection('tm2mst')->table('prod_process')
            ->where('id_process', $this->process->id_process)->update(['default_speed_per_hour' => null]);
        $p = $svc->resolveParams(
            ProdProcessMachine::find($cap->capability_id),
            ProdProcess::find($this->process->id_process)
        );
        $this->assertSame('MACHINE', $p['speed_per_hour']['source']);
        $this->assertEquals(12000, $p['speed_per_hour']['value']);
    }

    public function test_mesin_yang_tidak_muat_ukuran_lembar_ditandai_tidak_layak_bukan_disembunyikan(): void
    {
        $this->pair();

        // Officer tetap harus melihat mesinnya beserta alasannya -- pola
        // "engine suggests, officer confirms".
        $rows = app(MachineCapabilityService::class)->machinesForProcess(
            $this->process->id_process,
            ['sheet_length_mm' => 1200, 'sheet_width_mm' => 900]
        );

        $this->assertCount(1, $rows);
        $this->assertFalse($rows[0]['eligible']);
        // Teks alasan ikut bahasa tampilan (kamus) -> yang diuji = ukuran lembar yang ditolak.
        $this->assertStringContainsString('1200x900', $rows[0]['reasons'][0]);
    }

    public function test_lembar_boleh_diputar_sembilan_puluh_derajat(): void
    {
        $this->pair();

        // 700x1000 tidak muat apa adanya (700 > batas lebar 720? tidak) --
        // dipakai 720x1030 sebagai kasus yang HANYA muat setelah diputar.
        $rows = app(MachineCapabilityService::class)->machinesForProcess(
            $this->process->id_process,
            ['sheet_length_mm' => 700, 'sheet_width_mm' => 1000]
        );

        $this->assertTrue($rows[0]['eligible'], 'Lembar 700x1000 harus muat setelah diputar ke 1000x700.');
    }

    public function test_gramatur_di_luar_rentang_mesin_diblokir(): void
    {
        $this->pair();

        $rows = app(MachineCapabilityService::class)
            ->machinesForProcess($this->process->id_process, ['gsm' => 600]);

        $this->assertFalse($rows[0]['eligible']);
        $this->assertStringContainsString('600', $rows[0]['reasons'][0]);
    }

    public function test_mesin_rusak_tidak_layak_dijadwalkan_tapi_pasangannya_tetap_ada(): void
    {
        $this->pair();
        $this->machine->update(['machine_status' => 'BREAKDOWN']);

        $rows = app(MachineCapabilityService::class)->machinesForProcess($this->process->id_process);

        $this->assertCount(1, $rows, 'Pasangan tidak boleh hilang hanya karena mesin rusak sementara.');
        $this->assertFalse($rows[0]['eligible']);
        $this->assertFalse($this->machine->fresh()->isSchedulable());
    }

    public function test_estimasi_jam_memasukkan_setup_dan_waste_tetap_plus_persen(): void
    {
        $cap = $this->pair(['speed_per_hour' => 10000, 'setup_minutes' => 60]);

        // 20.000 x 1,025 + 150 = 20.650 lembar; /10.000 = 2,065 jam; + 1 jam setup.
        $hours = app(MachineCapabilityService::class)->estimateHours($cap, 20000, $this->process);

        $this->assertSame(3.07, $hours);
    }

    public function test_estimasi_jam_null_bila_kecepatan_belum_ada_bukan_nol(): void
    {
        DB::connection('tm2mst')->table('prod_process')
            ->where('id_process', $this->process->id_process)->update(['default_speed_per_hour' => null]);
        $this->machine->update(['speed_target_per_hour' => null]);
        $cap = $this->pair();

        $hours = app(MachineCapabilityService::class)
            ->estimateHours($cap, 10000, ProdProcess::find($this->process->id_process));

        $this->assertNull($hours, 'Kecepatan kosong harus jadi NULL, bukan 0 -- 0 jam akan dibaca sebagai fakta.');
    }

    public function test_where_used_menghitung_pemakaian_lintas_modul(): void
    {
        $this->pair();

        $used = app(MachineCapabilityService::class)->whereUsedMachine($this->machine->machine_id);

        $this->assertSame(1, $used['Kapabilitas proses']);
    }

    public function test_audit_cakupan_menandai_proses_tanpa_mesin(): void
    {
        // Sengaja TIDAK dipasangkan -> harus muncul di audit.
        $audit = app(MachineCapabilityService::class)->coverageAudit();

        $this->assertArrayHasKey($this->process->id_process, $audit['process_without_machine']->all());
        $this->assertArrayHasKey($this->machine->machine_id, $audit['machine_without_process']->all());
    }

    public function test_proses_manual_tidak_dianggap_belum_dipetakan(): void
    {
        $this->process->update(['is_manual' => true]);

        $audit = app(MachineCapabilityService::class)->coverageAudit();

        $this->assertArrayNotHasKey($this->process->id_process, $audit['process_without_machine']->all());
    }

    public function test_validator_routing_memblokir_urutan_yang_mustahil(): void
    {
        $second = ProdProcess::create([
            'process_code' => 'ZZT2',
            'process_name' => 'ZZTEST Proses Kupas',
            'process_stage' => 'FINISHING',
            'is_manual' => true,
            'inhouse' => true,
            'activate' => true,
            'created_by' => self::TAG,
        ]);
        $this->process->update(['is_manual' => true]);

        ProdProcessPrecedence::create([
            'process_id' => $second->id_process,
            'related_process_id' => $this->process->id_process,
            'rule_type' => 'MUST_FOLLOW',
            'severity' => 'BLOCK',
            'reason' => 'Uji: kupas harus sesudah cetak.',
            'activate' => true,
            'created_by' => self::TAG,
        ]);

        $svc = app(RoutingTemplateService::class);

        // Urutan salah: kupas (10) sebelum cetak (20).
        $bad = $svc->validate([
            ['process_id' => $second->id_process, 'step_seq' => 10, 'branch_code' => 'MAIN'],
            ['process_id' => $this->process->id_process, 'step_seq' => 20, 'branch_code' => 'MAIN'],
        ]);
        $this->assertFalse($bad['ok']);
        $this->assertCount(1, $bad['blocks']);

        // Urutan benar.
        $good = $svc->validate([
            ['process_id' => $this->process->id_process, 'step_seq' => 10, 'branch_code' => 'MAIN'],
            ['process_id' => $second->id_process, 'step_seq' => 20, 'branch_code' => 'MAIN'],
        ]);
        $this->assertTrue($good['ok']);
    }

    public function test_aturan_precedence_dinilai_per_cabang(): void
    {
        $second = ProdProcess::create([
            'process_code' => 'ZZT3',
            'process_name' => 'ZZTEST Proses Lem',
            'process_stage' => 'FINISHING',
            'is_manual' => true,
            'inhouse' => true,
            'activate' => true,
            'created_by' => self::TAG,
        ]);
        $this->process->update(['is_manual' => true]);

        ProdProcessPrecedence::create([
            'process_id' => $second->id_process,
            'related_process_id' => $this->process->id_process,
            'rule_type' => 'MUST_FOLLOW',
            'severity' => 'BLOCK',
            'activate' => true,
            'created_by' => self::TAG,
        ]);

        // Dua cabang terpisah: langkah cabang LID bukan pendahulu cabang BODY.
        $result = app(RoutingTemplateService::class)->validate([
            ['process_id' => $second->id_process, 'step_seq' => 10, 'branch_code' => 'BODY'],
            ['process_id' => $this->process->id_process, 'step_seq' => 10, 'branch_code' => 'LID'],
        ]);

        $this->assertTrue($result['ok'], 'Aturan urutan tidak boleh melintasi batas cabang.');
    }

    public function test_routing_memblokir_proses_yang_belum_punya_mesin(): void
    {
        // Proses butuh mesin (is_manual=false) tapi belum dipasangkan.
        $result = app(RoutingTemplateService::class)->validate([
            ['process_id' => $this->process->id_process, 'step_seq' => 10, 'branch_code' => 'MAIN'],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('NO_MACHINE', $result['blocks'][0]['rule']);
    }

    public function test_expand_template_mengusulkan_mesin_dari_matriks(): void
    {
        $this->pair();

        $tpl = ProdRoutingTemplate::create([
            'template_code' => 'ZZTEST-TPL',
            'template_name' => 'Template Uji',
            'activate' => true,
            'created_by' => self::TAG,
        ]);
        ProdRoutingStep::create([
            'template_id' => $tpl->template_id,
            'step_seq' => 10,
            'process_id' => $this->process->id_process,
            'branch_code' => 'MAIN',
            'created_by' => self::TAG,
        ]);

        $expanded = app(RoutingTemplateService::class)->expand($tpl->template_id);

        $this->assertCount(1, $expanded);
        $this->assertSame('ZZTEST-M1', $expanded[0]['suggested_machine']->machine_code);
        $this->assertFalse($expanded[0]['unresolved']);
    }

    public function test_expand_menjelaskan_kenapa_mesin_pilihan_template_diganti(): void
    {
        $this->pair();

        $tpl = ProdRoutingTemplate::create([
            'template_code' => 'ZZTEST-TPL2',
            'template_name' => 'Template Uji Fallback',
            'activate' => true,
            'created_by' => self::TAG,
        ]);
        ProdRoutingStep::create([
            'template_id' => $tpl->template_id,
            'step_seq' => 10,
            'process_id' => $this->process->id_process,
            'preferred_machine_id' => $this->machine->machine_id,
            'branch_code' => 'MAIN',
            'created_by' => self::TAG,
        ]);

        // Lembar terlalu besar -> mesin pilihan template gugur.
        $expanded = app(RoutingTemplateService::class)->expand(
            $tpl->template_id,
            ['sheet_length_mm' => 1500, 'sheet_width_mm' => 1100]
        );

        $this->assertNotNull($expanded[0]['fallback_reason']);
        $this->assertStringContainsString('('.$this->machine->machine_code.')', $expanded[0]['fallback_reason']);
    }

    public function test_oee_null_selama_ketiga_faktor_belum_terukur(): void
    {
        $result = app(MachineOeeService::class)
            ->calculate($this->machine->machine_id, now()->subDays(30), now());

        $this->assertNull($result['oee'], 'OEE hanya sah kalau Availability, Performance, dan Quality ketiganya ada.');
        $this->assertNotEmpty($result['missing']);
    }

    public function test_availability_dihitung_dari_downtime_tak_terencana_saja(): void
    {
        $this->machine->update(['shifts_per_day' => 2, 'hours_per_shift' => 8]);

        // 6 jam stop TERENCANA -> tidak menghukum Availability.
        ProdMachineDowntime::create([
            'machine_id' => $this->machine->machine_id,
            'downtime_kind' => 'PLANNED_MAINT',
            'is_planned' => true,
            'start_at' => now()->subDays(3)->setTime(8, 0),
            'end_at' => now()->subDays(3)->setTime(14, 0),
            'created_by' => self::TAG,
        ]);

        $planned = app(MachineOeeService::class)
            ->calculate($this->machine->machine_id, now()->subDays(30), now());
        $this->assertSame(100.0, (float) $planned['factors']['availability']['value']);
        $this->assertSame(360, $planned['downtime']['planned']);

        // 4 jam BREAKDOWN -> menghukum Availability.
        ProdMachineDowntime::create([
            'machine_id' => $this->machine->machine_id,
            'downtime_kind' => 'BREAKDOWN',
            'is_planned' => false,
            'start_at' => now()->subDays(2)->setTime(8, 0),
            'end_at' => now()->subDays(2)->setTime(12, 0),
            'created_by' => self::TAG,
        ]);

        $after = app(MachineOeeService::class)
            ->calculate($this->machine->machine_id, now()->subDays(30), now());
        $this->assertLessThan(100.0, (float) $after['factors']['availability']['value']);
        $this->assertSame(240, $after['downtime']['unplanned']);
    }

    public function test_durasi_downtime_dihitung_otomatis_saat_disimpan(): void
    {
        $d = ProdMachineDowntime::create([
            'machine_id' => $this->machine->machine_id,
            'downtime_kind' => 'BREAKDOWN',
            'start_at' => now()->subHours(3)->subMinutes(30),
            'end_at' => now(),
            'created_by' => self::TAG,
        ]);

        $this->assertSame(210, $d->fresh()->duration_minutes);
    }

    public function test_downtime_berjalan_tidak_punya_durasi(): void
    {
        $d = ProdMachineDowntime::create([
            'machine_id' => $this->machine->machine_id,
            'downtime_kind' => 'BREAKDOWN',
            'start_at' => now()->subHour(),
            'end_at' => null,
            'created_by' => self::TAG,
        ]);

        $this->assertNull($d->fresh()->duration_minutes);
        $this->assertTrue($d->isOpen());
    }

    public function test_mttr_dan_mtbf_dihitung_dari_kejadian_breakdown(): void
    {
        foreach ([[10, 8, 10], [6, 8, 12]] as [$daysAgo, $startHour, $endHour]) {
            ProdMachineDowntime::create([
                'machine_id' => $this->machine->machine_id,
                'downtime_kind' => 'BREAKDOWN',
                'is_planned' => false,
                'start_at' => now()->subDays($daysAgo)->setTime($startHour, 0),
                'end_at' => now()->subDays($daysAgo)->setTime($endHour, 0),
                'created_by' => self::TAG,
            ]);
        }

        $rel = app(MachineOeeService::class)
            ->reliability($this->machine->machine_id, now()->subDays(30), now());

        $this->assertSame(2, $rel['failures']);
        // (2 jam + 4 jam) / 2 kejadian = 3 jam rata-rata perbaikan.
        $this->assertSame(3.0, $rel['mttr_hours']);
        $this->assertGreaterThan(0, $rel['mtbf_hours']);
    }

    public function test_total_kru_adalah_operator_plus_pembantu(): void
    {
        $this->machine->update(['operator_min' => 1, 'helper_min' => 2]);
        $this->assertSame(3, $this->machine->fresh()->crewTotal());

        $this->machine->update(['operator_min' => null, 'helper_min' => null]);
        $this->assertNull($this->machine->fresh()->crewTotal(), 'Kru belum diisi harus NULL, bukan 0.');
    }

    public function test_skor_kesiapan_mencerminkan_kolom_yang_masih_kosong(): void
    {
        $this->pair();

        $incomplete = ProdMachine::withCount('capabilities')->find($this->machine->machine_id);
        $before = $incomplete->readinessScore();

        $this->machine->update(['shifts_per_day' => 2, 'hours_per_shift' => 8, 'department_id' => 1]);
        $after = ProdMachine::withCount('capabilities')->find($this->machine->machine_id)->readinessScore();

        $this->assertGreaterThan($before, $after);
        $this->assertSame(100, $after);
    }

    public function test_pasangan_proses_mesin_tidak_boleh_ganda(): void
    {
        $this->pair();

        $this->expectException(QueryException::class);
        $this->pair();
    }
}
