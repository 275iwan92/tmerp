<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Label menu (sys_app_menu.label) yang masih Indonesia -> English (LOCKED user 2026-10-01: database English, bahasa lain
// lewat kamus). Menu v2 kini menerjemahkan labelnya saat tampil (App\Support\V2\AppMenu::label), jadi teks Indonesia yang
// lama diisi ke kamus: tampilan menu dalam bahasa Indonesia TIDAK berubah, mode English tidak lagi menampilkan Indonesia.
// Baris menu yang tidak aktif ikut diganti labelnya tetapi tanpa pasangan kamus (tidak tampil).
return new class extends Migration
{
    // English => label Indonesia lama (baris menu aktif)
    private const ACTIVE = [
        'Attendance Log' => 'Presensi',
        'Attendance Upload' => 'Upload Presensi',
        'GPS Clock-In (Mobile)' => 'Absen GPS (Mobile)',
        'Employees Without WhatsApp No.' => 'Karyawan Tanpa No. WhatsApp',
        'Employee Data Correction' => 'Koreksi Data Karyawan',
        'Daily Discipline Report' => 'Laporan Kedisiplinan Harian',
        'GPS Location Approval' => 'Approval Lokasi GPS',
        'Leave Approval' => 'Approval Cuti/Sakit/Izin',
        'Organization Chart' => 'Struktur Organisasi',
        'Master Position' => 'Master Posisi',
        'Overtime' => 'Lembur',
        'Employee Bank Accounts' => 'Master Karyawan & Rekening Bank',
        'Bank Transfer Generator' => 'Generator Transfer Bank',
        'Payroll Calculation' => 'Perhitungan Gaji',
        'Cash Roster & Denominations' => 'Roster Cash & Pecahan Uang',
        'Print & Send Payslips' => 'Cetak & Kirim Slip Gaji',
        'Employee Salary & Tax' => 'Master Gaji & Pajak Pegawai',
        'Psychotest / Interview Results' => 'Input Hasil Psikotes/Interview',
        'Interview Schedule' => 'Jadwal Interview',
        'Provisioning to ERP' => 'Provisioning ke ERP',
        'Manpower Requisition' => 'Permohonan Tenaga Kerja',
        'Candidate Form Review' => 'Review Formulir Kandidat',
        'Sync Log Summary' => 'Rekap Sync Log',
        'Vacancy Posting & Channel Monitoring' => 'Posting Lowongan & Monitoring Channel',
        'Employee Self-Service' => 'Self-Service Karyawan',
        'Petty Cash' => 'Kas Kecil (Petty Cash)',
        'Master Machine' => 'Master Mesin',
        'Master Exchange Rate' => 'Master Kurs',
        'Master Raw Material Category' => 'Master Kategori Bahan Baku',
        'Master Raw Material' => 'Master Bahan Baku',
        'Master Supplier Category' => 'Master Kategori Supplier',
        'Master Unit (UoM)' => 'Master Satuan (UoM)',
        'Master Warehouse' => 'Master Gudang',
        'Management Reports' => 'Laporan Manajemen',
        'Accounting & Finance' => 'Akuntansi & Keuangan',
        'Executive Summary' => 'Ringkasan Eksekutif',
        'HR & Payroll' => 'SDM & Payroll',
        'Reports' => 'Laporan',
        'Production & Manufacturing' => 'Produksi & Manufaktur',
        'Purchase Details' => 'Rincian Pembelian',
        'Summary' => 'Ringkasan',
        'Sales Details' => 'Rincian Penjualan',
        'Warehouse & Inventory' => 'Gudang & Inventori',
        'Master Process' => 'Master Proses',
        'Machine Model Catalog' => 'Katalog Model Mesin',
        'Target Rate: Process x Machine / Employee' => 'Target Rate Proses x Mesin/Pegawai',
        'Scrap Types' => 'Kelola Tipe Scrap',
        'Shift Schedule' => 'Jadwal Shift',
        'Open Quotations' => 'Quotation Terbuka',
        'Acting Officer (PLT)' => 'Officer Pengganti (PLT)',
        'Identification' => 'Identifikasi',
        'Open Management Reports' => 'Buka Laporan Manajemen',
        'Sales Order List' => 'Daftar Sales Order',
        'Sales Reports' => 'Laporan Penjualan',
    ];

    // label Indonesia lama => English, tanpa pasangan kamus: baris tidak aktif, atau labelnya sudah English di situs lain.
    private const PLAIN = [
        'Master Produk' => 'Master Product',
        'Persetujuan Purchase Return' => 'Purchase Return Approval Center',
        'Kartu Stok' => 'Stock Card',
        'Master Stok' => 'Stock Master',
        'Mutasi / Transfer Stok' => 'Stock Movement / Transfer',
        'Nota Inquiry' => 'Inquiry Note',
        'Penjualan' => 'Sales',
        'Penyesuaian Stok (Opname)' => 'Stock Adjustment (Opname)',
        'Periode Stok' => 'Stock Period',
        'Persetujuan Penyesuaian' => 'Adjustment Approval',
        'Riwayat Mutasi' => 'Movement History',
        'Stok Barang Jadi' => 'Finished Goods Stock',
        'Tema' => 'Theme',
        'Transaksi' => 'Transactions',
    ];

    public function up(): void
    {
        $db = DB::connection('tm2sys');
        foreach (self::ACTIVE as $en => $id) {
            $db->table('sys_app_menu')->whereRaw('BINARY label = ?', [$id])->update(['label' => $en]);
        }
        foreach (self::PLAIN as $id => $en) {
            $db->table('sys_app_menu')->whereRaw('BINARY label = ?', [$id])->update(['label' => $en]);
        }
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            return;
        }
        foreach (self::ACTIVE as $en => $id) {
            $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if ($key && ! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
        Dictionary::flush();
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');
        foreach (self::ACTIVE as $en => $id) {
            $db->table('sys_app_menu')->whereRaw('BINARY label = ?', [$en])->update(['label' => $id]);
        }
    }
};
