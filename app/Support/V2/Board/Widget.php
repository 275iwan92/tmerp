<?php

namespace App\Support\V2\Board;

use App\Models\Sys\SecUser;
use Devlib\Locale;

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

    // Nama bulan 3 huruf menurut bahasa tampilan (label sumbu grafik).
    protected static function monthName(\DateTimeInterface $month): string
    {
        return explode('-', (string) Locale::d($month))[1] ?? $month->format('M');
    }

    // Tombol buka layar sumber (tab) dari kode menu.
    protected function open(string $menuCode, string $title, string $icon = 'fa-solid fa-chart-pie'): array
    {
        return ['url' => route('v2.item', $menuCode), 'code' => $menuCode, 'title' => $title, 'label' => 'Open', 'icon' => $icon];
    }
}
