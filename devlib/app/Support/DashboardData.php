<?php

namespace App\Support;

use App\Support\DashboardDocs as Docs;
use Carbon\CarbonImmutable;
use Devlib\Components\Field\Slider;
use Devlib\Components\Frame;
use Devlib\Locale;

// Data contoh opening screen (x-dl::dashboard) untuk situs DevLib (tanpa database). Deterministik: angka sama tiap muat.
// Profil dipilih dari kode jabatan user (config devlib.dashboards); jabatan tanpa profil khusus = 'general'.
// Di tmerp: KPI & target dari tabel target per user per periode, realisasi dari transaksi, JD dari
// org_position.job_description, SOP dari sop_document (terhubung ke jabatan), kata harian dari tabel 366 baris.
final class DashboardData
{
    public const PERIODS = ['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan', 'q3' => '3 Bulan', 'q6' => '6 Bulan', 'q12' => '12 Bulan'];

    // Pengali target terhadap target bulanan, dan capaian periode berjalan (belum selesai).
    private const SCALE = ['daily' => 1 / 25, 'weekly' => 0.24, 'monthly' => 1, 'q3' => 3, 'q6' => 6, 'q12' => 12];

    private const RUNNING = ['daily' => 0.85, 'weekly' => 0.55, 'monthly' => 0.688, 'q3' => 0.9, 'q6' => 0.93, 'q12' => 0.962];

    public static function profile(array $user): string
    {
        return config('devlib.dashboards')[$user['position_code'] ?? ''] ?? 'general';
    }

    // Data contoh untuk akun DevLib tertentu (halaman katalog komponen).
    public static function forLogin(string $login): array
    {
        return self::for(collect(config('devlib.users'))->firstWhere('login', $login) ?? []);
    }

    // Opening screen developer (user 2026-10-05): sambutan sama dengan profil lain; bagian widget = papan Build Progress
    // (slot x-dl::dashboard), jadi tanpa KPI contoh.
    public static function developer(array $user, array $build): array
    {
        $t = $build['totals'] ?? [];
        $n = fn (string $k) => Locale::number((float) ($t[$k] ?? 0), 0);

        return [
            ...array_intersect_key(self::for($user), array_flip(['user', 'attendance', 'weather', 'jobdesc', 'sops'])),
            'profile' => 'developer',
            'profile_label' => 'Developer Dashboard',
            'brief' => [['Screens on v2', $n('v2').' / '.$n('screens')], ['Waiting for your QC', $n('waiting_qc')], ['Locked', $n('locked')], ['Engines', $n('engines')]],
        ];
    }

    public static function for(array $user): array
    {
        $profile = self::profile($user);
        $now = CarbonImmutable::now(config('devlib.timezone'));

        $base = [
            'profile' => $profile,
            'user' => [
                'first' => strtok($user['name'] ?? 'User', ' '),
                'name' => $user['name'] ?? '',
                'code' => $user['employee_code'] ?? null,
                'position' => $user['position'] ?? ($user['role'] ?? null),
                'department' => $user['department'] ?? null,
                'superior' => $user['superior'] ?? null,
            ],
            'attendance' => isset($user['employee_code']) ? ['in' => '07:52', 'status' => 'Tepat waktu', 'tone' => 'success', 'shift' => 'Reguler', 'start' => '08:00', 'end' => '17:00'] : null,
            'weather' => config('devlib.weather'),
            'jobdesc' => Docs::jobdesc($user['position_code'] ?? null),
            'sops' => Docs::sops($user['position_code'] ?? null),
            'periods' => self::PERIODS,
            'period' => 'monthly',
            'quotes' => Frame::textLines('motivasi-harian.txt'),
        ];

        return array_merge($base, match ($profile) {
            'sales' => self::sales($now),
            'management' => self::management($now),
            default => self::general($now),
        });
    }

    // ---- profil Sales ------------------------------------------------------------------------------------

