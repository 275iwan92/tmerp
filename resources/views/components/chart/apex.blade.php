{{--
    Pembungkus ApexCharts untuk Livewire.

    POLA YANG DIPAKAI (jangan "dirapikan" jadi salah satu saja -- keduanya
    diperlukan bersama):
      1. `wire:ignore` pada node tempat ApexCharts menggambar. Livewire
         me-morph DOM tiap properti berubah; kalau SVG hasil ApexCharts ikut
         di-morph, grafik rusak/hilang TANPA error di console -- varian dari
         jebakan morph yang sudah pernah menimpa proyek ini.
      2. `wire:key` pada pembungkus LUAR yang ikut berubah saat datanya
         berubah. wire:ignore menahan morph, jadi tanpa ini grafik akan
         membeku memakai data lama saat filter diganti. Key yang berubah
         memaksa Livewire mengganti seluruh node -> Alpine init ulang ->
         chart digambar dgn data baru.

    :config -> array opsi ApexCharts (series, xaxis, dst). `chart.type` dan
               `chart.height` diisi lewat prop terpisah agar seragam.
--}}
@props([
    'config' => [],
    'type' => 'line',
    'height' => 300,
])
@php
    $payload = array_merge($config, [
        'chart' => array_merge($config['chart'] ?? [], [
            'type' => $type,
            'height' => $height,
        ]),
    ]);
    // Key ikut berubah begitu isi data berubah -- lihat alasan di blok komentar.
    $key = 'apex-' . md5(json_encode($payload));
@endphp

<div wire:key="{{ $key }}" {{ $attributes->merge(['class' => 'w-full']) }}>
    <div wire:ignore
         x-data="{
             chart: null,
             init() {
                 this.chart = new window.ApexCharts(
                     this.$refs.canvas,
                     window.tmChartTheme.merge(@js($payload))
                 );
                 this.chart.render();
             },
             destroy() {
                 // Tanpa ini, mengganti filter berkali-kali meninggalkan
                 // instance chart yatim yang masih memasang listener resize.
                 this.chart?.destroy();
                 this.chart = null;
             },
         }">
        <div x-ref="canvas"></div>
    </div>
</div>
