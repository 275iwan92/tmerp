<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Kalender kerja Telasih (user 2026-10-01):
// 1. Jam kantor = hari & jam CV. Telasih Makmur menerima / mengirim (bukan jam produksi -- produksi bisa 24/7 menurut shift
//    emp_shift). Senin-Jumat 07:30-16:30, Sabtu 07:30-16:00, Minggu libur. Dipakai slot kirim CPDEL.
// 2. Hari libur nasional + cuti bersama 2026 & 2027 = SKB 3 Menteri (2026: No. 1497/2/5 Tahun 2025; 2027: No. 1205/3/2
//    Tahun 2026, setneg.go.id). Nama English (DB English, LOCKED 2026-10-01), terjemahan Indonesia di sys_dictionary.
// 3. Menu Master Data: Holiday Calendar (menggantikan Master Hari Libur v1) + Office Hours.
return new class extends Migration
{
    private const HOLIDAYS = [
        // [date, type, English, Indonesia]
        ['2026-01-01', 'NATIONAL', "New Year's Day 2026", 'Tahun Baru 2026 Masehi'],
        ['2026-01-16', 'NATIONAL', "Isra Mi'raj of Prophet Muhammad", 'Isra Mikraj Nabi Muhammad SAW'],
        ['2026-02-16', 'COLLECTIVE_LEAVE', 'Collective leave: Chinese New Year 2577', 'Cuti Bersama Tahun Baru Imlek 2577 Kongzili'],
        ['2026-02-17', 'NATIONAL', 'Chinese New Year 2577', 'Tahun Baru Imlek 2577 Kongzili'],
        ['2026-03-18', 'COLLECTIVE_LEAVE', 'Collective leave: Nyepi', 'Cuti Bersama Hari Suci Nyepi'],
        ['2026-03-19', 'NATIONAL', 'Nyepi (Saka New Year 1948)', 'Hari Suci Nyepi (Tahun Baru Saka 1948)'],
        ['2026-03-20', 'COLLECTIVE_LEAVE', 'Collective leave: Eid al-Fitr 1447 H', 'Cuti Bersama Idulfitri 1447 H'],
        ['2026-03-21', 'NATIONAL', 'Eid al-Fitr 1447 H (day 1)', 'Idulfitri 1447 H (hari 1)'],
        ['2026-03-22', 'NATIONAL', 'Eid al-Fitr 1447 H (day 2)', 'Idulfitri 1447 H (hari 2)'],
        ['2026-03-23', 'COLLECTIVE_LEAVE', 'Collective leave: Eid al-Fitr 1447 H', 'Cuti Bersama Idulfitri 1447 H'],
        ['2026-03-24', 'COLLECTIVE_LEAVE', 'Collective leave: Eid al-Fitr 1447 H', 'Cuti Bersama Idulfitri 1447 H'],
        ['2026-04-03', 'NATIONAL', 'Good Friday', 'Wafat Yesus Kristus'],
        ['2026-04-05', 'NATIONAL', 'Easter Sunday', 'Kebangkitan Yesus Kristus (Paskah)'],
        ['2026-05-01', 'NATIONAL', 'International Labour Day', 'Hari Buruh Internasional'],
        ['2026-05-14', 'NATIONAL', 'Ascension of Jesus Christ', 'Kenaikan Yesus Kristus'],
        ['2026-05-15', 'COLLECTIVE_LEAVE', 'Collective leave: Ascension of Jesus Christ', 'Cuti Bersama Kenaikan Yesus Kristus'],
        ['2026-05-27', 'NATIONAL', 'Eid al-Adha 1447 H', 'Iduladha 1447 H'],
        ['2026-05-28', 'COLLECTIVE_LEAVE', 'Collective leave: Eid al-Adha 1447 H', 'Cuti Bersama Iduladha 1447 H'],
        ['2026-05-31', 'NATIONAL', 'Vesak Day 2570 BE', 'Hari Raya Waisak 2570 BE'],
        ['2026-06-01', 'NATIONAL', 'Pancasila Day', 'Hari Lahir Pancasila'],
        ['2026-06-16', 'NATIONAL', 'Islamic New Year 1448 H', '1 Muharam Tahun Baru Islam 1448 H'],
        ['2026-08-17', 'NATIONAL', 'Independence Day', 'Proklamasi Kemerdekaan'],
        ['2026-08-25', 'NATIONAL', "Prophet Muhammad's Birthday", 'Maulid Nabi Muhammad SAW'],
        ['2026-12-24', 'COLLECTIVE_LEAVE', 'Collective leave: Christmas', 'Cuti Bersama Kelahiran Yesus Kristus'],
        ['2026-12-25', 'NATIONAL', 'Christmas Day', 'Kelahiran Yesus Kristus (Natal)'],

        ['2027-01-01', 'NATIONAL', "New Year's Day 2027", 'Tahun Baru 2027 Masehi'],
        ['2027-01-05', 'NATIONAL', "Isra Mi'raj of Prophet Muhammad 1448 H", 'Isra Mikraj Nabi Muhammad SAW 1448 H'],
        ['2027-02-05', 'COLLECTIVE_LEAVE', 'Collective leave: Chinese New Year 2578', 'Cuti Bersama Tahun Baru Imlek 2578 Kongzili'],
        ['2027-02-06', 'NATIONAL', 'Chinese New Year 2578', 'Tahun Baru Imlek 2578 Kongzili'],
        ['2027-03-08', 'NATIONAL', 'Nyepi (Saka New Year 1949)', 'Hari Suci Nyepi (Tahun Baru Saka 1949)'],
        ['2027-03-09', 'COLLECTIVE_LEAVE', 'Collective leave: Eid al-Fitr 1448 H', 'Cuti Bersama Idulfitri 1448 H'],
        ['2027-03-10', 'NATIONAL', 'Eid al-Fitr 1448 H (day 1)', 'Idulfitri 1448 H (hari 1)'],
        ['2027-03-11', 'NATIONAL', 'Eid al-Fitr 1448 H (day 2)', 'Idulfitri 1448 H (hari 2)'],
        ['2027-03-12', 'COLLECTIVE_LEAVE', 'Collective leave: Eid al-Fitr 1448 H', 'Cuti Bersama Idulfitri 1448 H'],
        ['2027-03-15', 'COLLECTIVE_LEAVE', 'Collective leave: Eid al-Fitr 1448 H', 'Cuti Bersama Idulfitri 1448 H'],
        ['2027-03-25', 'COLLECTIVE_LEAVE', 'Collective leave: Good Friday', 'Cuti Bersama Wafat Yesus Kristus'],
        ['2027-03-26', 'NATIONAL', 'Good Friday', 'Wafat Yesus Kristus'],
        ['2027-03-28', 'NATIONAL', 'Easter Sunday', 'Kebangkitan Yesus Kristus (Paskah)'],
        ['2027-05-01', 'NATIONAL', 'International Labour Day', 'Hari Buruh Internasional'],
        ['2027-05-06', 'NATIONAL', 'Ascension of Jesus Christ', 'Kenaikan Yesus Kristus'],
        ['2027-05-17', 'NATIONAL', 'Eid al-Adha 1448 H', 'Iduladha 1448 H'],
        ['2027-05-18', 'COLLECTIVE_LEAVE', 'Collective leave: Eid al-Adha 1448 H', 'Cuti Bersama Iduladha 1448 H'],
        ['2027-05-19', 'COLLECTIVE_LEAVE', 'Collective leave: Vesak Day', 'Cuti Bersama Hari Raya Waisak'],
        ['2027-05-20', 'NATIONAL', 'Vesak Day 2571 BE', 'Hari Raya Waisak 2571 BE'],
        ['2027-06-01', 'NATIONAL', 'Pancasila Day', 'Hari Lahir Pancasila'],
        ['2027-06-06', 'NATIONAL', 'Islamic New Year 1449 H', '1 Muharam Tahun Baru Islam 1449 H'],
        ['2027-08-15', 'NATIONAL', "Prophet Muhammad's Birthday", 'Maulid Nabi Muhammad SAW'],
        ['2027-08-17', 'NATIONAL', 'Independence Day', 'Proklamasi Kemerdekaan'],
        ['2027-12-24', 'COLLECTIVE_LEAVE', 'Collective leave: Christmas', 'Cuti Bersama Kelahiran Yesus Kristus'],
        ['2027-12-25', 'NATIONAL', 'Christmas Day', 'Kelahiran Yesus Kristus (Natal)'],
        ['2027-12-26', 'NATIONAL', "Isra Mi'raj of Prophet Muhammad 1449 H", 'Isra Mikraj Nabi Muhammad SAW 1449 H'],
    ];

    // [ISO day, English, Indonesia, workday, start, end]
    private const OFFICE = [
        [1, 'Monday', 'Senin', true, '07:30', '16:30'],
        [2, 'Tuesday', 'Selasa', true, '07:30', '16:30'],
        [3, 'Wednesday', 'Rabu', true, '07:30', '16:30'],
        [4, 'Thursday', 'Kamis', true, '07:30', '16:30'],
        [5, 'Friday', 'Jumat', true, '07:30', '16:30'],
        [6, 'Saturday', 'Sabtu', true, '07:30', '16:00'],
        [7, 'Sunday', 'Minggu', false, null, null],
    ];

    private const TYPES = [['NATIONAL', 'National holiday', 'Libur nasional'], ['COLLECTIVE_LEAVE', 'Collective leave', 'Cuti bersama'],
        ['COMPANY', 'Company holiday', 'Libur perusahaan']];

    public function up(): void
    {
        if (! Schema::connection('tm2mst')->hasTable('sys_office_hour')) {
            Schema::connection('tm2mst')->create('sys_office_hour', function (Blueprint $t) {
                $t->id('id_office_hour');
                $t->unsignedTinyInteger('day_of_week')->unique()->comment('ISO: 1 = Monday .. 7 = Sunday');
                $t->string('day_name', 12);
                $t->boolean('is_workday')->default(true);
                $t->time('start_time')->nullable();
                $t->time('end_time')->nullable();
                $t->string('notes', 255)->nullable();
                $t->boolean('activate')->default(true);
                $t->timestamps();
                $t->string('created_by', 100)->nullable();
                $t->string('updated_by', 100)->nullable();
            });
        }
        $mst = DB::connection('tm2mst');
        foreach (self::OFFICE as [$dow, $en, , $work, $start, $end]) {
            $mst->table('sys_office_hour')->updateOrInsert(['day_of_week' => $dow], ['day_name' => $en, 'is_workday' => $work,
                'start_time' => $start, 'end_time' => $end, 'activate' => 1, 'created_at' => now(), 'created_by' => 'claude_code']);
        }

        // enum('NATIONAL','COMPANY') lama -> kode English bebas (tambah COLLECTIVE_LEAVE tanpa ALTER lagi).
        $mst->statement("ALTER TABLE sys_holiday MODIFY holiday_type VARCHAR(20) NOT NULL DEFAULT 'NATIONAL'");
        foreach (self::HOLIDAYS as [$date, $type, $en]) {
            $exists = $mst->table('sys_holiday')->where('holiday_date', $date)->exists();
            $mst->table('sys_holiday')->updateOrInsert(['holiday_date' => $date], ['holiday_name' => $en, 'holiday_type' => $type, 'activate' => 1,
                'data_origin' => 'LIVE'] + ($exists ? ['updated_at' => now(), 'updated_by' => 'claude_code'] : ['created_at' => now(), 'created_by' => 'claude_code']));
        }

        $this->translate('tm2mst.sys_holiday.holiday_name', array_map(fn ($h) => [$h[2], $h[3]], self::HOLIDAYS));
        $this->translate('tm2mst.sys_holiday.holiday_type', array_map(fn ($t) => [$t[1], $t[2]], self::TYPES));
        $this->translate('tm2mst.sys_office_hour.day_name', array_map(fn ($o) => [$o[1], $o[2]], self::OFFICE));

        $sys = DB::connection('tm2sys');
        $sys->table('sys_app_menu')->where('code', 'MD_HOLIDAY')->update(['label' => 'Holiday Calendar', 'target' => 'v2:holiday-calendar', 'target_kind' => 'app',
            'object_type' => 'grid', 'status_code' => 'READY_FOR_QC', 'status_note' => 'v2 Holiday Calendar (grid + form + year calendar)',
            'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
        $holiday = $sys->table('sys_app_menu')->where('code', 'MD_HOLIDAY')->first();
        if ($holiday && ! $sys->table('sys_app_menu')->where('code', 'MD_OFFICE_HOUR')->exists()) {
            $sys->table('sys_app_menu')->insert(['parent_id' => $holiday->parent_id, 'code' => 'MD_OFFICE_HOUR', 'label' => 'Office Hours', 'menu_type' => 'item',
                'object_type' => 'grid', 'target' => 'v2:office-hours', 'target_kind' => 'app', 'sort_order' => $holiday->sort_order + 1, 'activate' => 1,
                'created_at' => now(), 'status_code' => 'READY_FOR_QC', 'status_note' => 'v2 Office Hours (sys_office_hour)',
                'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
        }
    }

    // Terjemahan Indonesia untuk nilai English di database (kamus sys_dictionary, konteks <db>.<tabel>.<kolom>).
    private function translate(string $context, array $pairs): void
    {
        $sys = DB::connection('tm2sys');
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            return;
        }
        foreach ($pairs as [$en, $id]) {
            $sys->table('sys_dictionary')->insertOrIgnore(['context' => $context, 'source_en' => $en, 'created_at' => now(), 'created_by' => 'claude_code']);
            $dictId = $sys->table('sys_dictionary')->where('context', $context)->where('source_en', $en)->value('id_dictionary');
            $sys->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $dictId, 'lang_code' => 'id'], ['text' => $id]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'MD_OFFICE_HOUR')->delete();
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'MD_HOLIDAY')->update(['label' => 'Master Hari Libur', 'target' => null]);
        Schema::connection('tm2mst')->dropIfExists('sys_office_hour');
    }
};