    private static function sales(CarbonImmutable $now): array
    {
        $kpis = $trend = $act = [];
        foreach (self::SCALE as $p => $f) {
            $labels = self::labels($p, $now);
            $target = self::series(count($labels), 600e6 * $f, $p === 'monthly' || $p === 'daily' ? 0.08 : 0.05);
            $actual = self::actuals($target, $p, 11);
            $n = count($labels) - 1;
            $prev = $actual[$n - 1];
            $kpis[$p] = [
                ['label' => 'Omzet Customer Order', 'value' => $actual[$n], 'previous' => $prev * self::RUNNING[$p], 'target' => $target[$n], 'format' => 'money', 'icon' => 'fa-sack-dollar', 'tone' => 'accent', 'spark' => array_slice($actual, -8, 7)],
                ['label' => 'Quotation Terkirim', 'value' => self::round(20 * $f * 0.9 * self::RUNNING[$p] / 0.688), 'previous' => self::round(17 * $f), 'target' => self::round(20 * $f), 'icon' => 'fa-file-signature', 'tone' => 'violet'],
                ['label' => 'Win Rate Quotation', 'value' => 38 - ($f < 1 ? 3 : 0), 'previous' => 34, 'target' => 35, 'format' => 'percent', 'icon' => 'fa-trophy', 'tone' => 'success', 'hint' => 'Target ≥ 35%'],
                ['label' => 'Customer Baru', 'value' => max(0, self::round(3 * $f)), 'previous' => max(1, self::round(4 * $f)), 'target' => max(1, self::round(4 * $f)), 'icon' => 'fa-user-plus', 'tone' => 'teal'],
                ['label' => 'Repeat Order Existing', 'value' => self::round(11 * $f), 'previous' => self::round(9 * $f), 'target' => self::round(12 * $f), 'icon' => 'fa-rotate', 'tone' => 'warn'],
            ];
            $sumA = array_sum($actual);
            $sumT = array_sum($target);
            $trend[$p] = [
                'title' => 'Target vs Pencapaian Omzet',
                'caption' => self::caption($p).' · batang = pencapaian, garis = target',
                'labels' => $labels, 'actual' => $actual, 'target' => $target, 'format' => 'money',
                'source' => 'realisasi: co_order (CONFIRMED) · target: target sales per user',
                'summary' => [
                    ['Pencapaian', 'IDR '.self::compact($sumA)],
                    ['Target', 'IDR '.self::compact($sumT)],
                    ['Rasio', number_format($sumA / $sumT * 100, 1, ',', '.').'%', $sumA >= $sumT ? 'good' : 'warn'],
                    ['Periode tercapai', count(array_filter(array_map(fn ($a, $t) => $a >= $t, array_slice($actual, 0, -1), array_slice($target, 0, -1)))).' dari '.$n],
                ],
            ];
            $act[$p] = self::activity($p, $f, ['call' => 110, 'email' => 80, 'visit' => 20], ['prospect' => .43, 'new' => .23, 'existing' => .34], $now);
        }

        return [
            'profile_label' => 'Dashboard Sales',
            'brief' => [['Visit hari ini', '2'], ['Telepon follow-up', '5'], ['Quotation menunggu jawaban', '3'], ['Sisa hari kerja bulan ini', (string) self::workDaysLeft($now)]],
            'kpis' => $kpis,
            'trend' => $trend,
            'activities' => [
                'title' => 'Aktivitas Sales',
                'caption' => 'Telepon · Email · Visit — mengejar prospek, customer baru, dan menjaga customer existing',
                'segment_label' => 'Sasaran',
                'types' => [
                    ['key' => 'call', 'label' => 'Telepon', 'icon' => 'fa-phone', 'tone' => 'accent'],
                    ['key' => 'email', 'label' => 'Email', 'icon' => 'fa-envelope', 'tone' => 'teal'],
                    ['key' => 'visit', 'label' => 'Visit', 'icon' => 'fa-location-dot', 'tone' => 'warn'],
                ],
                'segments' => [
                    ['key' => 'prospect', 'label' => 'Prospek (belum pernah order)', 'tone' => 'violet'],
                    ['key' => 'new', 'label' => 'Customer baru (order < 6 bulan)', 'tone' => 'teal'],
                    ['key' => 'existing', 'label' => 'Customer existing (maintain)', 'tone' => 'warn'],
                ],
                'data' => $act,
            ],
            'focus' => [
                'title' => 'Fokus Follow-up',
                'caption' => 'Urut dari yang paling lama tidak dihubungi',
                'columns' => ['Customer / Prospek', 'Sasaran', 'Tahap', 'Kontak', 'Terakhir', 'Aksi'],
                'rows' => [
                    ['name' => 'PT Sumber Pangan Lestari', 'sub' => 'Ibu Ratna W. · Purchasing', 'segment' => 'Prospek', 'tone' => 'violet', 'step' => 2, 'stage' => 'Inquiry · IQ2609014', 'counts' => ['fa-phone' => 4, 'fa-envelope' => 2, 'fa-location-dot' => 1], 'last' => '9 hari lalu', 'last_tone' => 'danger', 'action' => 'Telepon', 'primary' => true],
                    ['name' => 'CV Kopi Nusa Jaya', 'sub' => 'Bp. Hendra · Owner', 'segment' => 'Prospek', 'tone' => 'violet', 'step' => 3, 'stage' => 'Quotation · QT2609008', 'counts' => ['fa-phone' => 6, 'fa-envelope' => 4, 'fa-location-dot' => 2], 'last' => '5 hari lalu', 'last_tone' => 'warn', 'action' => 'Visit', 'primary' => true],
                    ['name' => 'PT Anugrah Farma Medika', 'sub' => 'Bp. Dimas · Brand Manager', 'segment' => 'Customer baru', 'tone' => 'success', 'step' => 4, 'stage' => 'CO pertama · CO2608021', 'counts' => ['fa-phone' => 3, 'fa-envelope' => 5, 'fa-location-dot' => 1], 'last' => '4 hari lalu', 'last_tone' => 'warn', 'action' => 'Email'],
                    ['name' => 'PT Mitra Snack Indonesia', 'sub' => 'Ibu Lina · PPIC', 'segment' => 'Existing', 'tone' => 'warn', 'step' => 4, 'stage' => 'Repeat order terakhir 38 hari', 'counts' => ['fa-phone' => 2, 'fa-envelope' => 1, 'fa-location-dot' => 0], 'last' => '3 hari lalu', 'last_tone' => 'neutral', 'action' => 'Telepon'],
                    ['name' => 'UD Berkah Kosmetika', 'sub' => 'Ibu Sari · Pemilik', 'segment' => 'Prospek', 'tone' => 'violet', 'step' => 1, 'stage' => 'Kontak awal', 'counts' => ['fa-phone' => 1, 'fa-envelope' => 1, 'fa-location-dot' => 0], 'last' => '2 hari lalu', 'last_tone' => 'neutral', 'action' => 'Jadwalkan'],
                ],
            ],
            'agenda' => [
                ['time' => '08:15', 'duration' => '15 mnt', 'title' => 'Briefing tim Sales', 'note' => 'Ruang meeting lt. 2', 'tone' => 'neutral', 'done' => true],
                ['time' => '10:00', 'duration' => '1 jam', 'title' => 'Visit · PT Anugrah Farma Medika', 'note' => 'Bawa sampel dus sekunder 2 varian', 'tone' => 'warn'],
                ['time' => '13:30', 'duration' => '30 mnt', 'title' => 'Telepon · CV Kopi Nusa Jaya', 'note' => 'Konfirmasi QT2609008 — nego harga tier 5.000 pcs', 'tone' => 'accent'],
                ['time' => '14:30', 'duration' => '1 jam', 'title' => 'Visit · PT Sumber Pangan Lestari', 'note' => 'Presentasi opsi laminasi doff', 'tone' => 'warn'],
            ],
        ];
    }

