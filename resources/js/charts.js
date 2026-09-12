import ApexCharts from 'apexcharts';

// ApexCharts + Livewire: chart HARUS hidup di luar siklus morph Livewire.
// Pola yang dipakai komponen <x-chart.apex>: container ber-wire:ignore, chart
// dibuat sekali saat init, lalu diperbarui lewat updateOptions/updateSeries --
// BUKAN dirender ulang dari nol. Kalau container ikut di-morph Livewire,
// instance chart-nya kehilangan node dan grafik jadi kosong tanpa error.
window.ApexCharts = ApexCharts;

// Preset tampilan supaya semua grafik di aplikasi konsisten -- warna, font,
// dan format angka Rupiah didefinisikan sekali di sini, bukan diulang di tiap
// layar (dan supaya ganti tema cukup diubah satu tempat).
window.tmChartTheme = {
    palette: ['#2563eb', '#dc2626', '#16a34a', '#7c3aed', '#ea580c', '#0891b2', '#db2777', '#ca8a04'],

    formatRupiah(value, compact = true) {
        const n = Number(value) || 0;
        if (!compact) return 'Rp ' + n.toLocaleString('id-ID');
        const abs = Math.abs(n);
        if (abs >= 1e12) return 'Rp ' + (n / 1e12).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' T';
        if (abs >= 1e9) return 'Rp ' + (n / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' M';
        if (abs >= 1e6) return 'Rp ' + (n / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 0 }) + ' jt';
        if (abs >= 1e3) return 'Rp ' + (n / 1e3).toLocaleString('id-ID', { maximumFractionDigits: 0 }) + ' rb';
        return 'Rp ' + n.toLocaleString('id-ID');
    },

    base() {
        return {
            chart: {
                fontFamily: 'Instrument Sans, ui-sans-serif, system-ui, sans-serif',
                toolbar: { show: false },
                animations: { enabled: true, speed: 420, animateGradually: { enabled: false } },
                parentHeightOffset: 0,
            },
            colors: this.palette,
            grid: {
                borderColor: 'rgba(0,0,0,0.07)',
                strokeDashArray: 4,
                padding: { left: 4, right: 8, top: 0 },
            },
            dataLabels: { enabled: false },
            legend: {
                position: 'top',
                horizontalAlign: 'center',
                fontSize: '12px',
                markers: { width: 9, height: 9, radius: 9 },
                itemMargin: { horizontal: 10 },
            },
            tooltip: {
                shared: true,
                intersect: false,
                style: { fontSize: '12px' },
                y: { formatter: (v) => this.formatRupiah(v, false) },
            },
            yaxis: {
                labels: {
                    style: { fontSize: '11px', colors: 'rgba(0,0,0,0.55)' },
                    formatter: (v) => this.formatRupiah(v),
                },
            },
            xaxis: {
                labels: { style: { fontSize: '11px', colors: 'rgba(0,0,0,0.55)' } },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
        };
    },

    // Penggabungan dangkal per-kunci sudah cukup: opsi yang dikirim layar
    // selalu berupa blok utuh (mis. seluruh objek `xaxis`), bukan tambalan
    // sebagian -- jadi tidak perlu deep-merge yang justru menyulitkan
    // penimpaan nilai default.
    merge(overrides) {
        const base = this.base();
        const out = { ...base };
        for (const key of Object.keys(overrides || {})) {
            out[key] = (overrides[key] && typeof overrides[key] === 'object' && !Array.isArray(overrides[key]))
                ? { ...(base[key] || {}), ...overrides[key] }
                : overrides[key];
        }
        return out;
    },
};
