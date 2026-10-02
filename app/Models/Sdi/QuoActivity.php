<?php

namespace App\Models\Sdi;

// Follow-up quotation = aktivitas CRM yang menunjuk satu quotation (tabel yang sama, tm2sdi.crm_activity). Kelas ini
// tetap ada untuk layar Quotation v1 / v2. Yang disimpan = KODE (kunci array); labelnya English, tampil lewat
// typeLabel() / resultLabel() (kamus t()).
class QuoActivity extends CrmActivity
{
    public const TYPES = [
        'CALL' => ['📞', 'Phone call'],
        'VISIT' => ['🚗', 'Visit'],
        'WHATSAPP' => ['💬', 'WhatsApp'],
        'EMAIL' => ['✉️', 'Email'],
        'MEETING' => ['🤝', 'Meeting'],
        'OTHER' => ['📝', 'Other'],
    ];

    public const RESULTS = [
        'POSITIVE' => 'Positive',
        'NEUTRAL' => 'Neutral',
        'NEGATIVE' => 'Negative',
        'NO_ANSWER' => 'No answer',
    ];

    public static function typeLabel(?string $code): string
    {
        return isset(self::TYPES[$code]) ? t(self::TYPES[$code][1]) : (string) $code;
    }

    public static function resultLabel(?string $code): string
    {
        return isset(self::RESULTS[$code]) ? t(self::RESULTS[$code]) : (string) $code;
    }

    // Pilihan select: kode => ikon + label tampil.
    public static function typeOptions(): array
    {
        return collect(self::TYPES)->map(fn ($t, $code) => $t[0].' '.self::typeLabel($code))->all();
    }

    public static function resultOptions(): array
    {
        return collect(self::RESULTS)->map(fn ($l, $code) => self::resultLabel($code))->all();
    }
}