    // ---- profil Management (Presiden Direktur, Direktur, General Manager): angka tingkat PERUSAHAAN ------------

    private static function management(CarbonImmutable $now): array
    {
        $kpis = $trend = $targets = $pipeline = $ranking = [];
        foreach (self::SCALE as $p => $f) {
            $run = self::RUNNING[$p];
            $labels = self::labels($p, $now);
            $target = self::series(count($labels), 2.5e9 * $f, 0.05);
            $actual = self::actuals($target, $p, 17);
            $n = count($labels) - 1;
            $kpis[$p] = [
                ['label' => 'Omzet Perusahaan', 'value' => $actual[$n], 'previous' => $actual[$n - 1] * $run, 'target' => $target[$n], 'format' => 'money', 'icon' => 'fa-building', 'tone' => 'accent', 'spark' => array_slice($actual, -8, 7)],
                ['label' => 'Margin Kotor', 'value' => 24.6, 'previous' => 23.1, 'target' => 27, 'format' => 'percent', 'icon' => 'fa-percent', 'tone' => 'violet'],
                ['label' => 'Piutang Jatuh Tempo', 'value' => round(845e6 * min(1, max(0.35, $f / 3)), -6), 'previous' => round(620e6 * min(1, max(0.35, $f / 3)), -6), 'format' => 'money', 'good_when' => 'down', 'icon' => 'fa-file-invoice-dollar', 'tone' => 'danger', 'hint' => 'Naik = perlu perhatian penagihan'],
                ['label' => 'Pengiriman Tepat Waktu', 'value' => 93, 'previous' => 89, 'target' => 95, 'format' => 'percent', 'icon' => 'fa-truck-fast', 'tone' => 'success'],
                ['label' => 'Customer Order Baru', 'value' => self::round(64 * $f * $run / 0.688), 'previous' => self::round(58 * $f), 'target' => self::round(70 * $f), 'icon' => 'fa-bag-shopping', 'tone' => 'teal'],
            ];
            $sumA = array_sum($actual);
            $sumT = array_sum($target);
            $trend[$p] = [
                'title' => 'Target vs Pencapaian Omzet Perusahaan',
                'caption' => self::caption($p).' · batang = pencapaian, garis = target',
                'labels' => $labels, 'actual' => $actual, 'target' => $target, 'format' => 'money',
                'source' => 'realisasi: co_order (CONFIRMED) seluruh sales · target: target perusahaan',
                'summary' => [
                    ['Pencapaian', 'IDR '.self::compact($sumA)],
                    ['Target', 'IDR '.self::compact($sumT)],
                    ['Rasio', number_format($sumA / $sumT * 100, 1, ',', '.').'%', $sumA >= $sumT ? 'good' : 'warn'],
                    ['Periode tercapai', count(array_filter(array_map(fn ($a, $t) => $a >= $t, array_slice($actual, 0, -1), array_slice($target, 0, -1)))).' dari '.$n],
                ],
            ];
            // Target per bidang: satu baris = satu sasaran bisnis perusahaan untuk periode ini.
            $targets[$p] = ['title' => 'Target per Bidang', 'caption' => 'Pencapaian '.mb_strtolower(self::PERIODS[$p]).' berjalan vs target', 'rows' => [
                ['label' => 'Omzet Penjualan', 'sub' => 'Customer Order CONFIRMED', 'actual' => $actual[$n], 'target' => $target[$n], 'format' => 'money', 'icon' => 'fa-sack-dollar', 'tone' => 'accent'],
                ['label' => 'Penagihan Piutang', 'sub' => 'kas masuk dari customer', 'actual' => round(2.3e9 * $f * $run * 1.18, -6), 'target' => round(2.3e9 * $f, -6), 'format' => 'money', 'icon' => 'fa-hand-holding-dollar', 'tone' => 'success'],
                ['label' => 'Output Produksi', 'sub' => 'ribu lembar cetak', 'actual' => self::round(620 * $f * $run * 1.21), 'target' => self::round(620 * $f), 'icon' => 'fa-industry', 'tone' => 'violet'],
                ['label' => 'Pengiriman Tepat Waktu', 'sub' => 'terhadap due date', 'actual' => 93, 'target' => 95, 'format' => 'percent', 'icon' => 'fa-truck-fast', 'tone' => 'teal'],
                ['label' => 'Biaya Bahan / Omzet', 'sub' => 'makin kecil makin baik', 'actual' => 58.4, 'target' => 56, 'format' => 'percent', 'good_when' => 'down', 'icon' => 'fa-layer-group', 'tone' => 'warn'],
                ['label' => 'Customer Baru', 'sub' => 'order pertama', 'actual' => max(0, self::round(9 * $f * $run / 0.688)), 'target' => max(1, self::round(12 * $f)), 'icon' => 'fa-user-plus', 'tone' => 'danger'],
            ]];
            $pipeline[$p] = ['title' => 'Pipeline Penjualan', 'caption' => 'Nilai dokumen '.mb_strtolower(self::PERIODS[$p]).' ini: Inquiry sampai Customer Order', 'format' => 'money', 'series' => 'Nilai',
                'labels' => ['Inquiry masuk', 'Harga terbit (ProCost)', 'Quotation terkirim', 'Customer Order'],
                'values' => array_map(fn ($v) => round($v * $f * $run / 0.688, -6), [6.8e9, 5.6e9, 4.9e9, 1.9e9]),
                'source' => 'iqp_inquiry · iqp_costing (published) · quo_quotation · co_order'];
            $team = [['Firman', 'Sales & Marketing Manager · key account', 1.0e9, 0.72], ['Yuna Puji Astuti', 'Senior Staff Sales & Marketing', 0.6e9, 0.69],
                ['Purna', 'Senior Staff Sales & Marketing', 0.6e9, 0.81], ['Order langsung / online', 'tanpa sales', 0.3e9, 0.55]];
            $ranking[$p] = ['title' => 'Pencapaian Target per Sales', 'caption' => self::PERIODS[$p].' berjalan · urut dari persentase tertinggi', 'name_label' => 'Sales', 'format' => 'money',
                'rows' => collect($team)->map(fn ($t) => ['name' => $t[0], 'sub' => $t[1], 'target' => round($t[2] * $f, -6), 'actual' => round($t[2] * $f * $t[3] * $run / 0.688, -6)])
                    ->sortByDesc(fn ($r) => $r['actual'] / $r['target'])->values()->all()];
        }

        return [
            'profile_label' => 'Dashboard Manajemen',
            'kpi_title' => 'KPI Perusahaan',
            'attendance' => null,
            'brief' => [['Omzet bulan ini', 'IDR '.self::compact($kpis['monthly'][0]['value'])], ['Sisa ke target', 'IDR '.self::compact(max(0, $kpis['monthly'][0]['target'] - $kpis['monthly'][0]['value']))],
                ['Menunggu keputusan Anda', '4'], ['Sisa hari kerja bulan ini', (string) self::workDaysLeft($now)]],
            'kpis' => $kpis,
            'trend' => $trend,
            'targets' => $targets,
            'pipeline' => $pipeline,
            'ranking' => $ranking,
        ];
    }

