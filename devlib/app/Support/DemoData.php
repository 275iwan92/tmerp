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
                'attendance_ytd' => 150 + ($i * 37) % 120,
            ];
        }

        return $rows;
    }

    // Dokumen PO menunggu approval (untuk x-dl::approval): diambil dari purchaseOrders berstatus SUBMITTED.
    public static function approvals(): array
    {
        $out = [];
        // Status baku (Draft/Confirmed/...) menggantikan SUBMITTED; Confirmed = menunggu approval.
        // Hanya baris yang dulu SUBMITTED (indeks 0, 6, 12, ...) supaya isi Approval Center sama seperti yang disetujui 2026-09-26.
        foreach (array_filter(self::purchaseOrders(), fn ($p, $k) => $p['status'] === 'Confirmed' && $k % 6 === 0, ARRAY_FILTER_USE_BOTH) as $i => $p) {
            $big = $p['total'] > 50000000;
            $steps = [['role' => 'Pembuat', 'by' => $p['requester'], 'at' => \Devlib\Locale::date(date('Y-m-d', strtotime($p['po_date']))), 'status' => 'done', 'submitter' => true],
                ['role' => 'Kepala Pembelian', 'by' => $i % 2 ? 'PLT — Kepala PPIC' : 'Dewi Kusuma', 'at' => \Devlib\Locale::date(date('Y-m-d', strtotime($p['po_date'].' +1 day'))), 'status' => 'done', 'plt' => (bool) ($i % 2)],
                ['role' => 'Operational Manager', 'status' => 'current']];
            if ($big) {
                $steps[] = ['role' => 'Direksi (> IDR 50 jt)', 'status' => 'pending'];
            }
            $out[] = [
                'id' => $p['id'], 'no' => $p['po_no'], 'title' => 'Purchase Order', 'amount' => $p['total'], 'party' => $p['supplier'], 'date' => $p['po_date'],
                'submitted_by' => $p['requester'].' (Purchasing)',
                'summary' => ['Nilai' => 'IDR '.\Devlib\Locale::number($p['total'], 0), 'Termin' => ['Net 30', 'Net 14', 'COD'][$i % 3], 'Estimasi Tiba' => \Devlib\Locale::date(date('Y-m-d', strtotime($p['po_date'].' +7 days'))), 'Jumlah Item' => $p['items']],
                'lines' => $p['lines'],
                'steps' => $steps,
                'history' => [['action' => 'SUBMITTED', 'by' => $p['requester'], 'at' => \Devlib\Locale::date(date('Y-m-d', strtotime($p['po_date']))).' 10:05'],
                    ['action' => 'APPROVED', 'by' => $steps[1]['by'], 'at' => \Devlib\Locale::date(date('Y-m-d', strtotime($p['po_date'].' +1 day'))).' 15:40', 'plt' => $steps[1]['plt'], 'note' => 'Harga sesuai penawaran.']],
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
            $qty = 3000 + $i * 770;
            $price = 150 + $i * 37;
            $disc = $i % 4 === 0 ? 2 : 0;
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

    // Ringkasan per supplier dari PO (untuk drill-down).
    public static function supplierSummary(): array
    {
        $out = [];
        foreach (self::purchaseOrders() as $p) {
            $k = $p['supplier'];
            $out[$k] ??= ['id' => $k, 'name' => $k, 'po' => 0, 'items' => 0, 'total' => 0, 'open' => 0];
            $out[$k]['po']++;
            $out[$k]['items'] += $p['items'];
            $out[$k]['total'] += $p['total'];
            $out[$k]['open'] += in_array($p['status'], ['Draft', 'Confirmed']) ? 1 : 0;
        }

        return array_values($out);
    }

    // Gambar produk contoh: kotak isometrik SVG (data URI) -- tanpa foto nyata.
    public static function boxImage(string $hex, string $label): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><rect width="120" height="120" fill="#f1f4f9"/>'
            .'<polygon points="60,18 100,38 60,58 20,38" fill="'.$hex.'" opacity=".55"/><polygon points="20,38 60,58 60,104 20,84" fill="'.$hex.'"/>'
            .'<polygon points="60,58 100,38 100,84 60,104" fill="'.$hex.'" opacity=".78"/><text x="40" y="76" font-family="Arial" font-size="9" fill="#fff" font-weight="700">'.htmlspecialchars($label).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    // Produk jadi contoh (grid foto / mix component).
    public static function products(): array
    {
        $names = [['Box Snack 200g', 'Box'], ['Sleeve Teh Celup', 'Sleeve'], ['Dus Obat Herbal', 'Box'], ['Label Botol Saus', 'Label'], ['Kotak Kosmetik', 'Box'], ['Pouch Kopi Sachet', 'Pouch'],
            ['Karton Master 12in1', 'Karton'], ['Hang Tag Baju', 'Tag'], ['Box Parcel Lebaran', 'Box'], ['Sleeve Cup Es Krim', 'Sleeve'], ['Kotak Sepatu Anak', 'Box'], ['Label Madu 500ml', 'Label']];
        $colors = ['Box' => '#2f6fed', 'Sleeve' => '#22b07d', 'Label' => '#e5a324', 'Pouch' => '#8b5cf6', 'Karton' => '#b6852a', 'Tag' => '#d0453f'];
        $out = [];
        foreach ($names as $i => [$n, $cat]) {
            $out[] = ['id' => $i + 1, 'name' => $n, 'code' => sprintf('FG-%04d', 101 + $i), 'category' => $cat, 'image' => self::boxImage($colors[$cat], strtoupper(substr($cat, 0, 3))),
                'price' => 150 + $i * 45, 'stock_pct' => (17 * $i + 23) % 100, 'rating' => 3 + ($i % 3) * 0.5 + ($i % 2) * 0.5, 'active' => $i % 5 !== 3,
                'trend' => array_map(fn ($k) => 40 + (($i * 13 + $k * 7) % 30) + ($i % 2 ? $k * 3 : -$k * 2), range(0, 7)), 'status' => $i % 5 === 3 ? 'NONAKTIF' : ($i % 4 === 0 ? 'BARU' : 'AKTIF')];
        }

        return $out;
    }

    // Work order contoh dengan tahapan produksi (grid status bar).
    public static function workOrders(): array
    {
        $steps = ['Plat', 'Cetak', 'Laminasi', 'Pond', 'Lem', 'QC', 'Kirim'];
        $out = [];
        foreach (self::products() as $i => $p) {
            $at = ($i * 3) % count($steps);
            $out[] = ['id' => $i + 1, 'wo' => sprintf('WO-2609-%04d', 200 + $i), 'product' => $p['name'], 'qty' => 5000 + $i * 1250, 'stage' => $steps[$at],
                'progress' => (int) round(($at + 0.5) / count($steps) * 100), 'due' => date('Y-m-d', strtotime('2026-09-24 +'.($i % 9).' days')),
                'status' => $at === 6 ? 'SELESAI' : ($i % 5 === 2 ? 'TERLAMBAT' : 'JALAN')];
        }

        return $out;
    }

    // Baris faktur penjualan (ribuan) untuk Grid for Reporting.
    public static function salesLines(int $n = 2400): array
    {
        $cust = [['PT Contoh Snackindo', 'C-0012'], ['CV Farma Jaya', 'C-0031'], ['PT Kosmetik Indah', 'C-0044'], ['UD Teh Wangi', 'C-0058'], ['PT Label Prima', 'C-0063'], ['PT Roti Nusantara', 'C-0071'], ['CV Obat Sehat', 'C-0085']];
        $prod = [['Box Snack 250g', 'Box'], ['Sleeve Teh Celup', 'Sleeve'], ['Label Botol 600ml', 'Label'], ['Pouch Kopi 200g', 'Pouch'], ['Karton Master 24', 'Karton'], ['Box Obat 10 Strip', 'Box']];
        $sales = ['Andi', 'Rina', 'Joko', 'Sari'];
        $status = ['LUNAS', 'LUNAS', 'LUNAS', 'JATUH TEMPO', 'BELUM LUNAS'];
        $out = [];
        for ($i = 1; $i <= $n; $i++) {
            [$c, $code] = $cust[($i * 5) % count($cust)];
            [$p, $cat] = $prod[($i * 7) % count($prod)];
            $qty = 500 * (1 + ($i * 11) % 40);
            $price = 90 + ($i * 13) % 260;
            $out[] = ['id' => $i, 'inv_date' => date('Y-m-d', mktime(0, 0, 0, 1 + intdiv($i - 1, 267), 1 + ($i * 3) % 28, 2026)),
                'inv_no' => sprintf('INV-26%05d', intdiv($i + 1, 2)), 'customer' => $c, 'customer_code' => $code, 'product' => $p, 'category' => $cat,
                'sales' => $sales[intdiv($i, 9) % count($sales)], 'qty' => $qty, 'price' => $price, 'value' => $qty * $price, 'status' => $status[($i * 3) % count($status)]];
        }

        return $out;
    }

    // Penjualan contoh (datar) untuk pivot.
    public static function sales(int $n = 240): array
    {
        $cust = ['PT Contoh Snackindo', 'CV Farma Jaya', 'PT Kosmetik Indah', 'UD Teh Wangi', 'PT Label Prima'];
        $cat = ['Box', 'Sleeve', 'Label', 'Pouch', 'Karton'];
        $sales = ['Andi', 'Rina', 'Joko'];
        $mon = ['01 Jan', '02 Feb', '03 Mar', '04 Apr', '05 Mei', '06 Jun'];
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $qty = 1000 * (1 + ($i * 7) % 12);
            $out[] = ['bulan' => $mon[intdiv($i, 5) % 6], 'customer' => $cust[$i % 5], 'kategori' => $cat[intdiv($i, 30) % 5], 'sales' => $sales[intdiv($i, 7) % 3],
                'qty' => $qty, 'nilai' => $qty * (120 + ($i * 13) % 180)];
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
                'status' => ['Draft', 'Confirmed', 'Confirmed', 'Confirmed', 'Closed', 'Cancelled'][$i % 6],  // status baku (user 2026-09-30)
                'requester' => ['Andi Pratama', 'Siti Rahma', 'Joko Santoso'][$i % 3],
                'note' => $i % 4 === 0 ? 'Kirim bertahap sesuai jadwal cetak minggu ini; konfirmasi ulang ke gudang sebelum barang diturunkan.' : 'Reguler',
            ];
        }

        return $rows;
    }

    // Foto profil ilustrasi (siluet, bukan foto orang sungguhan).
    public static function avatarPhoto(string $bg, string $fg): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="'.$bg.'"/>'
            .'<circle cx="50" cy="40" r="18" fill="'.$fg.'"/><path d="M14 100c2-22 17-34 36-34s34 12 36 34z" fill="'.$fg.'"/></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    // Chat: orang (online = sedang login) + percakapan contoh. Nama fiktif.
    public static function chat(): array
    {
        $ph = fn ($bg, $fg) => self::avatarPhoto($bg, $fg);
        $people = [
            ['id' => 2, 'name' => 'Rina Wijaya', 'role' => 'Sales', 'online' => true, 'photo' => $ph('#dbeafe', '#3b82f6')],
            ['id' => 3, 'name' => 'Andi Pratama', 'role' => 'Finance', 'online' => true, 'photo' => $ph('#dcfce7', '#16a34a')],
            ['id' => 4, 'name' => 'Sari Kusuma', 'role' => 'PPIC', 'online' => true, 'photo' => $ph('#fce7f3', '#db2777')],
            ['id' => 5, 'name' => 'Bayu Nugroho', 'role' => 'Gudang', 'online' => true],
            ['id' => 6, 'name' => 'Dewi Lestari', 'role' => 'HRD', 'online' => true, 'photo' => $ph('#ede9fe', '#7c3aed')],
            ['id' => 7, 'name' => 'Joko Santoso', 'role' => 'Kepala Produksi', 'online' => false, 'seen' => 'hari ini 07:42', 'photo' => $ph('#fef3c7', '#d97706')],
            ['id' => 8, 'name' => 'Maya Putri', 'role' => 'Purchasing', 'online' => false, 'seen' => 'kemarin 16:05'],
            ['id' => 9, 'name' => 'Hendra Gunawan', 'role' => 'QC', 'online' => false, 'seen' => 'kemarin 21:10', 'photo' => $ph('#e0f2fe', '#0284c7')],
            ['id' => 10, 'name' => 'Lukman Hakim', 'role' => 'Direksi', 'online' => false, 'seen' => '20 Sep 2026'],
            ['id' => 11, 'name' => 'Tari Anggraini', 'role' => 'Accounting', 'online' => false, 'seen' => 'hari ini 08:15'],
        ];
        $convos = [
            2 => ['unread' => 2, 'msgs' => [
                ['me' => false, 't' => 'Pak, PO dari PT Sarana sudah masuk?', 'at' => '16:40', 'day' => 'Kemarin'],
                ['me' => true, 't' => 'Sudah, saya cek dulu ya.', 'at' => '16:42', 'day' => 'Kemarin', 'st' => 'read'],
                ['me' => false, 't' => 'Ini foto sampel box yang diminta customer', 'img' => self::boxImage('#2563eb', 'SAMPEL'), 'at' => '08:05', 'day' => 'Hari ini'],
                ['me' => false, 't' => 'Warnanya bisa disamakan?', 'at' => '08:06', 'day' => 'Hari ini'],
            ]],
            3 => ['unread' => 0, 'msgs' => [
                ['me' => false, 't' => 'Invoice #2600393 sudah dibayar.', 'at' => '09:12', 'day' => 'Hari ini'],
                ['me' => true, 't' => 'Noted, terima kasih.', 'at' => '09:15', 'day' => 'Hari ini', 'st' => 'read'],
            ]],
            4 => ['unread' => 1, 'msgs' => [
                ['me' => true, 't' => 'Jadwal cetak WO-2609-014 bisa maju ke Rabu?', 'at' => '10:20', 'day' => 'Hari ini', 'st' => 'delivered'],
                ['me' => false, 't' => 'Bisa, mesin kosong Rabu pagi.', 'at' => '10:31', 'day' => 'Hari ini'],
            ]],
            7 => ['unread' => 0, 'msgs' => [
                ['me' => true, 't' => 'Pak, hasil cetak shift 2 kemarin', 'img' => self::boxImage('#16a34a', 'SHIFT 2'), 'at' => '07:30', 'day' => 'Hari ini', 'st' => 'delivered'],
            ]],
        ];

        return ['me' => ['id' => 1, 'name' => 'Demo Admin', 'photo' => $ph('#e2e8f0', '#475569')], 'people' => $people, 'convos' => $convos];
    }

    // Data contoh gudang untuk x-dl::wh-map dan x-dl::warehouse-desk (gedung L 30 x 20 m, 2 lantai). Dipakai bersama oleh dua demo.
    public static function warehouse(): array
    {
        $bin = fn (string $code, string $status = 'EMPTY', array $items = [], ?float $pct = null, bool $dead = false, ?string $level = null, ?string $note = null) => [
            'code' => $code, 'title' => 'Demo area', 'status' => $status, 'dead' => $dead, 'pct' => $pct, 'level' => $level, 'note' => $note,
            'capacity' => $pct === null ? null : \Devlib\Locale::number($pct * 30).' / '.\Devlib\Locale::number(3000).' kg', 'capacityLabel' => 'Max 3,000 kg · 4 pallets', 'qr' => 'LOC:'.$code, 'items' => $items];
        $paper = ['code' => 'IV-300', 'name' => 'Ivory 300 gsm 79 x 109', 'qty' => '8,000 sheets', 'idle' => '12 days', 'dead' => false];
        $old = ['code' => 'KR-140', 'name' => 'White Kraft 140 gsm 79 x 97', 'qty' => '15,000 sheets', 'idle' => '275 days', 'dead' => true];
        $bins = [];
        $cells = function (string $prefix, float $x, float $y, int $lanes, int $slots, array $fill) use (&$bins, $bin, $paper, $old) {
            $out = [];
            for ($l = 1; $l <= $lanes; $l++) {
                for ($s = 1; $s <= $slots; $s++) {
                    $code = sprintf('%s-L%02d-%02d', $prefix, $l, $s);
                    $kind = $fill[$l.'-'.$s] ?? 'EMPTY';
                    $bins[$code] = match ($kind) {
                        'IN_USE' => $bin($code, 'IN_USE', [$paper], 69),
                        'FULL' => $bin($code, 'FULL', [$paper], 95),
                        'OVER' => $bin($code, 'OVER', [$paper], 108),
                        'DEAD' => $bin($code, 'IN_USE', [$old], 54, true),
                        'BLOCKED' => $bin($code, 'BLOCKED', [], 0, false, null, 'Floor repair'),
                        default => $bin($code),
                    };
                    $out[] = ['x' => $x + ($l - 1) * 2.1, 'y' => $y + ($s - 1) * 1.8, 'w' => 1.9, 'h' => 1.6, 'label' => sprintf('L%02d-%02d', $l, $s), 'bins' => [$code]];
                }
            }

            return $out;
        };
        $bins['L-RCV'] = $bin('L-RCV', 'IN_USE', [$paper], 11);
        $bins['L-INK-R01-01-1'] = $bin('L-INK-R01-01-1', 'IN_USE', [['code' => 'INK-CY', 'name' => 'Process Cyan', 'qty' => '120 kg', 'idle' => '25 days', 'dead' => false]], 40, false, 'Level 1');
        $bins['L-INK-R01-01-2'] = $bin('L-INK-R01-01-2', 'IN_USE', [['code' => 'INK-WH', 'name' => 'White Ink', 'qty' => '40 kg', 'idle' => '335 days', 'dead' => true,
            'tags' => [['label' => 'Arrived 01-Nov-2025'], ['label' => 'Expires 27-Oct-2026', 'tone' => 'warn'], ['label' => '1 pallets']]]], 13, true, 'Level 2');
        // Beberapa baris kapasitas (berat, volume, luas tapak x tinggi) + tanda kedaluwarsa.
        $bins['L-INK-R01-01-2'] = ['expiry' => true, 'caps' => [['label' => 'Weight', 'value' => '40 / 300 kg', 'pct' => 13], ['label' => 'Volume', 'value' => '0.20 / 3.00 m³', 'pct' => 7],
            ['label' => 'Floor area × height', 'value' => '3.75 m² × 0.80 m'], ['label' => 'Pallets', 'value' => '1']]] + $bins['L-INK-R01-01-2'];
        $cellsPpr = $cells('L-PPR', 0.9, 1.5, 6, 5, ['1-1' => 'IN_USE', '1-2' => 'FULL', '2-1' => 'OVER', '2-3' => 'IN_USE', '3-1' => 'DEAD', '4-2' => 'IN_USE', '6-5' => 'BLOCKED']);
        // Tumpukan (stack): satuan dari ATAS ke bawah, nomor 1 = paling bawah; 'count' = angka di kotak bin; sisa (remnant) dan
        // tertimbun (buried) punya titik + saringan legenda sendiri.
        $unit = fn (int $id, int $no, string $kind, string $name, string $sub, array $tags = [], ?string $tone = null, bool $up = true, bool $down = true) => [
            'id' => $id, 'no' => $no, 'kind' => $kind, 'name' => $name, 'sub' => $sub, 'tags' => $tags, 'tone' => $tone, 'up' => $up, 'down' => $down];
        $bins['L-PPR-L03-01'] = ['count' => 3, 'remnant' => true, 'buried' => true, 'stack' => [
            $unit(3, 3, 'pack', 'Art Paper 150 gsm 79 x 109', '2 reams · Arrived 12-Mar-2026', [['label' => 'Pack'], ['label' => 'Old remnant 205 days', 'tone' => 'danger']], 'danger', false),
            $unit(2, 2, 'pallet', 'White Kraft 140 gsm 79 x 97', 'PL2600031 · 7,500 sheets · Arrived 20-Sep-2026', [['label' => 'Pallet', 'tone' => 'info']]),
            $unit(1, 1, 'pallet', 'White Kraft 140 gsm 79 x 97', 'PL2600012 · 7,500 sheets · Arrived 31-Dec-2025',
                [['label' => 'Pallet', 'tone' => 'info'], ['label' => 'Dead stock', 'tone' => 'danger'], ['label' => 'Buried · 2 above', 'tone' => 'orange']], 'danger', true, false),
        ], 'caps' => [['label' => 'Weight', 'value' => '1,721 / 3,000 kg', 'pct' => 57], ['label' => 'Stack', 'value' => '3 units · 2 pallets · 1 pack'], ['label' => 'Stack height', 'value' => '3.05 / 3.20 m', 'pct' => 95]],
            'note' => 'The unit that should be used first is under 2 other unit(s). Move those first.'] + $bins['L-PPR-L03-01'];
        $bins['L-PPR-L06-04'] = ['count' => 3, 'remnant' => true, 'status' => 'IN_USE', 'pct' => 9, 'capacity' => '270 / 3,000 kg', 'stack' => [
            $unit(13, 3, 'pack', 'Ambri Verge 25 gsm 51 x 76', '400 sheets · Arrived 02-Oct-2026', [['label' => 'Pack'], ['label' => 'Remnant', 'tone' => 'violet']], null, false),
            $unit(12, 2, 'pack', 'Ivory 250 gsm 79 x 109', '350 sheets · Arrived 18-Sep-2026', [['label' => 'Pack'], ['label' => 'Remnant', 'tone' => 'violet']]),
            $unit(11, 1, 'pack', 'Sheet E-Flute 90 x 120', '60 sheets · Arrived 21-Feb-2026', [['label' => 'Pack'], ['label' => 'Old remnant 224 days', 'tone' => 'danger']], 'danger', true, false),
        ], 'items' => [['code' => 'AM-25', 'name' => 'Ambri Verge 25 gsm 51 x 76', 'qty' => '400 sheets', 'idle' => '1 days', 'dead' => false],
            ['code' => 'IV-250', 'name' => 'Ivory 250 gsm 79 x 109', 'qty' => '350 sheets', 'idle' => '15 days', 'dead' => false],
            ['code' => 'SH-E', 'name' => 'Sheet E-Flute 90 x 120', 'qty' => '60 sheets', 'idle' => '224 days', 'dead' => false]]] + $bins['L-PPR-L06-04'];
        $bins['L-PPR-L01-01'] = ['count' => 2] + $bins['L-PPR-L01-01'];
        $bins['L-PPR-L01-02'] = ['count' => 3] + $bins['L-PPR-L01-02'];
        $floors = [
            ['no' => 1, 'label' => 'Floor 1', 'zones' => [
                ['id' => 1, 'code' => 'RCV', 'name' => 'Receiving', 'tone' => 'violet', 'x' => 0.5, 'y' => 12, 'w' => 6, 'h' => 7.5, 'cells' => [['x' => 0.9, 'y' => 13, 'w' => 5.2, 'h' => 6.1, 'label' => 'RCV', 'bins' => ['L-RCV']]]],
                ['id' => 2, 'code' => 'PPR', 'name' => 'Plano Paper', 'tone' => 'accent', 'x' => 0.5, 'y' => 0.5, 'w' => 13.5, 'h' => 10.5,
                    'cells' => $cellsPpr],
                ['id' => 3, 'code' => 'INK', 'name' => 'Ink & Chemicals', 'tone' => 'lime', 'x' => 15, 'y' => 9, 'w' => 6, 'h' => 5,
                    'cells' => [['x' => 15.4, 'y' => 10, 'w' => 2.4, 'h' => 1.6, 'label' => 'R01-01', 'bins' => ['L-INK-R01-01-1', 'L-INK-R01-01-2']]]],
                ['id' => 4, 'code' => 'SHP', 'name' => 'Shipping', 'tone' => 'green', 'x' => 22, 'y' => 9, 'w' => 7.5, 'h' => 10.5, 'cells' => []],
            ]],
            ['no' => 2, 'label' => 'Floor 2', 'zones' => [
                ['id' => 5, 'code' => 'FG', 'name' => 'Finished Goods', 'tone' => 'accent', 'x' => 1, 'y' => 9, 'w' => 13, 'h' => 10, 'cells' => $cells('L-FG', 1.4, 10, 5, 4, ['1-1' => 'IN_USE', '2-1' => 'DEAD'])],
            ]],
        ];

        return ['floors' => $floors, 'bins' => $bins];
    }
}
