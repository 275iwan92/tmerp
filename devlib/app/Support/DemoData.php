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

    // Kartu kanban alur produksi (contoh fiktif).
    public static function kanbanCards(): array
    {
        $t = fn ($l, $tone) => ['label' => $l, 'tone' => $tone];

        return [
            ['id' => 1, 'col' => 'queue', 'title' => 'CO-2609-0031 · Box Snack 50.000 pcs', 'desc' => 'Menunggu plat & kertas Ivory 310.', 'tags' => [$t('Offset', 'accent'), $t('Urgent', 'danger')], 'priority' => 'High', 'due' => '2026-09-26 16:00', 'assignee' => 'Budi Santoso', 'checklist' => [['text' => 'Plat CTP siap', 'done' => true], ['text' => 'Kertas di-issue', 'done' => false]]],
            ['id' => 2, 'col' => 'queue', 'title' => 'CO-2609-0034 · Label Botol', 'tags' => [$t('Digital', 'violet')], 'priority' => 'Low', 'due' => '2026-10-02 12:00', 'assignee' => 'Rina Wulandari'],
            ['id' => 3, 'col' => 'print', 'title' => 'CO-2609-0027 · Dus Farmasi', 'desc' => 'KOMORI Lithrone 1, 4 warna + varnish.', 'tags' => [$t('Offset', 'accent')], 'priority' => 'Medium', 'due' => '2026-09-22 10:00', 'assignee' => 'Joko Santoso', 'checklist' => [['text' => 'Approval warna', 'done' => true], ['text' => 'Cetak sisi A', 'done' => true], ['text' => 'Cetak sisi B', 'done' => false]], 'comments' => [['by' => 'QC', 'at' => '23 Sep 2026 08:10', 'text' => 'Warna cyan sedikit tua, sudah dikoreksi.']]],
            ['id' => 4, 'col' => 'finish', 'title' => 'CO-2609-0020 · Kotak Kosmetik', 'desc' => 'Laminasi doff + pond + lem samping.', 'tags' => [$t('Finishing', 'warn')], 'priority' => 'High', 'due' => '2026-09-25 17:00', 'assignee' => 'Andi Pratama'],
            ['id' => 5, 'col' => 'qc', 'title' => 'CO-2609-0018 · Sleeve Teh', 'tags' => [$t('QC', 'success')], 'priority' => 'Medium', 'due' => '2026-09-24 09:00', 'assignee' => 'Siti Rahma', 'checklist' => [['text' => 'Sampling 1%', 'done' => true], ['text' => 'Cek potong', 'done' => true]]],
            ['id' => 6, 'col' => 'done', 'title' => 'CO-2609-0011 · Brosur Promo', 'tags' => [$t('Digital', 'violet')], 'priority' => 'Low', 'due' => '2026-09-18 15:00', 'assignee' => 'Rina Wulandari'],
        ];
    }

    // Acara kalender contoh, relatif terhadap minggu berjalan (WIB) supaya selalu terlihat.
    public static function calendarEvents(): array
    {
        $d = fn (int $off, ?string $t = null) => date('Y-m-d', strtotime("$off days")).($t ? ' '.$t : '');

        return [
            ['id' => 1, 'title' => 'Rapat produksi mingguan', 'start' => $d(0, '08:00'), 'end' => $d(0, '09:00'), 'tone' => 'accent', 'desc' => 'Review jadwal cetak & kendala mesin.'],
            ['id' => 2, 'title' => 'Kunjungan customer PT Contoh Kertas', 'start' => $d(0, '10:00'), 'end' => $d(0, '11:30'), 'tone' => 'success'],
            ['id' => 3, 'title' => 'Proof fisik CO-2609-0031', 'start' => $d(0, '10:30'), 'end' => $d(0, '12:00'), 'tone' => 'warn', 'desc' => 'Approval warna oleh customer.'],
            ['id' => 4, 'title' => 'Maintenance KOMORI 1', 'start' => $d(1, '13:00'), 'end' => $d(1, '17:00'), 'tone' => 'danger'],
            ['id' => 5, 'title' => 'Stock opname gudang bahan', 'start' => $d(2), 'end' => $d(3), 'allDay' => true, 'tone' => 'violet'],
            ['id' => 6, 'title' => 'Pelatihan K3', 'start' => $d(-1, '14:00'), 'end' => $d(-1, '16:00'), 'tone' => 'neutral'],
            ['id' => 7, 'title' => 'Libur nasional (contoh)', 'start' => $d(5), 'allDay' => true, 'tone' => 'danger'],
            ['id' => 8, 'title' => 'Kirim DO tahap 2', 'start' => $d(9, '09:00'), 'end' => $d(9, '10:00'), 'tone' => 'success'],
        ];
    }

    // Baris nota CO contoh (mengikuti nota_co_mockup).
    public static function coLines(int $n = 18): array
    {
        $names = ['Box Snack 200g', 'Sleeve Teh Celup 25s', 'Jasa Cetak Kemasan (bahan customer)', 'Pouch Kopi Sachet 10g', 'Box Biskuit Family Pack', 'Label Botol Saus 250ml', 'Karton Master Box 12in1', 'Sleeve Cup Es Krim 90ml', 'Box Obat Herbal 30 Tab'];
        $specs = ['230x160x60mm, DC 350gsm, UV varnish, cetak 4/0', '180x60mm, art paper 210gsm, cetak 4/1 +1 PMS 032C', '200x140mm, bahan customer, cetak 4/0', '120x80mm, laminasi metalize, cetak 4/4', '300x200x80mm, DC 400gsm, cetak 2/0 +1 PMS 286C'];
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $qty = 3000 + $i * 770; $price = 150 + $i * 37; $disc = $i % 4 === 0 ? 2 : 0;
            $out[] = ['name' => $names[$i % count($names)], 'code' => sprintf('FG-%04d', 101 + $i), 'spec' => $specs[$i % count($specs)], 'ship' => date('j M y', strtotime('2026-09-05 +'.$i.' days')),
                'qty' => $qty, 'price' => $price, 'disc' => $disc ? $disc.'%' : '–', 'subtotal' => round($qty * $price * (1 - $disc / 100))];
        }

        return $out;
    }

    // Mutasi kartu stok contoh (mockup_print_stock_card).
    public static function stockCard(): array
    {
        $m = [['2026-07-03', 'IN/26/00028', 'PV/26/00098', 'IN', 'Penerimaan dari PT Contoh Kertas · PO/26/00071', 'AP-2603-A', 2000, 0, 0],
            ['2026-07-05', 'OUT/26/00034', 'WO/26/00281', 'OUT', 'Bagian CETAK — Box Obat Herbal · CO/26/00187', 'AP-2603-A', 0, 1100, 0],
            ['2026-07-09', 'RTP/26/00012', 'OUT/26/00034', 'RTP', 'Retur GOOD dari Bagian CETAK', 'AP-2603-A', 140, 0, 0],
            ['2026-07-14', 'OUT/26/00041', 'WO/26/00293', 'OUT', 'Bagian CETAK — Sleeve Mie Instan · CO/26/00191', 'AP-2603-A', 0, 640, 0],
            ['2026-07-18', 'IN/26/00035', 'PV/26/00109', 'IN', 'Penerimaan dari PT Contoh Kertas · PO/26/00082', 'AP-2605', 1500, 0, 0],
            ['2026-07-25', 'OUT/26/00047', 'WO/26/00301', 'OUT', 'Bagian BELAH — Inner Box Sepatu · CO/26/00198', 'AP-2605', 0, 1520, 0],
            ['2026-07-31', 'OPN/26/00019', 'Opname Juli', 'ADJ', 'Hitung fisik akhir Juli — susut alami', 'AP-2605', 0, 0, -30],
            ['2026-08-04', 'OUT/26/00050', 'WO/26/00312', 'OUT', 'Bagian CETAK — Box Snack · CO/26/00205', 'AP-2605', 0, 280, 0],
            ['2026-08-12', 'IN/26/00041', 'PV/26/00121', 'IN', 'Penerimaan dari PT Contoh Kertas · PO/26/00090', 'AP-2608', 1800, 0, 0],
            ['2026-08-19', 'OUT/26/00058', 'WO/26/00330', 'OUT', 'Bagian CETAK — Label Botol · CO/26/00211', 'AP-2608', 0, 950, 0]];
        $bulan = ['07' => 'Juli 2026', '08' => 'Agustus 2026'];

        return array_map(fn ($r) => ['date' => date('d/m/Y', strtotime($r[0])), 'month' => $bulan[substr($r[0], 5, 2)], 'doc' => $r[1], 'src' => 'Sumber: '.$r[2], 'type' => $r[3], 'ref' => $r[4], 'lot' => $r[5], 'in' => $r[6], 'out' => $r[7], 'adj' => $r[8]], $m);
    }

    // Master stok contoh (mockup_print_master_stock).
    public static function masterStock(): array
    {
        $rows = [
            ['RM-KRT-0001', 'Art Paper 150gr 65 × 100 cm', 'Kertas Cetak', 'GD-01 Bahan Baku', 'LBR', 1240, 2500, 2180, 0, 500], ['RM-KRT-0002', 'Art Paper 150gr 79 × 109 cm', 'Kertas Cetak', 'GD-01 Bahan Baku', 'LBR', 3400, 1800, 1220, 0, 500],
            ['RM-KRT-0003', 'Art Paper 190gr 65 × 100 cm', 'Kertas Cetak', 'GD-01 Bahan Baku', 'LBR', 820, 1200, 1640, 0, 400], ['RM-KRT-0004', 'Art Paper 230gr 79 × 109 cm', 'Kertas Cetak', 'GD-01 Bahan Baku', 'LBR', 520, 1800, 1650, -200, 300],
            ['RM-KRT-0007', 'Art Carton 240gr 79 × 109 cm', 'Kertas Cetak', 'GD-01 Bahan Baku', 'LBR', 4100, 0, 3960, -80, 400], ['RM-KRT-0009', 'Duplex 350gr 79 × 109 cm', 'Kertas Cetak', 'GD-01 Bahan Baku', 'LBR', 3240, 4500, 2860, 120, 800],
            ['RM-TNT-0021', 'Tinta Offset Cyan', 'Tinta & Bahan Kimia', 'GD-03 Tinta & Kimia', 'KG', 98, 48, 28, 0, 40], ['RM-TNT-0022', 'Tinta Offset Magenta', 'Tinta & Bahan Kimia', 'GD-03 Tinta & Kimia', 'KG', 86, 48, 32, -8, 40],
            ['RM-TNT-0023', 'Tinta Offset Yellow', 'Tinta & Bahan Kimia', 'GD-03 Tinta & Kimia', 'KG', 72, 36, 40, 0, 40], ['RM-TNT-0024', 'Tinta Offset Black (K)', 'Tinta & Bahan Kimia', 'GD-03 Tinta & Kimia', 'KG', 124, 72, 64, 0, 60],
            ['RM-PLT-0072', 'Plat CTP 72 × 103', 'Plat & Film', 'GD-02 Plat', 'PCS', 210, 300, 260, -5, 100], ['RM-LEM-0005', 'Lem Samping PVAc', 'Lem & Perekat', 'GD-03 Tinta & Kimia', 'KG', 180, 200, 150, 0, 60],
        ];

        return array_map(function ($r) {
            $end = $r[5] + $r[6] - $r[7] + $r[8];

            return ['code' => $r[0], 'name' => $r[1], 'cat' => $r[2], 'wh' => $r[3], 'uom' => $r[4], 'open' => $r[5], 'in' => $r[6], 'out' => $r[7], 'adj' => $r[8], 'end' => $end, 'min' => 'min '.$r[9], 'status' => $end < $r[9] ? '< MIN' : 'OK'];
        }, $rows);
    }

    // Tipe produk + dieline_spec + parameter + ukuran pasaran, diekspor BACA-SAJA dari tm2mst (resources/data/dieline_types.json).
    public static function dielineTypes(): array
    {
        return json_decode(file_get_contents(resource_path('data/dieline_types.json')), true);
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
