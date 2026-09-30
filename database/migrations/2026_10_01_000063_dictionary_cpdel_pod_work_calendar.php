<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Terjemahan Indonesia untuk teks English layar CPDEL (dua janji), Proof of Delivery, Holiday Calendar, Office Hours,
// Customer Receiving Hours, Delivery Delay Reasons (LOCKED 2026-10-01: kode English, kamus menerjemahkan). Hanya mengisi yang
// belum punya terjemahan -- terjemahan yang sudah diubah user di Setup > Translation Dictionary tidak ditimpa.
return new class extends Migration
{
    private const UI = [
        'Every delivery line holds two promises that must match exactly: the slot agreed with the customer and the time production commits to be ready.' => 'Setiap baris kirim memegang dua janji yang harus cocok tepat: slot yang disepakati dengan customer dan jam siap yang dijanjikan produksi.',
        'Waiting for PPIC' => 'Menunggu PPIC', 'Production has not committed a ready time' => 'Produksi belum menjanjikan jam siap',
        'Need negotiation' => 'Perlu negosiasi', 'Ready later than the customer slot' => 'Siap lebih lambat dari slot customer',
        'Escalated' => 'Dieskalasi', 'Customer rejected; Operational Manager decides' => 'Customer menolak; Operational Manager memutuskan',
        'Late' => 'Terlambat', 'Slot date passed, not shipped' => 'Tanggal slot lewat, belum dikirim', 'Ship today' => 'Kirim hari ini',
        'in the next 7 days' => 'dalam 7 hari ke depan', 'Slot time not set' => 'Jam slot belum diisi', 'Agree the exact time with the customer' => 'Sepakati jam tepat dengan customer',
        'Delivery promise kept (this month)' => 'Janji kirim ditepati (bulan ini)', 'Aligned' => 'Selaras', 'Shipped' => 'Terkirim',
        'Customer slot' => 'Slot customer', 'Time' => 'Jam', 'Production ready' => 'Siap produksi', 'Gap (days)' => 'Selisih (hari)', 'Original promise' => 'Janji awal',
        'Production' => 'Produksi', 'Promise' => 'Janji', 'not set' => 'belum diisi', 'time not set' => 'jam belum diisi', 'not committed' => 'belum dijanjikan',
        'Gap' => 'Selisih', 'days late' => 'hari terlambat', 'on time' => 'tepat waktu', 'order' => 'order', 'Customer receives that day' => 'Customer menerima hari itu',
        'Due date on the order' => 'Due date di order', 'not posted' => 'belum di-post', 'Posted' => 'Posted', 'Open Customer Order' => 'Buka Customer Order',
        'Next: PPIC commits the time the goods will be ready.' => 'Berikutnya: PPIC menjanjikan jam barang siap.',
        'Next: Sales negotiates with the customer (WhatsApp / call), then changes the slot or splits the shipment. If the customer refuses, record it to escalate.' => 'Berikutnya: Sales bernegosiasi dengan customer (WhatsApp / telepon), lalu mengubah slot atau memecah kiriman. Bila customer menolak, catat untuk eskalasi.',
        'Next: the Operational Manager decides (overtime, priority, subcontract, or a new offer).' => 'Berikutnya: Operational Manager memutuskan (lembur, prioritas, subkon, atau tawaran baru).',
        'Both promises match. Keep the slot; Delivery records the arrival in Proof of Delivery.' => 'Kedua janji cocok. Pertahankan slot; bagian Pengiriman mencatat kedatangan di Proof of Delivery.',
        'The slot date has passed and the goods are not shipped: agree a new slot with the customer now.' => 'Tanggal slot sudah lewat dan barang belum dikirim: sepakati slot baru dengan customer sekarang.',
        'Shipped. The arrival and the customer receipt are recorded in Proof of Delivery.' => 'Terkirim. Kedatangan dan penerimaan customer dicatat di Proof of Delivery.',
        'Production promise (PPIC)' => 'Janji produksi (PPIC)',
        'When will the goods be ready to load? If it is later than the customer slot, choose the reason: Sales will negotiate with the customer.' => 'Kapan barang siap dimuat? Bila lebih lambat dari slot customer, pilih alasannya: Sales akan bernegosiasi dengan customer.',
        'Ready date' => 'Tanggal siap', 'Ready time' => 'Jam siap', 'Reason if later than the slot' => 'Alasan bila lewat slot', 'Work Order due' => 'Due Work Order',
        'Change the customer slot (Sales)' => 'Ubah slot customer (Sales)',
        'Posted to Production: only the slot time can change. Record who agreed and how.' => 'Sudah Posted to Production: hanya jam slot yang bisa diubah. Catat siapa yang setuju dan lewat apa.',
        'After the customer agrees by WhatsApp or phone: move the whole line, or fill Split qty to send part of it at the new slot. The slot must be inside Telasih office hours and the customer receiving hours.' => 'Setelah customer setuju lewat WhatsApp atau telepon: pindahkan seluruh baris, atau isi Qty split untuk mengirim sebagian di slot baru. Slot wajib di dalam jam kantor Telasih dan jam terima customer.',
        'Slot date' => 'Tanggal slot', 'Slot time' => 'Jam slot', 'Split qty (optional)' => 'Qty split (opsional)', 'Less than' => 'Kurang dari',
        'to split; empty = move the whole line' => 'untuk dipecah; kosong = pindahkan seluruh baris', 'Reason' => 'Alasan', 'Agreed via' => 'Disepakati lewat',
        'Customer contact (who agreed)' => 'Kontak customer (yang menyetujui)', 'Customer refused' => 'Customer menolak',
        'The customer does not accept a new slot or split. This escalates the line to the Operational Manager.' => 'Customer tidak menerima slot baru atau split. Baris ini dieskalasi ke Operational Manager.',
        'Via' => 'Lewat', 'Customer contact' => 'Kontak customer', 'What the customer said' => 'Kata customer', 'Escalate' => 'Eskalasi',
        'Decision (Operational Manager)' => 'Keputusan (Operational Manager)', 'Production must then commit a new ready time that meets the slot.' => 'Produksi lalu wajib menjanjikan jam siap baru yang memenuhi slot.',
        'Decision' => 'Keputusan', 'Instruction' => 'Instruksi', "Waiting for the Operational Manager's decision." => 'Menunggu keputusan Operational Manager.',
        'Join lines' => 'Gabung baris', 'Join with other lines of the same CO and product; the result goes to the earliest date.' => 'Gabungkan dengan baris lain dari CO dan product yang sama; hasilnya di tanggal paling awal.',
        'Join with' => 'Gabungkan dengan', 'Join' => 'Gabung', 'Negotiation log' => 'Log negosiasi', 'When' => 'Kapan', 'Who' => 'Siapa', 'Event' => 'Kejadian',
        'Change' => 'Perubahan', 'Via / contact' => 'Lewat / kontak', 'No changes yet: the line is still at its original promise.' => 'Belum ada perubahan: baris masih di janji awalnya.',
        'All delivery lines of this product' => 'Semua baris kirim product ini', 'Slot' => 'Slot', 'Split from' => 'Pecahan dari', 'this line' => 'baris ini', 'ready' => 'siap',
        'Ready committed' => 'Janji siap diisi', 'Rescheduled' => 'Dijadwal ulang', 'Split' => 'Split', 'Merged' => 'Digabung', 'Customer rejected' => 'Customer menolak', 'Ops decision' => 'Keputusan Ops',
        'Search CO no., customer, product...' => 'Cari no. CO, customer, product...', 'CO No.' => 'No. CO',
        'Production promise saved.' => 'Janji produksi tersimpan.', 'Customer slot changed and logged.' => 'Slot customer diubah dan tercatat.',
        'Escalated to the Operational Manager.' => 'Dieskalasi ke Operational Manager.', 'Decision saved. Production must commit a new ready time.' => 'Keputusan tersimpan. Produksi wajib menjanjikan jam siap baru.',
        'Lines joined into the earliest date.' => 'Baris digabung ke tanggal paling awal.',
        'Ready after the agreed slot: choose the reason so Sales can negotiate.' => 'Siap setelah slot yang disepakati: pilih alasannya supaya Sales bisa bernegosiasi.',
        'The date is a holiday (Holiday Calendar).' => 'Tanggal itu hari libur (Holiday Calendar).', 'Telasih office is closed on that day (Office Hours).' => 'Kantor Telasih tutup di hari itu (Office Hours).',
        'The time is outside Telasih office hours' => 'Jam di luar jam kantor Telasih', 'The customer does not receive goods on that day (Customer Receiving Hours).' => 'Customer tidak menerima barang di hari itu (Customer Receiving Hours).',
        'The time is outside the customer receiving hours' => 'Jam di luar jam terima customer',
        'Posted to Production: only the slot time can change here; the date and qty are locked.' => 'Sudah Posted to Production: di sini hanya jam slot yang bisa diubah; tanggal dan qty terkunci.',
        'This line is already shipped.' => 'Baris ini sudah terkirim.', 'Split from line' => 'Pecahan dari baris', 'Joined lines' => 'Baris digabung', 'The split line was not created.' => 'Baris pecahan tidak terbentuk.',
        'Proof of Delivery' => 'Bukti Terima (Proof of Delivery)', 'Promised date' => 'Tanggal janji', 'Arrived' => 'Tiba', 'Result' => 'Hasil', 'Waiting' => 'Menunggu',
        'Kept' => 'Ditepati', 'Missed' => 'Meleset', 'Retry' => 'Kirim ulang', 'Search delivery note no., customer...' => 'Cari no. surat jalan, customer...',
        'Delivery Note' => 'Surat Jalan', 'Promised' => 'Dijanjikan', 'Customer receives' => 'Customer menerima', 'Arrived at customer' => 'Tiba di customer',
        'Received by (name)' => 'Diterima oleh (nama)', 'Cause if promise missed' => 'Penyebab bila janji meleset',
        'Required when the truck arrived late, the customer did not receive, or it was not shipped.' => 'Wajib bila truk tiba terlambat, customer tidak menerima, atau tidak jadi dikirim.',
        'Photo of signed delivery note' => 'Foto surat jalan bertanda tangan', 'Notes' => 'Catatan', 'Recorded by' => 'Dicatat oleh', 'Photo' => 'Foto',
        'No arrival recorded yet.' => 'Belum ada kedatangan tercatat.', 'Delivery finished. The result is locked as the KPI record.' => 'Pengiriman selesai. Hasilnya terkunci sebagai catatan KPI.',
        'Received' => 'Diterima', 'Partially received' => 'Diterima sebagian', 'Customer closed, not received' => 'Customer tutup, tidak diterima', 'Rejected by customer' => 'Ditolak customer', 'Not shipped' => 'Tidak jadi dikirim',
        'Arrival recorded: promise kept.' => 'Kedatangan tercatat: janji ditepati.', 'Arrival recorded: promise missed.' => 'Kedatangan tercatat: janji meleset.', 'Promise missed: choose the cause.' => 'Janji meleset: pilih penyebabnya.',
        'Holiday Calendar' => 'Kalender Libur', 'Office Hours' => 'Jam Kantor', 'Customer Receiving Hours' => 'Jam Terima Customer', 'Delivery Delay Reasons' => 'Alasan Kirim Tertunda',
        'National holiday' => 'Libur nasional', 'Collective leave' => 'Cuti bersama', 'Company holiday' => 'Libur perusahaan', 'Office closed (Office Hours)' => 'Kantor tutup (Jam Kantor)',
        'Holiday' => 'Hari libur', 'Holiday Name (English)' => 'Nama Libur (English)', 'Holiday saved.' => 'Hari libur tersimpan.', 'Search holiday name...' => 'Cari nama libur...',
        'Working Day' => 'Hari Kerja', 'Start' => 'Mulai', 'End' => 'Selesai', 'Open' => 'Buka', 'Close' => 'Tutup', 'Break from' => 'Istirahat dari', 'Break to' => 'Istirahat sampai',
        'Customer (empty = default)' => 'Customer (kosong = default)', 'Responsible department' => 'Departemen penanggung jawab', 'Reason (English)' => 'Alasan (English)',
        'WhatsApp' => 'WhatsApp', 'Phone call' => 'Telepon', 'Meeting' => 'Rapat', 'Overtime' => 'Lembur', 'Re-prioritize production' => 'Ubah prioritas produksi',
        'Subcontract' => 'Subkon', 'Make customer a new offer' => 'Tawaran baru ke customer', 'Purchasing' => 'Purchasing', 'Maintenance' => 'Maintenance', 'Delivery' => 'Pengiriman',
        'Customer' => 'Customer', 'Other' => 'Lainnya', 'Changing an agreed slot needs a reason.' => 'Mengubah slot yang sudah disepakati wajib ada alasan.',
        'Slot time set' => 'Jam slot disepakati', 'Required when an agreed slot changes or the shipment is split' => 'Wajib bila slot yang disepakati berubah atau kiriman dipecah',
    ];

    public function up(): void
    {
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            return;
        }
        $db = DB::connection('tm2sys');
        foreach (self::UI as $en => $id) {
            $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if (! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
    }

    public function down(): void
    {
        // isi kamus dibiarkan (bisa sudah diubah user)
    }
};
