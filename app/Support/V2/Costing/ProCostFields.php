<?php

/*
 * REMARK
 * Tujuan       : Katalog field kartu "Cost & Material Parameters" layar ProCost v2 (Costing Hub dan Instant): urutan, label English, tipe, sumber pilihan, kelompok, dan field mana tampil untuk tipe part mana. Satu sumber untuk dua layar (bukan 60 baris tulisan tangan).
 * Sumber hulu  : Aturan tampil mengikuti v1 pages::inquiry.costing (Mode Integrasi, diarsipkan T10) dan pages::inquiry.costing-instant (Mode Instant, map_hpp_parts.visible_type di docs/ddl/tmhpp.sql).
 * Input        : kode tipe part (DC, DC+SF, DC+SHEET, KRAFT, SHEET, SHEET_DIGITAL).
 * Output       : list field [key, label, type n|t|s|b, opt (nama lookup), group, span, geo (kunci geometri ProDev)].
 * Dipakai oleh : resources/views/v2/screens/procost.blade.php, procost-instant.blade.php, App\Support\V2\Data\ProCost / ProCostInstant (daftar kunci yang boleh diposting).
 * Status       : Aktif (T10 tahap 3, 2026-10-21).
 */

namespace App\Support\V2\Costing;

final class ProCostFields
{
    public const DC_FAMILY = ['DC', 'DC+SF', 'DC+SHEET', 'KRAFT'];

    public const SHEET = ['SHEET', 'SHEET_DIGITAL'];

    public const LAM = ['DC+SF', 'DC+SHEET'];

    // Pilihan tetap.
    public const YES_NO = ['1' => 'Yes', '0' => 'No'];

    public const ROUNDING = ['NONE' => 'None', 'NEAREST' => 'Nearest', 'UP' => 'Ceiling', 'DOWN' => 'Floor'];

