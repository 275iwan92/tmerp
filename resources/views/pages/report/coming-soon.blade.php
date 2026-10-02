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
new #[Layout('layouts::report')] #[Title('Reports — TELASIH MAKMUR ERP')] class extends Component
{
    public string $slug = '';

    protected function datasets(): array
    {
        return [
            'qms' => [
                'title' => t('Quality Management System'),
                'subtitle' => t('Specification compliance, color consistency, defect containment, and CAPA effectiveness.'),
                'kpis' => [
                    ['label' => t('Incoming Material Reject'), 'icon' => 'fa-solid fa-triangle-exclamation', 'tone' => 'amber', 'value' => '0,84%', 'note' => 'Target ≤ 1,0%'],
                    ['label' => t('Defect In-Process'), 'icon' => 'fa-solid fa-circle-exclamation', 'tone' => 'blue', 'value' => '2,17%', 'note' => t('Down 0.3 pts vs previous FY')],
                    ['label' => t('Customer PPM'), 'icon' => 'fa-solid fa-check', 'tone' => 'green', 'value' => '1.148', 'note' => 'Target ≤ 1.500 PPM'],
                    ['label' => t('Open Audit Findings'), 'icon' => 'fa-solid fa-clipboard-list', 'tone' => 'violet', 'value' => '6', 'note' => t('2 of them major')],
                ],
                'chart_title' => t('Defect Pareto (YTD)'),
                'chart' => [
                    ['label' => t('Color/shade variation'), 'pct' => 31, 'color' => '#e64b52'],
                    ['label' => t('Die-cut registration'), 'pct' => 24, 'color' => '#f09a22'],
                    ['label' => t('Scuffing/coating'), 'pct' => 18, 'color' => '#8358e8'],
                    ['label' => t('Glue failure'), 'pct' => 15, 'color' => '#2878f0'],
                    ['label' => t('Others'), 'pct' => 12, 'color' => '#11a879'],
                ],
                'table_title' => t('CAPA & Complaint Tracker'),
                'table_cols' => [t('Case'), t('Source'), t('Case age'), t('Status')],
                'table_rows' => [
                    ['CAPA-26-041', t('Shade variation'), t('21 days'), 'warn', t('Verification')],
                    ['CC-26-083', t('Glue failure'), t('12 days'), 'info', t('Containment')],
                    ['CAPA-26-038', t('Die-cut drift'), t('35 days'), 'danger', t('Late')],
                    ['CC-26-079', t('Paper scratches'), t('8 days'), 'ok', t('Closed')],
                ],
            ],
            'cmms' => [
                'title' => t('CMMS Maintenance'),
                'subtitle' => t('Machine availability through preventive maintenance, breakdown analysis, and spare parts.'),
                'kpis' => [
                    ['label' => t('MTBF'), 'icon' => 'fa-solid fa-arrow-trend-up', 'tone' => 'green', 'value' => t('184 hours'), 'note' => t('Up 13% vs previous FY')],
                    ['label' => t('MTTR'), 'icon' => 'fa-solid fa-clock', 'tone' => 'amber', 'value' => t('2.8 hours'), 'note' => t('Down 0.6 hours vs previous FY')],
                    ['label' => t('Critical Spare Parts'), 'icon' => 'fa-solid fa-boxes-stacked', 'tone' => 'blue', 'value' => '6 SKU', 'note' => t('2 below minimum stock')],
                    ['label' => t('Emergency Work'), 'icon' => 'fa-solid fa-bolt', 'tone' => 'violet', 'value' => '17,4%', 'note' => 'Target < 15%'],
                ],
                'chart_title' => t('Machine Availability'),
                'chart' => [
                    ['label' => 'Heidelberg XL 106', 'pct' => 94.8, 'color' => '#11a879'],
                    ['label' => 'Komori GL-640', 'pct' => 92.1, 'color' => '#f09a22'],
                    ['label' => 'Bobst Novacut', 'pct' => 89.7, 'color' => '#f09a22'],
                    ['label' => 'Folder Gluer FG-01', 'pct' => 84.3, 'color' => '#e64b52'],
                ],
                'table_title' => t('Upcoming Maintenance Schedule'),
                'table_cols' => [t('Activity'), t('Schedule'), t('Nature'), t('Status')],
                'table_rows' => [
                    [t('XL 106 blanket & roller inspection'), t('14 Sep · 4 hours'), t('Planned'), 'info', t('Scheduled')],
                    [t('FG-01 sensor replacement'), '15 Sep', t('Corrective'), 'warn', t('Priority')],
                    [t('Novacut PM lubrication'), t('17 Sep · 3 hours'), t('Planned'), 'info', t('Scheduled')],
                    [t('Air compressor vibration alarm'), t('Immediately'), t('Corrective'), 'danger', t('Investigation')],
                ],
            ],
            'ehs' => [
                'title' => t('Environment, Health & Safety'),
                'subtitle' => t('Occupational safety, chemical risk, environmental compliance, waste, and incidents.'),
                'kpis' => [
                    ['label' => t('Days Without Workplace Accident'), 'icon' => 'fa-solid fa-shield-heart', 'tone' => 'green', 'value' => t('418 days'), 'note' => 'TRIR 0,42'],
                    ['label' => t('Near Misses Reported'), 'icon' => 'fa-solid fa-triangle-exclamation', 'tone' => 'amber', 'value' => '27', 'note' => t('Reporting culture improving')],
                    ['label' => t('Open Corrective Actions'), 'icon' => 'fa-solid fa-list-check', 'tone' => 'violet', 'value' => '13', 'note' => t('4 late')],
                    ['label' => t('Environmental Permit Compliance'), 'icon' => 'fa-solid fa-file-shield', 'tone' => 'blue', 'value' => '97,2%', 'note' => t('1 permit needs renewal')],
                ],
                'chart_title' => t('Environmental Performance (YTD vs Internal Target)'),
                'chart' => [
                    ['label' => t('Paper waste recycled'), 'pct' => 96.8, 'color' => '#11a879'],
                    ['label' => t('VOC control inspection'), 'pct' => 92.0, 'color' => '#2878f0'],
                    ['label' => t('Hazardous waste manifest closure'), 'pct' => 88.5, 'color' => '#f09a22'],
                ],
                'table_title' => t('EHS Risk Register'),
                'table_cols' => [t('Risk'), t('Area'), t('Person in Charge'), t('Status')],
                'table_rows' => [
                    [t('Solvent vapor exposure'), t('Ink room'), t('EHS'), 'warn', t('Action plan')],
                    [t('Forklift-pedestrian lane'), t('Warehouse'), t('Warehouse Manager'), 'danger', t('Late')],
                    [t('Machine guarding'), 'FG-01', t('Maintenance'), 'warn', t('Verification')],
                    [t('Fire extinguisher service'), t('Entire factory'), t('Facilities'), 'ok', t('Valid')],
                ],
            ],
            'assets' => [
                'title' => t('Assets & Capital'),
                'subtitle' => t('Fixed assets, depreciation, lifecycle planning, and capital expenditure.'),
                'kpis' => [
                    ['label' => t('Net Book Value (NBV)'), 'icon' => 'fa-solid fa-building-columns', 'tone' => 'blue', 'value' => 'Rp 126,7 M', 'note' => t('Of Rp 238.4 B acquisition cost')],
                    ['label' => t('Asset Utilization'), 'icon' => 'fa-solid fa-gauge-high', 'tone' => 'green', 'value' => '78,9%', 'note' => t('Up 3.4 pts vs previous FY')],
                    ['label' => t('Capex Committed'), 'icon' => 'fa-solid fa-coins', 'tone' => 'amber', 'value' => 'Rp 12,1 M', 'note' => t('65% of the approved plan')],
                    ['label' => t('Asset Disposal Candidates'), 'icon' => 'fa-solid fa-box-archive', 'tone' => 'violet', 'value' => t('4 assets'), 'note' => 'NBV Rp 0,74 M'],
                ],
                'chart_title' => t('Capital Project Portfolio'),
                'chart' => [
                    ['label' => t('FG-01 automation upgrade'), 'pct' => 62, 'color' => '#f09a22'],
                    ['label' => t('Warehouse barcode rollout'), 'pct' => 81, 'color' => '#11a879'],
                    ['label' => t('CTP line replacement'), 'pct' => 37, 'color' => '#2878f0'],
                    ['label' => t('Solar rooftop phase 1'), 'pct' => 18, 'color' => '#2878f0'],
                ],
                'table_title' => t('Asset Lifecycle Risks'),
                'table_cols' => [t('Indicator'), t('Count'), '', t('Status')],
                'table_rows' => [
                    [t('Assets past their economic life'), t('8 assets'), '', 'warn', t('Needs review')],
                    [t('High maintenance cost to NBV ratio'), t('3 assets'), '', 'danger', t('Attention')],
                    [t('Insurance valuation review due'), t('12 assets'), '', 'info', t('Scheduled')],
                    [t('Capex benefit tracking completed'), '71,4%', '', 'ok', t('In Progress')],
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
        <span class="text-[12px] font-semibold" style="color:var(--sh-ink-strong)">{{ t('DUMMY DATA') }} —</span>
        <span class="text-[12px]" style="color:var(--sh-ink)">{{ t('the source data module for this report has not been built in the ERP yet.') }} {{ t('All figures below are illustrative examples, not production data of') }} CV. Telasih Makmur.</span>
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