    // ---- profil General (jabatan tanpa dashboard khusus) --------------------------------------------------

    private static function general(CarbonImmutable $now): array
    {
        $kpis = $trend = $act = [];
        foreach (self::SCALE as $p => $f) {
            $labels = self::labels($p, $now);
            $target = self::series(count($labels), 40 * $f, 0.04, true);
            $actual = self::actuals($target, $p, 5, 0);
            $n = count($labels) - 1;
            $kpis[$p] = [
                ['label' => 'Kehadiran Tepat Waktu', 'value' => 97.5, 'previous' => 96, 'target' => 98, 'format' => 'percent', 'icon' => 'fa-fingerprint', 'tone' => 'success'],
                ['label' => 'Tugas Selesai', 'value' => $actual[$n], 'previous' => $actual[$n - 1] * self::RUNNING[$p], 'target' => $target[$n], 'icon' => 'fa-list-check', 'tone' => 'accent', 'spark' => array_slice($actual, -8, 7)],
                ['label' => 'Selesai Tepat Waktu', 'value' => 91, 'previous' => 88, 'target' => 90, 'format' => 'percent', 'icon' => 'fa-clock', 'tone' => 'violet'],
                ['label' => 'Lembur (jam)', 'value' => self::round(6 * $f, 1), 'previous' => self::round(8 * $f, 1), 'good_when' => 'down', 'icon' => 'fa-moon', 'tone' => 'warn', 'hint' => 'Turun = beban kerja terkendali'],
                ['label' => 'Pelatihan (jam)', 'value' => self::round(2 * $f * self::RUNNING[$p], 1), 'previous' => self::round(2 * $f, 1), 'target' => self::round(2 * $f, 1), 'icon' => 'fa-graduation-cap', 'tone' => 'teal'],
            ];
            $sumA = array_sum($actual);
            $sumT = array_sum($target);
            $trend[$p] = [
                'title' => 'Target vs Tugas Selesai',
                'caption' => self::caption($p).' · batang = selesai, garis = target',
                'labels' => $labels, 'actual' => $actual, 'target' => $target, 'format' => 'number',
                'source' => 'realisasi: tugas/aktivitas selesai · target: target kerja per user',
                'summary' => [
                    ['Selesai', number_format($sumA, 0, ',', '.')],
                    ['Target', number_format($sumT, 0, ',', '.')],
                    ['Rasio', number_format($sumA / $sumT * 100, 1, ',', '.').'%', $sumA >= $sumT ? 'good' : 'warn'],
                ],
            ];
            $act[$p] = self::activity($p, $f, ['task' => 40, 'meeting' => 12, 'report' => 8], ['routine' => .6, 'project' => .25, 'adhoc' => .15], $now);
        }

        return [
            'profile_label' => 'Dashboard',
            'brief' => [['Tugas jatuh tempo hari ini', '3'], ['Rapat', '2'], ['Menunggu persetujuan Anda', '1'], ['Sisa hari kerja bulan ini', (string) self::workDaysLeft($now)]],
            'kpis' => $kpis,
            'trend' => $trend,
            'activities' => [
                'title' => 'Aktivitas Kerja',
                'caption' => 'Tugas · Rapat · Laporan menurut jenis pekerjaan',
                'segment_label' => 'Jenis pekerjaan',
                'types' => [
                    ['key' => 'task', 'label' => 'Tugas', 'icon' => 'fa-list-check', 'tone' => 'accent'],
                    ['key' => 'meeting', 'label' => 'Rapat', 'icon' => 'fa-people-group', 'tone' => 'teal'],
                    ['key' => 'report', 'label' => 'Laporan', 'icon' => 'fa-file-lines', 'tone' => 'warn'],
                ],
                'segments' => [
                    ['key' => 'routine', 'label' => 'Rutin', 'tone' => 'accent'],
                    ['key' => 'project', 'label' => 'Proyek', 'tone' => 'violet'],
                    ['key' => 'adhoc', 'label' => 'Permintaan mendadak', 'tone' => 'danger'],
                ],
                'data' => $act,
            ],
            'focus' => [
                'title' => 'Tugas Prioritas',
                'caption' => 'Urut dari tenggat terdekat',
                'columns' => ['Tugas', 'Jenis', 'Progres', 'Rincian', 'Tenggat', 'Aksi'],
                'rows' => [
                    ['name' => 'Rekap absensi & lembur September', 'sub' => 'Untuk proses payroll', 'segment' => 'Rutin', 'tone' => 'accent', 'step' => 3, 'stage' => '3 dari 4 departemen', 'counts' => ['fa-list-check' => '3/4', 'fa-paperclip' => 2], 'last' => 'Hari ini', 'last_tone' => 'danger', 'action' => 'Buka', 'primary' => true],
                    ['name' => 'Jadwal interview kandidat Operator Cetak', 'sub' => '5 kandidat lolos seleksi berkas', 'segment' => 'Proyek', 'tone' => 'violet', 'step' => 2, 'stage' => 'Menunggu konfirmasi user', 'counts' => ['fa-list-check' => '2/5', 'fa-paperclip' => 5], 'last' => 'Besok', 'last_tone' => 'warn', 'action' => 'Buka', 'primary' => true],
                    ['name' => 'Perpanjangan kontrak 3 karyawan', 'sub' => 'Habis kontrak 31 Okt 2026', 'segment' => 'Rutin', 'tone' => 'accent', 'step' => 1, 'stage' => 'Draf', 'counts' => ['fa-list-check' => '0/3', 'fa-paperclip' => 0], 'last' => '3 hari lagi', 'last_tone' => 'warn', 'action' => 'Buka'],
                    ['name' => 'Data BPJS pegawai baru', 'sub' => 'Permintaan Bagian Keuangan', 'segment' => 'Mendadak', 'tone' => 'danger', 'step' => 1, 'stage' => 'Belum mulai', 'counts' => ['fa-list-check' => '0/2', 'fa-paperclip' => 1], 'last' => '5 hari lagi', 'last_tone' => 'neutral', 'action' => 'Buka'],
                ],
            ],
            'agenda' => [
                ['time' => '08:30', 'duration' => '30 mnt', 'title' => 'Koordinasi Human Capital', 'note' => 'Ruang HR', 'tone' => 'neutral', 'done' => true],
                ['time' => '10:00', 'duration' => '1 jam', 'title' => 'Interview kandidat Operator Cetak', 'note' => '2 kandidat · bersama Kepala Produksi', 'tone' => 'violet'],
                ['time' => '14:00', 'duration' => '1 jam', 'title' => 'Sosialisasi SOP Cuti', 'note' => 'Departemen Gudang', 'tone' => 'accent'],
            ],
        ];
    }

