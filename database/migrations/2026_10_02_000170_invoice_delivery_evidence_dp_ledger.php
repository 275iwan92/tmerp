<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// User 2026-10-02: "setiap tagihan harus related dengan surat jalan pengiriman dan validasi dari customer nilai yang
// terkirim, karena bisa ada retur dan rusak di jalan. Uang muka jelas dari CO yang mana dan historinya terlacak."
//   tm2sdi  do_line.receive_diff_reason + receive_note   mengapa customer menerima kurang dari yang dikirim (Proof of Delivery)
//   tm2acc  fin_account                                  satu akun bank per rekening perusahaan (rekonsiliasi per rekening)
//   tm2sys  sys_app_menu                                 To Invoice, AR Aging, Down Payment Ledger
// Hanya berjalan di database yang sudah punya modul Customer Invoice (fin_company_bank).
return new class extends Migration
{
    // kode => [kode menu sebelahnya (tempat + hak akses), urutan, label, ikon, slug, jenis objek]
    private const MENUS = [
        'FIN_TO_INVOICE' => ['FIN_AR_INVOICE', 21, 'To Invoice', 'fa-solid fa-truck-ramp-box', 'acc-to-invoice', 'grid'],
        'ACC_RPT_AR_AGING' => ['ACC_TB', 60, 'AR Aging', 'fa-solid fa-hourglass-half', 'acc-rpt-ar-aging', 'grid'],
        'ACC_RPT_DP_LEDGER' => ['ACC_TB', 62, 'Down Payment Ledger', 'fa-solid fa-money-bill-transfer', 'acc-rpt-dp-ledger', 'grid'],
    ];

    private const UI = [
        // Proof of Delivery
        'Difference' => 'Selisih', 'Reason for Difference' => 'Alasan Selisih', 'Damaged in transit' => 'Rusak di jalan', 'Returned by customer' => 'Diretur customer',
        'Short shipped' => 'Kurang kirim', 'Other' => 'Lainnya', 'Condition / Note' => 'Kondisi / Catatan',
        'Received by Customer' => 'Diterima Customer', 'Not received yet.' => 'Belum diterima.',
        'Result Received: every line counts as fully received. Partially received: type what the customer really received per line and why the rest was not received. Only the received quantity can be invoiced.' => 'Hasil Received: semua baris dianggap diterima penuh. Partially received: ketik yang benar-benar diterima customer per baris dan alasan sisanya tidak diterima. Hanya qty diterima yang bisa ditagih.',
        // Customer Invoice: tab Delivery + uang muka
        'Delivery' => 'Pengiriman', 'Delivery Date' => 'Tgl Kirim', 'Arrived' => 'Tiba', 'Received by' => 'Diterima oleh', 'Signed Note' => 'SJ Bertanda Tangan',
        'Every line of this invoice comes from a delivery note the customer has received. Shipped, Received and the reason for a difference come from the Proof of Delivery.' => 'Setiap baris invoice ini berasal dari Surat Jalan yang sudah diterima customer. Dikirim, Diterima, dan alasan selisih berasal dari Proof of Delivery.',
        'No delivery note behind this document.' => 'Tidak ada Surat Jalan di balik dokumen ini.', 'Photo' => 'Foto', 'No photo' => 'Tanpa foto',
        'Applied to Invoices' => 'Sudah Dipotong di Invoice', 'Down Payment Left' => 'Sisa Uang Muka', 'Open Customer Order' => 'Buka Customer Order',
        'Down payment invoiced' => 'Uang muka ditagih', 'Balance' => 'Saldo',
        'A down payment must belong to a Customer Order.' => 'Uang muka harus milik satu Customer Order.',
        'An invoice must come from delivery note lines of one Customer Order.' => 'Invoice harus berasal dari baris Surat Jalan satu Customer Order.',
        // Customer Order: tab Billing
        'Billing' => 'Penagihan', 'Order Value' => 'Nilai Order', 'Received by Customer (value)' => 'Diterima Customer (nilai)', 'Invoiced' => 'Sudah Ditagih',
        'Not Invoiced Yet' => 'Belum Ditagih', 'Down Payment Received' => 'Uang Muka Diterima', 'Down Payment Applied' => 'Uang Muka Dipotong',
        'Paid by Customer' => 'Dibayar Customer', 'Delivery Notes and Invoices' => 'Surat Jalan dan Invoice', 'Down Payment History' => 'Riwayat Uang Muka',
        'Invoices and Down Payments' => 'Invoice dan Uang Muka', 'No delivery note for this order yet.' => 'Belum ada Surat Jalan untuk order ini.',
        'No down payment for this order.' => 'Tidak ada uang muka untuk order ini.', 'No invoice for this order yet.' => 'Belum ada invoice untuk order ini.',
        'Waiting proof of delivery' => 'Menunggu bukti terima', 'Ready to invoice' => 'Siap ditagih', 'Partly invoiced' => 'Ditagih sebagian', 'Fully invoiced' => 'Sudah ditagih penuh',
        'Read only. Invoices and money are recorded in Finance > Customer Invoice.' => 'Hanya baca. Invoice dan uang dicatat di Finance > Customer Invoice.',
        // laporan (nama menu / laporan tetap English)
        'Search order no., customer, delivery note...' => 'Cari no order, customer, Surat Jalan...', 'Ready to Invoice (value)' => 'Siap Ditagih (nilai)',
        'Waiting Proof of Delivery (value)' => 'Menunggu Bukti Terima (nilai)', 'Oldest Delivery' => 'Kirim Paling Lama',
        'Orders with delivery notes not fully invoiced. Ready = the customer has received the goods (Proof of Delivery); open the row to make the invoice. Waiting = shipped, the signed delivery note is not recorded yet.' => 'Order dengan Surat Jalan yang belum habis ditagih. Siap = customer sudah menerima barang (Proof of Delivery); buka barisnya untuk membuat invoice. Menunggu = sudah dikirim, Surat Jalan bertanda tangan belum dicatat.',
        'Search invoice no., customer...' => 'Cari no invoice, customer...', 'Days Overdue' => 'Hari Lewat', 'Not Due' => 'Belum Jatuh Tempo', 'Age' => 'Umur',
        '1-30 Days' => '1-30 Hari', '31-60 Days' => '31-60 Hari', '61-90 Days' => '61-90 Hari', 'Over 90 Days' => 'Lebih 90 Hari',
        'Unpaid confirmed invoices by age past the due date, as of today. Group by Customer for the total per customer.' => 'Invoice Confirmed yang belum lunas menurut umur lewat jatuh tempo, per hari ini. Kelompokkan per Customer untuk total tiap customer.',
        'Search order no., customer, down payment no...' => 'Cari no order, customer, no uang muka...', 'Money In' => 'Uang Masuk', 'Deducted' => 'Dipotong', 'Received' => 'Diterima',
        'Every down payment event per Customer Order: invoiced, money received, applied to an invoice. Balance = money received and not applied yet (still owed to the customer as goods).' => 'Semua peristiwa uang muka per Customer Order: ditagih, uang diterima, dipotong di invoice. Saldo = uang diterima yang belum dipotong (masih terutang ke customer berupa barang).',
        'Billed' => 'Ditagih', 'Money received' => 'Uang diterima',
    ];

    public function up(): void
    {
        if (! Schema::connection('tm2acc')->hasTable('fin_company_bank')) {
            return;
        }
        $sdi = Schema::connection('tm2sdi');
        if (! $sdi->hasColumn('do_line', 'receive_diff_reason')) {
            $sdi->table('do_line', function (Blueprint $t) {
                $t->string('receive_diff_reason', 20)->nullable()->after('qty_received'); // DAMAGED | RETURNED | SHORT | OTHER
                $t->string('receive_note', 150)->nullable()->after('receive_diff_reason');
            });
        }
        $this->bankAccounts();
        $this->menus();
        $this->dictionary();
    }

    // Satu akun buku besar per rekening perusahaan, di sebelah 111200. Hanya untuk rekening yang belum punya akun sendiri
    // (kosong, atau masih menunjuk akun sementara 111200). Finance boleh mengubahnya di Master of Accounts / Company Bank Accounts.
    private function bankAccounts(): void
    {
        $acc = DB::connection('tm2acc');
        $model = $acc->table('fin_account')->where('account_code', '111200')->first();
        if (! $model) {
            return;
        }
        foreach ($acc->table('fin_company_bank')->orderBy('id_company_bank')->get() as $i => $bank) {
            if ($bank->gl_account_code && $bank->gl_account_code !== '111200') {
                continue;
            }
            $code = '1112'.($i + 1).'0';
            if (! $acc->table('fin_account')->where('account_code', $code)->exists()) {
                $acc->table('fin_account')->insert([
                    'account_code' => $code, 'account_name' => mb_substr('Bank '.$bank->bank_name.' - '.$bank->account_holder, 0, 100),
                    'account_type' => $model->account_type, 'normal_balance' => $model->normal_balance, 'parent_id' => $model->parent_id, 'is_recon' => $model->is_recon,
                    'is_postable' => 1, 'fs_line' => $model->fs_line, 'cf_line' => $model->cf_line, 'activate' => 1,
                    'created_by' => 'migration', 'created_at' => now(), 'updated_by' => 'migration', 'updated_at' => now(),
                ]);
            }
            $acc->table('fin_company_bank')->where('id_company_bank', $bank->id_company_bank)->update(['gl_account_code' => $code, 'updated_by' => 'migration', 'updated_at' => now()]);
        }
    }

    private function menus(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        foreach (self::MENUS as $code => [$sibling, $sort, $label, $icon, $slug, $object]) {
            $near = $menu()->where('code', $sibling)->first();
            if ($near && ! $menu()->where('code', $code)->exists()) {
                $menu()->insert(['parent_id' => $near->parent_id, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item', 'object_type' => $object,
                    'target' => 'v2:'.$slug, 'sort_order' => $sort, 'activate' => 1, 'required_object' => $near->required_object, 'status_code' => 'READY_FOR_QC',
                    'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now()]);
            }
        }
    }

    private function dictionary(): void
    {
        $db = DB::connection('tm2sys');
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            return;
        }
        foreach (self::UI as $en => $id) {
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
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', array_keys(self::MENUS))->delete();
        $sdi = Schema::connection('tm2sdi');
        if ($sdi->hasColumn('do_line', 'receive_diff_reason')) {
            $sdi->table('do_line', fn (Blueprint $t) => $t->dropColumn(['receive_diff_reason', 'receive_note']));
        }
    }
};
