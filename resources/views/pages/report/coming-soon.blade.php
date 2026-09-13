<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// QMS/CMMS/EHS/Assets & Capital -- 4 menu ADA di sidebar mockup
// (printflow_erp_complete_dashboard_mockup.html) tapi modul sumber
// datanya BELUM dibangun di ERP ini. Keputusan awal (2026-09-13) tampilkan
// pesan jujur "belum tersedia" tanpa angka -- user MEMBATALKAN keputusan
// itu secara eksplisit: "KALAU TIDAK ADA TETAP DIBUAT DUMMY DATA NYA,
// DENGAN TULISAN INI DUMMY DATA". Jadi sekarang: tampilkan dashboard
// lengkap (KPI+grafik+tabel) persis gaya mockup, ANGKA DIAMBIL APA ADANYA
// dari contoh di mockup asli (bukan dikarang baru), TAPI diberi banner
// "DATA DUMMY" yang jelas & permanen di atas + badge di header -- supaya
// tidak ada satupun angka yang bisa disalahartikan sebagai data produksi
// sungguhan.
new #[Layout('layouts::report')] #[Title('Laporan — TELASIH MAKMUR ERP')] class extends Component
{
    public string $slug = '';

    protected function datasets(): array
    {
        return [
            'qms' => [
                'title' => 'Quality Management System',
                'subtitle' => 'Kepatuhan spesifikasi, konsistensi warna, defect containment, dan efektivitas CAPA.',
                'kpis' => [
                    ['label' => 'Reject Bahan Masuk', 'icon' => 'fa-solid fa-triangle-exclamation', 'tone' => 'amber', 'value' => '0,84%', 'note' => 'Target ≤ 1,0%'],
                    ['label' => 'Defect In-Process', 'icon' => 'fa-solid fa-circle-exclamation', 'tone' => 'blue', 'value' => '2,17%', 'note' => 'Turun 0,3 pts vs FY sebelumnya'],
                    ['label' => 'Customer PPM', 'icon' => 'fa-solid fa-check', 'tone' => 'green', 'value' => '1.148', 'note' => 'Target ≤ 1.500 PPM'],
                    ['label' => 'Temuan Audit Terbuka', 'icon' => 'fa-solid fa-clipboard-list', 'tone' => 'violet', 'value' => '6', 'note' => '2 diantaranya mayor'],
                ],
                'chart_title' => 'Defect Pareto (YTD)',
                'chart' => [
                    ['label' => 'Variasi warna/shade', 'pct' => 31, 'color' => '#e64b52'],
                    ['label' => 'Registrasi die-cut', 'pct' => 24, 'color' => '#f09a22'],
                    ['label' => 'Scuffing/coating', 'pct' => 18, 'color' => '#8358e8'],
                    ['label' => 'Kegagalan lem', 'pct' => 15, 'color' => '#2878f0'],
                    ['label' => 'Lain-lain', 'pct' => 12, 'color' => '#11a879'],
                ],
                'table_title' => 'CAPA & Complaint Tracker',
                'table_cols' => ['Kasus', 'Sumber', 'Umur', 'Status'],
                'table_rows' => [
                    ['CAPA-26-041', 'Variasi shade', '21 hari', 'warn', 'Verifikasi'],
                    ['CC-26-083', 'Kegagalan lem', '12 hari', 'info', 'Containment'],
                    ['CAPA-26-038', 'Die-cut drift', '35 hari', 'danger', 'Terlambat'],
                    ['CC-26-079', 'Baret kertas', '8 hari', 'ok', 'Ditutup'],
                ],
            ],
            'cmms' => [
                'title' => 'CMMS Maintenance',
                'subtitle' => 'Ketersediaan mesin lewat preventive maintenance, analisa breakdown, dan spare-parts.',
                'kpis' => [
                    ['label' => 'MTBF', 'icon' => 'fa-solid fa-arrow-trend-up', 'tone' => 'green', 'value' => '184 jam', 'note' => 'Naik 13% vs FY sebelumnya'],
                    ['label' => 'MTTR', 'icon' => 'fa-solid fa-clock', 'tone' => 'amber', 'value' => '2,8 jam', 'note' => 'Turun 0,6 jam vs FY sebelumnya'],
                    ['label' => 'Spare Part Kritis', 'icon' => 'fa-solid fa-boxes-stacked', 'tone' => 'blue', 'value' => '6 SKU', 'note' => '2 dibawah stok minimum'],
                    ['label' => 'Pekerjaan Darurat', 'icon' => 'fa-solid fa-bolt', 'tone' => 'violet', 'value' => '17,4%', 'note' => 'Target < 15%'],
                ],
                'chart_title' => 'Ketersediaan Mesin',
                'chart' => [
                    ['label' => 'Heidelberg XL 106', 'pct' => 94.8, 'color' => '#11a879'],
                    ['label' => 'Komori GL-640', 'pct' => 92.1, 'color' => '#f09a22'],
                    ['label' => 'Bobst Novacut', 'pct' => 89.7, 'color' => '#f09a22'],
                    ['label' => 'Folder Gluer FG-01', 'pct' => 84.3, 'color' => '#e64b52'],
                ],
                'table_title' => 'Jadwal Maintenance Terdekat',
                'table_cols' => ['Kegiatan', 'Jadwal', 'Sifat', 'Status'],
                'table_rows' => [
                    ['Inspeksi blanket & roller XL 106', '14 Sep · 4 jam', 'Planned', 'info', 'Terjadwal'],
                    ['Penggantian sensor FG-01', '15 Sep', 'Corrective', 'warn', 'Prioritas'],
                    ['Lubrikasi PM Novacut', '17 Sep · 3 jam', 'Planned', 'info', 'Terjadwal'],
                    ['Alarm getaran kompresor udara', 'Segera', 'Corrective', 'danger', 'Investigasi'],
                ],
            ],
            'ehs' => [
                'title' => 'Environment, Health & Safety',
                'subtitle' => 'Keselamatan kerja, risiko kimia, kepatuhan lingkungan, limbah, dan insiden.',
                'kpis' => [
                    ['label' => 'Hari Tanpa Kecelakaan Kerja', 'icon' => 'fa-solid fa-shield-heart', 'tone' => 'green', 'value' => '418 hari', 'note' => 'TRIR 0,42'],
                    ['label' => 'Near Miss Dilaporkan', 'icon' => 'fa-solid fa-triangle-exclamation', 'tone' => 'amber', 'value' => '27', 'note' => 'Budaya pelaporan meningkat'],
                    ['label' => 'Tindakan Korektif Terbuka', 'icon' => 'fa-solid fa-list-check', 'tone' => 'violet', 'value' => '13', 'note' => '4 terlambat'],
                    ['label' => 'Kepatuhan Izin Lingkungan', 'icon' => 'fa-solid fa-file-shield', 'tone' => 'blue', 'value' => '97,2%', 'note' => '1 izin perlu diperpanjang'],
                ],
                'chart_title' => 'Kinerja Lingkungan (YTD vs Target Internal)',
                'chart' => [
                    ['label' => 'Limbah kertas didaur ulang', 'pct' => 96.8, 'color' => '#11a879'],
                    ['label' => 'Inspeksi kontrol VOC', 'pct' => 92.0, 'color' => '#2878f0'],
                    ['label' => 'Penutupan manifest limbah B3', 'pct' => 88.5, 'color' => '#f09a22'],
                ],
                'table_title' => 'EHS Risk Register',
                'table_cols' => ['Risiko', 'Area', 'Penanggung Jawab', 'Status'],
                'table_rows' => [
                    ['Paparan uap pelarut', 'Ruang tinta', 'EHS', 'warn', 'Rencana tindakan'],
                    ['Jalur forklift-pejalan kaki', 'Gudang', 'Manajer Gudang', 'danger', 'Terlambat'],
                    ['Pengaman mesin', 'FG-01', 'Maintenance', 'warn', 'Verifikasi'],
                    ['Servis APAR', 'Seluruh pabrik', 'Facilities', 'ok', 'Berlaku'],
                ],
            ],
            'assets' => [
                'title' => 'Assets & Capital',
                'subtitle' => 'Aset tetap, depresiasi, rencana siklus hidup, dan belanja modal.',
                'kpis' => [
                    ['label' => 'Nilai Buku Bersih (NBV)', 'icon' => 'fa-solid fa-building-columns', 'tone' => 'blue', 'value' => 'Rp 126,7 M', 'note' => 'Dari Rp 238,4 M nilai perolehan'],
                    ['label' => 'Utilisasi Aset', 'icon' => 'fa-solid fa-gauge-high', 'tone' => 'green', 'value' => '78,9%', 'note' => 'Naik 3,4 pts vs FY sebelumnya'],
                    ['label' => 'Capex Committed', 'icon' => 'fa-solid fa-coins', 'tone' => 'amber', 'value' => 'Rp 12,1 M', 'note' => '65% dari rencana disetujui'],
                    ['label' => 'Kandidat Pelepasan Aset', 'icon' => 'fa-solid fa-box-archive', 'tone' => 'violet', 'value' => '4 aset', 'note' => 'NBV Rp 0,74 M'],
                ],
                'chart_title' => 'Portofolio Proyek Modal',
                'chart' => [
                    ['label' => 'Upgrade otomasi FG-01', 'pct' => 62, 'color' => '#f09a22'],
                    ['label' => 'Rollout barcode gudang', 'pct' => 81, 'color' => '#11a879'],
                    ['label' => 'Penggantian lini CTP', 'pct' => 37, 'color' => '#2878f0'],
                    ['label' => 'Solar rooftop fase 1', 'pct' => 18, 'color' => '#2878f0'],
                ],
                'table_title' => 'Risiko Siklus Hidup Aset',
                'table_cols' => ['Indikator', 'Jumlah', '', 'Status'],
                'table_rows' => [
                    ['Aset melewati umur ekonomis', '8 aset', '', 'warn', 'Perlu review'],
                    ['Rasio biaya maintenance thd NBV tinggi', '3 aset', '', 'danger', 'Perhatian'],
                    ['Review valuasi asuransi jatuh tempo', '12 aset', '', 'info', 'Terjadwal'],
                    ['Capex benefit tracking selesai', '71,4%', '', 'ok', 'Berjalan'],
                ],
            ],
        ];
    }

    public function mount(string $slug): void
    {
        $this->slug = array_key_exists($slug, $this->datasets()) ? $slug : 'qms';
    }

    public function getDataProperty(): array
    {
        return $this->datasets()[$this->slug];
    }
};
?>

