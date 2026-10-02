<?php

namespace App\Support\V2\Board;

use App\Models\Sys\SecUser;
use Devlib\Components\Dashboard;

// Satu widget opening screen = satu kelas. Angkanya dihitung di sini (lewat Numbers), bukan oleh asisten.
// title / description / group / module / minLevel / size / phrases = nilai AWAL baris katalog (sys_widget); sesudah itu
// dirawat user di Setup > Opening Board Widgets. Bentuk tampilan (type) dan isi (build) milik kelas.
abstract class Widget
{
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    // kpi | chart | progress | ranking | list | quote (lihat Devlib\Components\WidgetBoard)
    abstract public function type(): string;

    public function group(): string
    {
        return 'General';
    }

    public function icon(): string
    {
        return 'fa-chart-simple';
    }

    public function tone(): string
    {
        return 'accent';
    }

    // Lebar bawaan dari 12 kolom: 3, 4, 6, 8, 12.
    public function size(): int
    {
        return 4;
    }

    // Modul pemilik angka: pemasang wajib boleh membuka modul ini (atau Laporan Manajemen). null = semua.
    public function module(): ?string
    {
        return null;
    }

    public function minLevel(): int
    {
        return 0;
    }

    // Widget berisi rupiah harga jual: hanya untuk yang berhak melihat harga jual (aturan A.9).
    public function needsSalesPrice(): bool
    {
        return false;
    }

    /** @return array<int, string> kata pemicu asisten, dua bahasa, huruf kecil */
    public function phrases(): array
    {
        return [];
    }

    /** @return array{data: array<string, mixed>, caption?: ?string, source?: string, open?: array<string, string>} */
    abstract public function build(Numbers $n, SecUser $user): array;

    // Rincian tahap order untuk baris bawah KPI (sudah diterjemahkan; bungkus raw() saat dipakai di prop teks):
    // "Confirmed Rp 2,4 jt · Draft Rp 162,6 jt" atau "Confirmed 3 · Draft 6 · Waiting for approval 1".
    protected function splitText(array $split, bool $money): string
    {
        $show = fn (string $key) => $money ? Dashboard::fmt($split[$key], 'money') : (string) $split['n_'.$key];
        $parts = ['Confirmed '.$show('confirmed'), 'Draft '.$show('draft')];
        if ($split['n_waiting'] > 0) {
            $parts[] = t('Waiting for approval').' '.$show('waiting');
        }

        return implode(' · ', $parts);
    }

    // Keterangan KPI tanpa target: target belum diisi, atau periodenya harian / mingguan (target diisi per bulan).
    protected function noTarget(Numbers $n): string
    {
        return in_array($n->period, ['daily', 'weekly'], true) ? 'Targets are set per month' : 'No target set for this period';
    }

    // Tombol buka layar sumber (tab) dari kode menu.
    protected function open(string $menuCode, string $title, string $icon = 'fa-solid fa-chart-pie'): array
    {
        return ['url' => route('v2.item', $menuCode), 'code' => $menuCode, 'title' => $title, 'label' => 'Open', 'icon' => $icon];
    }
}