    private static function f(string $key, string $label, string $type = 'n', ?string $opt = null, string $group = 'common', int $span = 1, bool $geo = false): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'opt' => $opt, 'group' => $group, 'span' => $span, 'geo' => $geo];
    }

    /** @return array<string, string> kelompok => judul */
    public static function groups(): array
    {
        return ['part' => 'Part', 'paper' => 'Paper & geometry', 'print' => 'Print & process rates', 'sheet' => 'Sheet / single face', 'lam' => 'Lamination', 'common' => 'Common costs', 'tool' => 'Tooling'];
    }

    // Mode Integrasi: geometri (kunci GEOMETRY_FIELDS) terkunci bila part mengambil data ProDev.
    public static function integrated(?string $type): array
    {
        $dc = in_array($type, self::DC_FAMILY, true);
        $sheet = in_array($type, self::SHEET, true);
        $lam = in_array($type, self::LAM, true);
        $o = [self::f('part_name', 'Part name', 't', null, 'part'), self::f('part_type_code', 'Part type', 's', 'partTypes', 'part'), self::f('qty_per_set', 'Qty per set', 'n', null, 'part')];
        if ($dc) {
            array_push($o,
                self::f('grammage_id', 'Grammage / material', 's', 'grammages', 'paper', 2),
                self::f('i_dc_persen_override', 'DC % (override)', 'n', null, 'paper'),
                self::f('i_panjang_plano_cm', 'Plano length (cm)', 'n', null, 'paper', 1, true),
                self::f('i_lebar_plano_cm', 'Plano width (cm)', 'n', null, 'paper', 1, true),
                self::f('i_cut_per_plano', '1 plano cut into', 'n', null, 'paper', 1, true),
                self::f('i_isi_per_lb', 'Per print sheet (LB)', 'n', null, 'paper', 1, true),
                self::f('i_uk_cetak_p_cm', 'Print size L (cm)', 'n', null, 'paper', 1, true),
                self::f('i_uk_cetak_l_cm', 'Print size W (cm)', 'n', null, 'paper', 1, true),
                self::f('i_jumlah_warna', 'Colours', 'n', null, 'print'),
                self::f('i_ongkos_cetak_rim', 'Print rate / ream', 'n', null, 'print'),
                self::f('i_ongkos_plong_lb', 'Die-cut rate / LB', 'n', null, 'print'),
                self::f('i_ongkos_lem_pc', 'Glue rate / pc', 'n', null, 'print'),
                self::f('i_ongkos_hotprint_pc', 'Hot print rate / pc', 'n', null, 'print'),
                self::f('i_film_rate', 'Film rate', 's', 'filmRates', 'tool'),
                self::f('i_film_faktor', 'Film factor', 'n', null, 'tool'),
                self::f('i_ongkos_plate_warna', 'Plate (per colour)', 'n', null, 'tool'),
                self::f('i_pisau_faktor', 'Knife factor', 'n', null, 'tool'),
                self::f('i_pisau_rate_rp', 'Knife rate (per order)', 'n', null, 'tool'),
            );
        }
        if ($lam || $sheet) {
            array_push($o,
                self::f('sheet_rate_id', 'Substance / SF type', 's', $type === 'DC+SF' ? 'sfSubst' : 'sheetRates', 'sheet', 2),
                self::f('supplier_sheet_code', 'Sheet supplier', 's', 'suppliers', 'sheet'),
                self::f('i_sheet_panjang_cm', 'Piece length SF/SH (cm)', 'n', null, 'sheet'),
                self::f('i_sheet_lebar_cm', 'Piece width SF (cm)', 'n', null, 'sheet'),
            );
        }
        if ($sheet || $type === 'DC+SHEET') {
            array_push($o, self::f('i_lebar_trim_cm', 'Trim width (cm)', 'n', null, 'sheet'), self::f('i_isi_per_trim', 'Per trim', 'n', null, 'sheet'), self::f('i_discount_rm', 'Discount / surcharge', 'n', null, 'sheet'));
        }
        if ($sheet) {
            array_push($o,
                self::f('flute_type', 'Flute type', 's', 'fluteChoices', 'sheet'),
                self::f('i_isi_per_lb_kerja', 'Per working LB', 'n', null, 'sheet'),
                self::f('i_ongkos_print_pc', 'Print rate / pc', 'n', null, 'sheet'),
                self::f('i_ongkos_diecut_pc', 'Die cut rate / pc', 'n', null, 'sheet'),
                self::f('i_ongkos_slitter_pc', 'Slitter rate / pc', 'n', null, 'sheet'),
                self::f('i_ongkos_slotter_pc', 'Slotter rate / pc', 'n', null, 'sheet'),
                self::f('join_type_code', 'Join type', 's', 'joinTypes', 'sheet'),
                self::f('i_ongkos_join_pc', 'Join rate / pc', 'n', null, 'sheet'),
                self::f('i_ongkos_partisi_pc', 'Partition rate / pc', 'n', null, 'sheet'),
                self::f('i_ongkos_layer_pc', 'Layer rate / pc', 'n', null, 'sheet'),
                self::f('i_ongkos_pisau_pc', 'Die knife rate / pc', 'n', null, 'sheet'),
            );
        }
        if ($lam) {
            array_push($o,
                self::f('pakai_laminasi', 'Use lamination', 'b', null, 'lam'),
                self::f('i_lam_sheet_flat_pc', 'Lam sheet flat (per pc)', 'n', null, 'lam'),
                self::f('sf_ef_rate_id', 'SF EF rate (E/F)', 's', 'sfEf', 'lam'),
                self::f('i_sf_ef_rate_override', 'SF EF price (override)', 'n', null, 'lam'),
                self::f('i_lam_pakai_ukuran_sf', 'Use SF size?', 'b', null, 'lam'),
                self::f('i_ongkos_lam_rp_cm2_sh', 'Lam SH rate (per cm²)', 'n', null, 'lam'),
                self::f('i_ongkos_lam_rp_cm2_sf', 'Lam SF rate (per cm²)', 'n', null, 'lam'),
                self::f('i_ongkos_laminasi_pc', 'Manual lamination rate', 'n', null, 'lam'),
            );
        }
        array_push($o,
            self::f('i_ongkos_kupas_pc', 'Peel rate / pc'), self::f('i_ongkos_qc_pack_pc', 'QC rate / pc'), self::f('i_ongkos_kirim_pc', 'Delivery rate / pc'),
            self::f('packing_type_code', 'Packing type', 's', 'packingTypes'), self::f('i_ongkos_packing_pc', 'Packing rate / pc'),
        );

        return $o;
    }

    // Mode Instant: dimensi per part dan semua parameter manual; tampil per tipe part (program HPP lama).
    public static function instant(?string $type): array
    {
        $is = fn (string ...$t) => in_array($type, $t, true);
        $o = [self::f('no_urut', 'Part no.', 'n', null, 'part'), self::f('part_name', 'Part name', 't', null, 'part'), self::f('part_type_code', 'Part type', 's', 'partTypes', 'part')];
        if ($is('DC', 'DC+SF', 'DC+SHEET', 'KRAFT')) {
            array_push($o, self::f('i_jumlah_warna', 'Colours', 'n', null, 'paper'), self::f('grammage_id', 'Grammage / material', 's', 'grammages', 'paper', 2), self::f('i_paper_price_override', 'Paper price (override)', 'n', null, 'paper'));
        }
        array_push($o,
            self::f('i_dim_panjang_mm', 'Length (mm)', 'n', null, 'paper'), self::f('i_dim_lebar_mm', 'Width (mm)', 'n', null, 'paper'),
            self::f('i_dim_tinggi_mm', 'Height (mm)', 'n', null, 'paper'), self::f('i_lidah_lem_mm', 'Glue flap (mm)', 'n', null, 'paper'),
        );
        if ($is('DC', 'DC+SF', 'DC+SHEET', 'KRAFT')) {
            array_push($o, self::f('i_panjang_plano_cm', 'Plano length (cm)', 'n', null, 'paper'), self::f('i_lebar_plano_cm', 'Plano width (cm)', 'n', null, 'paper'),
                self::f('i_cut_per_plano', '1 plano cut into', 'n', null, 'paper'), self::f('i_isi_per_lb', 'Per print LB', 'n', null, 'paper'));
        } else {
            array_push($o, self::f('i_cut_per_plano', '1 plano cut into', 'n', null, 'paper'), self::f('i_isi_per_lb_kerja', 'Per working LB', 'n', null, 'paper'));
        }
        if ($is('DC', 'DC+SF', 'DC+SHEET')) {
            array_push($o, self::f('i_dc_persen_override', 'DC % (override)', 'n', null, 'paper'), self::f('i_uk_cetak_p_cm', 'Print size L (cm)', 'n', null, 'paper'), self::f('i_uk_cetak_l_cm', 'Print size W (cm)', 'n', null, 'paper'));
        }
        if ($is('DC+SF', 'DC+SHEET', 'SHEET', 'SHEET_DIGITAL')) {
            array_push($o, self::f('sheet_rate_id', 'Substance / SF type', 's', $type === 'DC+SF' ? 'sfSubstInstant' : 'sheetRates', 'sheet', 2), self::f('supplier_sheet_code', 'Sheet supplier', 's', 'suppliers', 'sheet'),
                self::f('i_sheet_panjang_cm', 'Piece length SF/SH (cm)', 'n', null, 'sheet'));
        }
        if ($is('DC+SF')) {
            $o[] = self::f('i_sheet_lebar_cm', 'Piece width SF (cm)', 'n', null, 'sheet');
        }
        if ($is('DC+SHEET', 'SHEET', 'SHEET_DIGITAL')) {
            array_push($o, self::f('i_lebar_trim_cm', 'Trim width (cm)', 'n', null, 'sheet'), self::f('i_isi_per_trim', 'Per trim', 'n', null, 'sheet'), self::f('i_discount_rm', 'Discount / surcharge', 'n', null, 'sheet'));
        }
        if ($is('SHEET_DIGITAL', 'SHEET')) {
            $o[] = self::f('flute_type', 'Flute type (geometry automatic)', 's', 'flutes', 'sheet');
        }
        if ($is('DC', 'DC+SF', 'DC+SHEET', 'KRAFT')) {
            array_push($o, self::f('i_ongkos_cetak_rim', 'Print rate / ream', 'n', null, 'print'), self::f('i_ongkos_plong_lb', 'Die-cut rate / LB', 'n', null, 'print'), self::f('i_ongkos_lem_pc', 'Glue rate / pc', 'n', null, 'print'));
        }
        if ($is('DC', 'DC+SF', 'DC+SHEET')) {
            $o[] = self::f('i_ongkos_hotprint_pc', 'Hot print rate / pc', 'n', null, 'print');
        }
        array_push($o, self::f('i_ongkos_kupas_pc', 'Peel rate / pc'), self::f('i_ongkos_qc_pack_pc', 'QC rate / pc'), self::f('i_ongkos_kirim_pc', 'Delivery rate / pc'),
            self::f('packing_type_code', 'Packing type', 's', 'packingTypes'), self::f('i_ongkos_packing_pc', 'Packing rate / pc'));
        if ($is('DC+SF', 'DC+SHEET')) {
            array_push($o, self::f('pakai_laminasi', 'Use lamination', 'b', null, 'lam'), self::f('i_lam_sheet_flat_pc', 'Lam sheet flat (per pc)', 'n', null, 'lam'), self::f('sf_ef_rate_id', 'SF EF rate (E/F)', 's', 'sfEf', 'lam'),
                self::f('i_sf_ef_rate_override', 'SF EF price (override)', 'n', null, 'lam'), self::f('i_lam_pakai_ukuran_sf', 'Use SF size?', 'b', null, 'lam'),
                self::f('i_ongkos_lam_rp_cm2_sh', 'Lam SH rate (per cm²)', 'n', null, 'lam'), self::f('i_ongkos_lam_rp_cm2_sf', 'Lam SF rate (per cm²)', 'n', null, 'lam'), self::f('i_ongkos_laminasi_pc', 'Manual lamination rate', 'n', null, 'lam'));
        }
        if ($is('DC', 'DC+SF', 'DC+SHEET', 'KRAFT')) {
            array_push($o, self::f('i_film_rate', 'Film rate', 's', 'filmRates', 'tool'), self::f('i_film_faktor', 'Film factor', 'n', null, 'tool'), self::f('i_ongkos_plate_warna', 'Plate (per colour)', 'n', null, 'tool'),
                self::f('i_pisau_faktor', 'Knife factor', 'n', null, 'tool'), self::f('i_pisau_rate_rp', 'Knife rate (per order)', 'n', null, 'tool'));
        }
        if ($is('SHEET', 'SHEET_DIGITAL')) {
            array_push($o, self::f('i_ongkos_print_pc', 'Print rate / pc', 'n', null, 'sheet'), self::f('i_ongkos_diecut_pc', 'Die cut rate / pc', 'n', null, 'sheet'), self::f('i_ongkos_slitter_pc', 'Slitter rate / pc', 'n', null, 'sheet'),
                self::f('i_ongkos_slotter_pc', 'Slotter rate / pc', 'n', null, 'sheet'), self::f('join_type_code', 'Join type', 's', 'joinTypes', 'sheet'), self::f('i_ongkos_join_pc', 'Join rate / pc', 'n', null, 'sheet'),
                self::f('i_ongkos_partisi_pc', 'Partition rate / pc', 'n', null, 'sheet'), self::f('i_ongkos_layer_pc', 'Layer rate / pc', 'n', null, 'sheet'), self::f('i_ongkos_pisau_pc', 'Die knife rate / pc', 'n', null, 'sheet'));
        }

        return $o;
    }

    // Jenis Flute Mode Integrasi (pilihan tetap v1).
    public const FLUTE_INTEGRATED = ['B/F' => 'B/F', 'C/F' => 'C/F', 'BC/F' => 'BC/F'];
}