<div class="p-4 sm:p-6" style="background:var(--content-bg)">
    <div class="mb-4 rounded-xl px-4 py-2.5 flex items-center gap-2.5" style="background:color-mix(in srgb, var(--sh-warn) 14%, transparent);border:1px solid color-mix(in srgb, var(--sh-warn) 40%, transparent)">
        <i class="fa-solid fa-flask-vial" style="color:var(--sh-warn)"></i>
        <span class="text-[12px] font-semibold" style="color:var(--sh-ink-strong)">DATA DUMMY —</span>
        <span class="text-[12px]" style="color:var(--sh-ink)">modul sumber data untuk laporan ini belum dibangun di ERP. Semua angka di bawah adalah contoh ilustrasi, bukan data produksi CV. Telasih Makmur.</span>
    </div>

    <div class="mb-5">
        <h1 class="text-[22px] font-extrabold -tracking-wide" style="color:var(--sh-ink-strong)">{{ $this->data['title'] }} <x-report.badge tone="warn">DUMMY</x-report.badge></h1>
        <p class="text-[13px] mt-0.5" style="color:var(--sh-muted)">{{ $this->data['subtitle'] }}</p>
    </div>

    <div class="[display:grid] grid-cols-2 lg:grid-cols-4 gap-3.5 mb-4">
        @foreach ($this->data['kpis'] as $k)
            <x-report.kpi :label="$k['label']" :icon="$k['icon']" :tone="$k['tone']" :value="$k['value']" :note="$k['note']" />
        @endforeach
    </div>

    <div class="rounded-2xl border p-4 mb-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
        <h2 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">{{ $this->data['chart_title'] }}</h2>
        @foreach ($this->data['chart'] as $row)
            <x-report.progress-row :label="$row['label']" :pct="$row['pct']" :value="number_format($row['pct'], 1).'%'" :color="$row['color']" />
        @endforeach
    </div>

    <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
        <h2 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">{{ $this->data['table_title'] }}</h2>
        <table class="w-full text-[12px]">
            <thead>
                <tr class="text-left border-b" style="border-color:var(--sh-border)">
                    @foreach ($this->data['table_cols'] as $col)
                        <th class="pb-2 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($this->data['table_rows'] as $row)
                    <tr class="border-b" style="border-color:var(--sh-border)">
                        <td class="py-2 font-semibold" style="color:var(--sh-ink-strong)">{{ $row[0] }}</td>
                        <td class="py-2" style="color:var(--sh-muted)">{{ $row[1] }}</td>
                        <td class="py-2" style="color:var(--sh-muted)">{{ $row[2] }}</td>
                        <td class="py-2 text-right"><x-report.badge :tone="$row[3]">{{ $row[4] }}</x-report.badge></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