    // ---- pembantu -----------------------------------------------------------------------------------------

    private static function activity(string $p, float $f, array $monthly, array $split, CarbonImmutable $now): array
    {
        $run = self::RUNNING[$p];
        $target = array_map(fn ($v) => max(1, (int) round($v * $f)), $monthly);
        $matrix = [];
        $i = 0;
        foreach ($split as $seg => $share) {
            foreach ($target as $type => $t) {
                $matrix[$seg][$type] = (int) round($t * $share * $run * (0.85 + self::noise(++$i + strlen($p)) * 0.35));
            }
        }
        [$title, $labels] = match ($p) {
            'daily', 'weekly' => ['Rincian minggu ini', ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']],
            'monthly' => ['Rincian per minggu', ['Mg 1', 'Mg 2', 'Mg 3', 'Mg 4', 'Mg 5']],
            default => ['Rincian per bulan', array_slice(self::monthLabels($now, 12), -min(12, (int) round($f)))],
        };
        $week = ['title' => $title, 'labels' => $labels];
        $today = $p === 'monthly' ? 4 : ($p === 'daily' || $p === 'weekly' ? $now->dayOfWeekIso - 1 : count($labels) - 1);
        foreach ($target as $type => $t) {
            $per = array_sum(array_column($matrix, $type)) / max(1, $today + 1);
            $week[$type] = array_map(fn ($k) => $k > $today ? 0 : (int) round($per * (0.7 + self::noise($k * 7 + strlen($type)) * 0.6)), array_keys($labels));
        }

        return ['target' => $target, 'matrix' => $matrix, 'week' => $week];
    }

    // Label sumbu per periode: hari kerja, minggu, bulan, kuartal, semester, tahun (bucket terakhir = berjalan).
    private static function labels(string $p, CarbonImmutable $now): array
    {
        return match ($p) {
            'daily' => collect(range(20, 0))->map(fn ($i) => $now->subDays($i))->reject(fn ($d) => $d->isSunday())->take(-14)->map(fn ($d) => $d->format('j/n'))->values()->all(),
            'weekly' => collect(range(11, 0))->map(fn ($i) => 'Mg '.$now->subWeeks($i)->isoWeek())->all(),
            'monthly' => self::monthLabels($now, 12),
            'q3' => collect(range(7, 0))->map(fn ($i) => 'Q'.$now->subQuarters($i)->quarter.' '.$now->subQuarters($i)->format('y'))->all(),
            'q6' => collect(range(5, 0))->map(fn ($i) => ($now->subMonths($i * 6)->month <= 6 ? 'S1 ' : 'S2 ').$now->subMonths($i * 6)->format('y'))->all(),
            default => collect(range(3, 0))->map(fn ($i) => (string) $now->subYears($i)->year)->all(),
        };
    }

    private static function monthLabels(CarbonImmutable $now, int $n): array
    {
        $bln = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        return collect(range($n - 1, 0))->map(fn ($i) => $bln[$now->subMonthsNoOverflow($i)->month])->all();
    }

    private static function caption(string $p): string
    {
        return ['daily' => '14 hari kerja terakhir', 'weekly' => '12 minggu terakhir', 'monthly' => '12 bulan terakhir',
            'q3' => '8 kuartal terakhir', 'q6' => '6 semester terakhir', 'q12' => '4 tahun terakhir'][$p];
    }

    // Target naik bertahap (versi target berubah tiap beberapa periode).
    private static function series(int $n, float $base, float $step, bool $int = false): array
    {
        return array_map(fn ($i) => $int ? round($base * (1 + $step * intdiv($i, 4))) : round($base * (1 + $step * intdiv($i, 4)), -5), range(0, $n - 1));
    }

    private static function actuals(array $target, string $p, int $seed, int $precision = -4): array
    {
        $out = array_map(fn ($t, $i) => $t * (0.82 + self::noise($i * 3 + $seed + strlen($p)) * 0.33), $target, array_keys($target));
        $out[count($out) - 1] = end($target) * self::RUNNING[$p];

        return array_map(fn ($v) => round($v, $precision), $out);
    }

    private static function noise(int $i): float
    {
        $x = sin($i * 12.9898) * 43758.5453;

        return $x - floor($x);
    }

    private static function round(float $v, int $dec = 0): float|int
    {
        return $dec ? round($v, $dec) : (int) round($v);
    }

    private static function compact(float $v): string
    {
        return Slider::compact($v);
    }

    private static function workDaysLeft(CarbonImmutable $now): int
    {
        $n = 0;
        for ($d = $now->addDay(); $d->month === $now->month; $d = $d->addDay()) {
            $n += $d->isSunday() ? 0 : 1;
        }

        return $n;
    }
}
