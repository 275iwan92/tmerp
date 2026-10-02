<?php

namespace App\Support\V2\Board;

use App\Models\Sys\SecUser;
use Devlib\Components\Dashboard;

// Satu widget opening screen = satu kelas. Angkanya dihitung di sini (lewat Numbers), bukan oleh asisten.
// title / description / group / module / minLevel / size / phrases = nilai AWAL baris katalog (sys_widget); sesudah itu
// dirawat user di Setup > Opening Board Widgets. Bentuk tampilan (type) dan isi (build) milik kelas.
abstract class Widget
{
    // Cara ringkas menulis identitas widget: satu array (kunci di bawah); kelas boleh juga menimpa method-nya satu per satu.
    //   key, title, description, type (kpi | chart | progress | ranking | list | quote), group, dashboard, icon, tone, size,
    //   module, min_level, price (bool), payroll (bool), phrases
    /** @return array<string, mixed> */
    protected function meta(): array
    {
        return [];
    }

    public function key(): string
    {
        return (string) $this->meta()['key'];
    }

    public function title(): string
    {
        return (string) $this->meta()['title'];
    }

    public function description(): string
    {
        return (string) $this->meta()['description'];
    }

    // kpi | chart | progress | ranking | list | quote (lihat Devlib\Components\WidgetBoard)
    public function type(): string
    {
        return (string) $this->meta()['type'];
    }

    // Kelompok di daftar Choose Widgets untuk widget yang tidak punya dashboard asal.
    public function group(): string
    {
        return (string) ($this->meta()['group'] ?? 'General');
    }

    // Dashboard bidang tempat widget ini berasal (sys_dashboard.dashboard_code); null = tidak punya dashboard sendiri.
    public function dashboard(): ?string
    {
        return $this->meta()['dashboard'] ?? null;
    }

    public function icon(): string
    {
        return (string) ($this->meta()['icon'] ?? 'fa-chart-simple');
    }

    public function tone(): string
    {
        return (string) ($this->meta()['tone'] ?? 'accent');
    }

    // Lebar bawaan dari 12 kolom: 3, 4, 6, 8, 12.
    public function size(): int
    {
        return (int) ($this->meta()['size'] ?? 4);
    }

    // Modul pemilik angka: pemasang wajib boleh membuka modul ini (atau Laporan Manajemen). null = semua.
    public function module(): ?string
    {
        return $this->meta()['module'] ?? null;
    }

    public function minLevel(): int
    {
        return (int) ($this->meta()['min_level'] ?? 0);
    }

    // Widget berisi rupiah harga jual: hanya untuk yang berhak melihat harga jual (aturan A.9).
    public function needsSalesPrice(): bool
    {
        return (bool) ($this->meta()['price'] ?? false);
    }

    // Widget berisi angka gaji: hanya untuk yang berhak melihat nilai payroll (SecUser::canViewPayrollAmounts).
    public function needsPayroll(): bool
    {
        return (bool) ($this->meta()['payroll'] ?? false);
    }

    /** @return array<int, string> kata pemicu asisten, dua bahasa, huruf kecil */
    public function phrases(): array
    {
        return $this->meta()['phrases'] ?? [];
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

    // Kalimat yang memuat angka: teks English ber-:kunci -> terjemahannya dengan nilai terisi (hasilnya dibungkus raw() di prop teks).
    protected function say(string $text, array $values = []): string
    {
        return str_replace(array_map(fn ($k) => ':'.$k, array_keys($values)), array_map('strval', array_values($values)), t($text));
    }

    protected function money(float|int $value): string
    {
        return Dashboard::fmt($value, 'money');
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
