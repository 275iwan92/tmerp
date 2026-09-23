<?php

namespace App\Support;

// Data contoh fiktif untuk halaman DevLib (situs tanpa database). Deterministik: sama tiap muat.
final class DemoData
{
    public static function employees(int $n = 64): array
    {
        $first = ['Andi', 'Budi', 'Citra', 'Dewi', 'Eka', 'Fajar', 'Gita', 'Hadi', 'Indah', 'Joko', 'Kartika', 'Lukman', 'Maya', 'Nanda', 'Oki', 'Putri'];
        $last = ['Pratama', 'Santoso', 'Rahma', 'Wijaya', 'Kusuma', 'Saputra', 'Lestari', 'Hidayat'];
        $dept = ['Produksi' => ['Operator Cetak', 'Operator Potong', 'Leader Shift'], 'Gudang' => ['Staf Gudang', 'Kepala Gudang'], 'Sales' => ['Sales Executive', 'Admin Sales'], 'Keuangan' => ['Kasir', 'Staf Akuntansi']];
        $depts = array_keys($dept);
        $rows = [];
        for ($i = 1; $i <= $n; $i++) {
            $d = $depts[$i % count($depts)];
            $rows[] = [
                'id' => $i,
                'name' => $first[($i * 7) % 16].' '.$last[($i * 3) % 8],
                'code' => sprintf('EMP-%04d', 1000 + $i),
                'dept' => $d,
                'position' => $dept[$d][$i % count($dept[$d])],
                'status' => ['TETAP', 'KONTRAK', 'KONTRAK', 'HARIAN'][$i % 4],
                'join_date' => date('Y-m-d', mktime(0, 0, 0, 1 + ($i * 5) % 12, 1 + ($i * 11) % 28, 2016 + $i % 10)),
                'phone' => '+628'.(1200000000 + $i * 7919),
                'city' => ['Surabaya', 'Sidoarjo', 'Gresik', 'Mojokerto'][$i % 4],
                'active' => $i % 9 !== 0,
            ];
        }

        return $rows;
    }

    // Dokumen PO menunggu approval (untuk x-dl::approval): diambil dari purchaseOrders berstatus SUBMITTED.
    public static function approvals(): array
    {
        $out = [];
        foreach (array_filter(self::purchaseOrders(), fn ($p) => $p['status'] === 'SUBMITTED') as $i => $p) {
            $big = $p['total'] > 50000000;
            $steps = [['role' => 'Pembuat', 'by' => $p['requester'], 'at' => date('j M', strtotime($p['po_date'])), 'status' => 'done'],
                ['role' => 'Kepala Pembelian', 'by' => $i % 2 ? 'PLT — Kepala PPIC' : 'Dewi Kusuma', 'at' => date('j M', strtotime($p['po_date'].' +1 day')), 'status' => 'done', 'plt' => (bool) ($i % 2)],
                ['role' => 'Operational Manager', 'status' => 'current']];
            if ($big) {
                $steps[] = ['role' => 'Direksi (> Rp 50 jt)', 'status' => 'pending'];
            }
            $out[] = [
                'id' => $p['id'], 'no' => $p['po_no'], 'title' => 'Purchase Order', 'amount' => $p['total'], 'party' => $p['supplier'], 'date' => $p['po_date'],
                'submitted_by' => $p['requester'].' (Purchasing)',
                'summary' => ['Nilai' => 'Rp '.number_format($p['total'], 0, ',', '.'), 'Termin' => ['Net 30', 'Net 14', 'COD'][$i % 3], 'Estimasi Tiba' => date('j M Y', strtotime($p['po_date'].' +7 days')), 'Jumlah Item' => $p['items']],
                'lines' => $p['lines'],
                'steps' => $steps,
                'history' => [['action' => 'SUBMITTED', 'by' => $p['requester'], 'at' => date('j M Y', strtotime($p['po_date'])).', 10:05'],
                    ['action' => 'APPROVED', 'by' => $steps[1]['by'], 'at' => date('j M Y', strtotime($p['po_date'].' +1 day')).', 15:40', 'plt' => $steps[1]['plt'], 'note' => 'Harga sesuai penawaran.']],
            ];
        }

        return $out;
    }

    public static function purchaseOrders(int $n = 48): array
    {
        $sup = ['PT Contoh Kertas Jaya', 'CV Tinta Nusantara', 'PT Karton Abadi', 'UD Lem Sejahtera', 'PT Plat Offset Prima'];
        $items = config('devlib.lookups.item.rows');
        $rows = [];
        for ($i = 1; $i <= $n; $i++) {
            $lines = [];
            for ($k = 0, $cnt = 1 + ($i * 7) % 4; $k < $cnt; $k++) {
                $it = $items[($i + $k * 3) % count($items)];
                $qty = 5 * (1 + ($i * 13 + $k * 7) % 40);
                $lines[] = ['item' => $it['code'], 'item_label' => $it['name'], 'spec' => $it['spec'], 'qty' => $qty, 'uom' => $it['uom'], 'price' => $it['price'], 'subtotal' => $qty * $it['price']];
            }
            $rows[] = [
                'id' => $i,
                'po_no' => sprintf('PO-2609-%04d', $i),
                'po_date' => date('Y-m-d', mktime(0, 0, 0, 9, 1 + ($i % 23), 2026)),
                'supplier' => $sup[($i * 3) % 5],
                'items' => count($lines),
                'lines' => $lines,
                'lines_discount' => 0,
                'lines_ppn' => true,
                'total' => round(array_sum(array_column($lines, 'subtotal')) * 1.11, 2),
                'status' => ['DRAFT', 'SUBMITTED', 'APPROVED', 'APPROVED', 'CLOSED', 'CANCELLED'][$i % 6],
                'requester' => ['Andi Pratama', 'Siti Rahma', 'Joko Santoso'][$i % 3],
                'note' => $i % 4 === 0 ? 'Kirim bertahap sesuai jadwal cetak minggu ini; konfirmasi ulang ke gudang sebelum barang diturunkan.' : 'Reguler',
            ];
        }

        return $rows;
    }
}
