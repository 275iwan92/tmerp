<?php

namespace App\Support\V2;

// Alur antar layar/modul (mengikuti tmerp sekarang, CLAUDE.md §4). Status blok = status layar mockup-nya.
final class Flows
{
    public static function get(string $module): ?array
    {
        $s = function (string $slug, ?string $label = null, ?string $module = null, ?string $note = null, array $branch = []) {
            $m = Screens::screen($slug);

            return ['key' => $slug, 'label' => $label ?? $m['label'], 'icon' => $m['icon'] ?? 'file', 'module' => $module ?? ($m['module'] ?? ''),
                'url' => $m['url'] ?? null, 'status' => $m['status'] ?? 'todo', 'note' => $note, 'branch' => $branch];
        };
        $b = fn (string $slug, ?string $label = null) => ($m = Screens::screen($slug)) ? ['label' => $label ?? $m['label'], 'icon' => $m['icon'], 'url' => $m['url'], 'status' => $m['status']] : null;
        $later = fn (string $label, string $icon, string $module, string $note) => ['key' => $label, 'label' => $label, 'icon' => $icon, 'module' => $module, 'status' => 'todo', 'note' => $note];

        return match ($module) {
            'SALES' => ['title' => 'Alur Sales: dari Inquiry sampai pengiriman', 'steps' => [
                $s('inquiry', 'Inquiry Product', 'Sales', '3 jenis dokumen: BARU (dari nol), REVISI (spec berubah), REKALKULASI (harga bahan saja)',
                    array_filter([$b('inquiry-new', 'Baru'), $b('inquiry-revise', 'Revisi'), $b('inquiry-recalc', 'Rekalkulasi')])),
                $s('prodev', null, 'Sales · Production', 'Inquiry BARU dikirim ke ProDev: cari layout fisik paling hemat -> prod_layout_group CONFIRMED'),
                $s('sales-price', 'Pro-Cost: tier & harga', 'Product Costing', 'HPP dihitung di modul Product Costing (rahasia); Sales hanya melihat tier & harga setelah dipublish'),
                $s('quotation', null, 'Sales', 'Pilih InqProd berharga milik customer yang sama; harga/qty/diskon terisi otomatis, boleh diubah sebelum publish'),
                $s('customer-order', null, 'Sales', 'Mode #1: dari Quotation PUBLISHED (1 CO = 1 Quotation). Mode #2 manual (butuh Master Product) belum ada di tmerp'),
                $s('cpdel', null, 'Sales', 'CO Confirm -> jadwal kirim otomatis per item (qty penuh @ due date); bisa Split / Gabung'),
                $s('delivery-order', null, 'Sales', 'Pengiriman berdasar jadwal CPDEL', array_filter([$b('delivery-notes', 'Surat Jalan')])),
                $later('Invoice & Piutang', 'fa-file-invoice-dollar', 'Finance', 'Modul Finance (menyusul)'),
            ]],
            'COSTING' => ['title' => 'Alur Product Costing: dari layout ProDev sampai harga dipublish', 'steps' => [
                $s('inquiry', 'Inquiry Product (BARU)', 'Sales', 'Spesifikasi kasar dari Sales'),
                $s('prodev', null, 'Production', 'Mode #1 integrasi: berawal dari iqp_part; Mode #2 manual tanpa Inquiry'),
                $s('procost', 'Pro-Cost (ambil data layout)', 'Product Costing', 'Mode #1: tarik prod_layout_group CONFIRMED',
                    array_filter([$b('procost-instant', 'Mode #2: input manual (Instant)')])),
                $later('Publish (persetujuan Direksi)', 'fa-stamp', 'Product Costing', 'Publish = deklasifikasi harga ke Sisi B (Sales). HPP/margin tetap rahasia'),
                $s('quotation', 'Dipakai Sales Quotation', 'Sales', 'Hanya costing published_flag = true yang bisa ditarik'),
            ]],
            default => null,
        };
    }
}
