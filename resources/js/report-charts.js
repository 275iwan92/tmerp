import ApexCharts from 'apexcharts';

// Format angka gaya Indonesia ringkas (12,3 jt / 1,2 M) -- dipakai seragam
// utk sumbu-Y, tooltip, dan nilai per-segmen donut di SEMUA chart laporan,
// menggantikan formatter PHP per-komponen yg lama (kadang asumsi salah
// skala, mis. svg-bars lama selalu bagi 1 juta walau datanya cacah WO).
function fmtIdCompact(v) {
    v = Number(v) || 0;
    const abs = Math.abs(v);
    if (abs >= 1e9) return (v / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' M';
    if (abs >= 1e6) return (v / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt';
    if (abs >= 1e3) return v.toLocaleString('id-ID', { maximumFractionDigits: 0 });

    return Number.isInteger(v) ? String(v) : v.toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

// Dipakai komponen resources/views/components/report/{svg-bars,svg-trend,svg-donut}.blade.php
// (rename bertahap ke ApexCharts, keputusan user 2026-09-13: "graphics
// jelek dan kaku" -- SVG hand-rolled server-side sebelumnya diganti chart
// library sungguhan). Setiap komponen render <div wire:ignore
// x-data="reportChart(@js($options))" x-init="init($el)"> -- wire:ignore
// WAJIB supaya Livewire morph tidak menyentuh DOM yg dimanipulasi Apex;
// wire:key di elemen dibuat dari hash(data) di sisi Blade supaya kalau
// data laporan berubah (mis. ganti filter Tahun), Livewire memperlakukan-
// nya sbg elemen BARU (bukan morph) -- x-init jalan lagi dgn opsi baru,
// tanpa perlu $watch/entangle manual.
window.reportChart = function (options) {
    return {
        chart: null,
        init(el) {
            const opts = JSON.parse(JSON.stringify(options));
            const isDonut = opts.chart?.type === 'donut' || opts.chart?.type === 'pie';

            opts.tooltip = opts.tooltip || {};
            if (isDonut) {
                opts.tooltip.y = { formatter: (v) => fmtIdCompact(v) };
            } else {
                opts.tooltip.y = { formatter: (v) => fmtIdCompact(v) };
                opts.yaxis = opts.yaxis || {};
                opts.yaxis.labels = opts.yaxis.labels || {};
                opts.yaxis.labels.formatter = (v) => fmtIdCompact(v);
            }

            if (isDonut && opts.custom) {
                opts.plotOptions = opts.plotOptions || {};
                opts.plotOptions.pie = opts.plotOptions.pie || {};
                opts.plotOptions.pie.donut = opts.plotOptions.pie.donut || {};
                opts.plotOptions.pie.donut.labels = {
                    show: true,
                    total: {
                        show: true,
                        label: opts.custom.centerLabel || 'Total',
                        formatter: () => opts.custom.centerValue || '',
                    },
                    value: { formatter: (v) => fmtIdCompact(v) },
                };
            }
            delete opts.custom;

            this.chart = new ApexCharts(el, opts);
            this.chart.render();
        },
        destroy() {
            this.chart?.destroy();
        },
    };
};
