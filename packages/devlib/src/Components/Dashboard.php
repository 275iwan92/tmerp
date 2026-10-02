<?php

namespace Devlib\Components;

// Opening screen setelah login (Beranda): salam + jam + cuaca, tombol Job Description & SOP jabatan,
// KPI per periode, target vs pencapaian, aktivitas, fokus & agenda, kata motivasi harian.
// Satu komponen untuk SEMUA jabatan: isi dari $board (profil dashboard per jabatan; jabatan tanpa
// profil khusus = profil "general"). Mekanisme sama, hanya bidang KPI/aktivitasnya yang beda.
//
// $board = [
//   'profile' => 'sales', 'profile_label' => 'Dashboard Sales',
//   'user' => ['first', 'name', 'code', 'position', 'department', 'superior'],
//   'brief' => [[label, nilai], ...],                    // ringkasan hari ini di bawah salam
//   'attendance' => ['in', 'status', 'tone', 'shift', 'start', 'end'],
//   'weather' => ['city', 'lat', 'lon'],
//   'jobdesc' => ['title', 'updated', 'html'],            // HTML dari master jabatan (diisi HR)
//   'sops' => [['code', 'title', 'revision', 'effective', 'html'], ...],
//   'periods' => ['daily' => 'Harian', ...], 'period' => 'monthly',
//   'kpi_title' => 'KPI Saya' (bawaan) | 'KPI Perusahaan' (manajemen),
//   'kpis' => [periode => [['label', 'value', 'previous', 'target', 'format', 'good_when', 'icon', 'tone', 'spark'], ...]],
//   'trend' => [periode => ['title', 'caption', 'labels', 'actual', 'target', 'format', 'summary' => [[label, nilai], ...]]],
//   'activities' => ['title', 'caption', 'types' => [[key, label, icon, tone]], 'segments' => [[key, label, tone]],
//                    'data' => [periode => ['target' => [type => n], 'matrix' => [segment => [type => n]], 'week' => [...]]]],
//   'targets' => [periode => ['title', 'caption', 'rows' => [['label', 'sub', 'actual', 'target', 'format', 'good_when', 'icon', 'tone']]]],
//   'pipeline' => [periode => ['title', 'caption', 'labels', 'values', 'format', 'series']],           // funnel
//   'ranking' => [periode => ['title', 'caption', 'name_label', 'format', 'rows' => [['name', 'sub', 'target', 'actual']]]],
//   'focus' => ['title', 'caption', 'rows' => [['name', 'sub', 'segment', 'tone', 'stage', 'counts', 'last', 'last_tone', 'action']]],
//   'agenda' => [['time', 'duration', 'title', 'note', 'tone', 'done'], ...],
//   'quotes' => [['t' => teks, 'a' => penulis], ...],    // 366 baris: baris ke-n = hari ke-n dalam tahun
//   'widgets' => [...], 'catalog' => [...], 'board_url', 'board_editable', 'board_source'   // papan widget (x-dl::widget-board):
//       bila kunci 'widgets' ada, blok KPI .. kata harian di atas diganti papan yang susunannya diatur pemiliknya
// ]
class Dashboard extends DevlibComponent
{
    public function __construct(public array $board = [], public ?string $source = null) {}

    public function tag(): string
    {
        return 'x-dl::dashboard profile='.($this->board['profile'] ?? 'general');
    }

    public function source(): string
    {
        return $this->source ?? 'profil dashboard per jabatan';
    }

    public function period(): string
    {
        return $this->board['period'] ?? array_key_first($this->board['periods'] ?? ['monthly' => '']);
    }

    // Angka ringkas untuk blok target/ranking: money = "Rp 1,9 M", percent = "93%", number = "1.315".
    public static function fmt(float|int $v, string $format = 'number'): string
    {
        return match ($format) {
            'money' => 'Rp '.\Devlib\Components\Field\Slider::compact((float) $v),
            'percent' => \Devlib\Locale::percent($v, 0),
            default => \Devlib\Locale::number($v),
        };
    }

    // Nilai total aktivitas per tipe dari matriks segmen x tipe.
    public static function totals(array $matrix, array $types): array
    {
        $out = [];
        foreach ($types as $t) {
            $out[$t['key']] = array_sum(array_map(fn ($row) => $row[$t['key']] ?? 0, $matrix));
        }

        return $out;
    }

    public function render()
    {
        return view('devlib::components.dashboard');
    }
}
