<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;

// Pass akhir multi bahasa: label konstanta model / service yang tampil di layar v2 (QuoActivity, ProdMachineModel,
// ProdProductAcc, InquiryDerivedWriter::SCOPES), subject e-mail, dan kunci t() v2 lama yang belum punya terjemahan.
// Terjemahan Indonesia = teks lama, supaya tampilan bahasa id tetap.
return new class extends Migration
{
    private const ID = [
        // Kunci v2 lama tanpa seed
        'Search inquiry no. / customer / product...' => 'Cari no. inquiry / customer / produk...',
        'All Products' => 'Semua Produk',
        'No Digital ACC yet' => 'Belum ACC Digital',
        'Digital ACC done' => 'Sudah ACC Digital',
        'Not Confirmed' => 'Belum Confirm',
        'This part has no dieline type yet (assembly, or a manual part without a Product Type), so there is no dieline to change here.' => 'Part ini belum punya tipe dieline (rakitan, atau part manual tanpa Tipe Produk), jadi tidak ada dieline yang bisa diubah di sini.',
        "e.g. trucking to the customer's warehouse" => 'mis. trucking ke gudang customer',
        'Master Product Market' => 'Master Product Market',
        // ProdMachineModel::DRIVES
        'Automatic' => 'Otomatis',
        // ProdProductAcc::TYPES / CHANNELS
        'ACC E-mail (design)' => 'ACC E-mail (desain)',
        'ACC Shape Sample (plain box)' => 'ACC Sample Bentuk (box polos)',
        'In person' => 'Langsung',
        // InquiryDerivedWriter::SCOPES ('Structure / Joint' sudah di-seed 'Struktur / Joint')
        'Structure / Joining' => 'Struktur / Sambungan',
        // Purchase Quotation: kotak statistik
        'Needs check' => 'Perlu cek',
        // Mail
        'Email Verification — :company Careers' => 'Verifikasi Email — Karir :company',
    ];

    // Konteks 'badge': nilai badge yang juga dipakai sebagai kunci tones / where grid v2 (lihat migration 000923).
    private const BADGE = [
        // PurchaseQuotation::READ_LABELS
        'Ready to pick' => 'Siap dipilih',
        'Needs check' => 'Perlu cek',
        'Manual entry' => 'Isi manual',
        'Read failed' => 'Gagal dibaca',
        // Schema::changes() (kode kind di schema-changes.json)
        'New table' => 'tabel baru',
        'New column' => 'kolom baru',
        'Table dropped' => 'tabel dihapus',
        'Column dropped' => 'kolom dihapus',
        'Type changed' => 'tipe berubah',
    ];

    public function up(): void
    {
        Dictionary::seed(self::ID);
        Dictionary::seed(self::BADGE, 'badge');
    }

    public function down(): void {}
};
