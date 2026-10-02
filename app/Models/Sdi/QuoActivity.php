<?php

namespace App\Models\Sdi;

// Follow-up quotation = aktivitas CRM yang menunjuk satu quotation (tabel yang sama, tm2sdi.crm_activity). Kelas ini
// tetap ada untuk layar Quotation v1 / v2; daftar jenis & hasil di bawah adalah label lama layar-layar itu.
class QuoActivity extends CrmActivity
{
    public const TYPES = [
        'CALL' => ['📞', 'Telepon'],
        'VISIT' => ['🚗', 'Kunjungan'],
        'WHATSAPP' => ['💬', 'WhatsApp'],
        'EMAIL' => ['✉️', 'Email'],
        'MEETING' => ['🤝', 'Meeting'],
        'OTHER' => ['📝', 'Lainnya'],
    ];

    public const RESULTS = [
        'POSITIVE' => 'Positif',
        'NEUTRAL' => 'Netral',
        'NEGATIVE' => 'Negatif',
        'NO_ANSWER' => 'Tidak terhubung',
    ];
}
