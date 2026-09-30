<?php

namespace App\Services\Pur;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

// Baca file penawaran supplier -> header + baris harga (belum dicocokkan ke master; itu PqMatcher).
// Dengan ANTHROPIC_API_KEY: semua jenis file dibaca Claude (Excel dikirim sebagai teks CSV). Tanpa key: hanya Excel/CSV
// yang bisa dibaca (tebak kolom dari judul), PDF/foto -> null = officer isi manual. Hasil selalu Draft, dicek officer.
final class PqFileReader
{
    public const ACCEPT = ['pdf', 'xlsx', 'xls', 'csv', 'jpg', 'jpeg', 'png', 'webp'];

    private const SHEET = ['xlsx', 'xls', 'csv'];

    private const IMAGE = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    /** @return array{via: string, supplier_name: ?string, quote_date: ?string, valid_until: ?string, currency: ?string, payment_term: ?string, lead_time_day: ?int, lines: list<array{text: string, qty: ?float, uom: ?string, price: ?float, lead_time_day: ?int}>}|null */
    public static function read(string $path, string $ext): ?array
    {
        $ext = strtolower($ext);
        if (config('services.anthropic.key')) {
            return self::viaClaude($path, $ext);
        }

        return in_array($ext, self::SHEET, true) ? self::viaSheet($path) : null;
    }

    public static function aiEnabled(): bool
    {
        return (bool) config('services.anthropic.key');
    }

    private static function viaClaude(string $path, string $ext): array
    {
        $file = match (true) {
            $ext === 'pdf' => ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode((string) file_get_contents($path))]],
            isset(self::IMAGE[$ext]) => ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => self::IMAGE[$ext], 'data' => base64_encode((string) file_get_contents($path))]],
            default => ['type' => 'text', 'text' => "Isi file spreadsheet (CSV):\n".self::sheetAsCsv($path)],
        };

        $nullable = fn (string $type) => ['type' => [$type, 'null']];
        $schema = [
            'type' => 'object',
            'properties' => [
                'supplier_name' => $nullable('string'), 'quote_date' => $nullable('string'), 'valid_until' => $nullable('string'),
                'currency' => $nullable('string'), 'payment_term' => $nullable('string'), 'lead_time_day' => $nullable('integer'),
                'lines' => ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'properties' => ['text' => ['type' => 'string'], 'qty' => $nullable('number'), 'uom' => $nullable('string'),
                        'price' => $nullable('number'), 'lead_time_day' => $nullable('integer')],
                    'required' => ['text', 'qty', 'uom', 'price', 'lead_time_day'],
                    'additionalProperties' => false,
                ]],
            ],
            'required' => ['supplier_name', 'quote_date', 'valid_until', 'currency', 'payment_term', 'lead_time_day', 'lines'],
            'additionalProperties' => false,
        ];

        try {
            $msg = (new Client(apiKey: config('services.anthropic.key')))->beta->messages->create(
                model: config('services.anthropic.model'),
                maxTokens: 16000,
                betas: ['server-side-fallback-2026-07-01'],
                fallbacks: 'default',
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
                system: 'You read supplier price quotations (penawaran harga) sent to CV. Telasih Makmur, an Indonesian offset printing and packaging company. '
                    .'Extract the header and every quoted item line exactly as written. Rules: supplier_name = the company that SENT the quotation (not Telasih Makmur). '
                    .'Dates as YYYY-MM-DD; if only a validity period is given (e.g. "berlaku 14 hari"), compute valid_until from quote_date. '
                    .'currency = ISO code (IDR when prices are in Rupiah). price = unit price as a plain number in the quotation currency, before tax, without thousand separators '
                    .'(Indonesian "1.185.000" means 1185000). uom = the unit the price refers to (rim, lembar, kg, roll, pcs, ...). text = the item description as written, '
                    .'including size and grammage. Skip subtotal, discount, tax (PPN) and total rows. Use null for anything not stated; never guess.',
                messages: [['role' => 'user', 'content' => [$file, ['type' => 'text', 'text' => 'Extract this quotation.']]]],
            );
        } catch (APIException $e) {
            throw new RuntimeException('Pembaca AI gagal: '.$e->getMessage(), 0, $e);
        }

        if ($msg->stopReason === 'refusal') {
            throw new RuntimeException('Pembaca AI menolak file ini.');
        }
        $json = null;
        foreach ($msg->content as $block) {
            if ($block->type === 'text') {
                $json = json_decode($block->text, true);
                break;
            }
        }
        if (! is_array($json)) {
            throw new RuntimeException('Pembaca AI tidak mengembalikan data.');
        }

        return ['via' => 'AI'] + $json + ['lines' => []];
    }

    // Tanpa AI: cari baris judul (Nama Barang / Qty / Satuan / Harga) di 30 baris teratas, lalu baca baris di bawahnya.
    private static function viaSheet(string $path): array
    {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
        $cols = null;
        $start = 0;
        foreach (array_slice($rows, 0, 30, true) as $i => $row) {
            $map = [];
            foreach ($row as $c => $cell) {
                $h = mb_strtolower(trim((string) $cell));
                $kind = match (true) {
                    $h === '' => null,
                    (str_contains($h, 'harga') || str_contains($h, 'price')) && ! preg_match('/total|jumlah harga|subtotal|amount/', $h) => 'price',
                    (bool) preg_match('/^(qty|jml|jumlah|kuantitas|quantity|banyak)/', $h) => 'qty',
                    (bool) preg_match('/^(sat|satuan|unit|uom)\b/', $h) => 'uom',
                    (bool) preg_match('/barang|item|deskripsi|description|uraian|nama|produk|spesifikasi/', $h) => 'text',
                    default => null,
                };
                if ($kind && ! isset($map[$kind])) {
                    $map[$kind] = $c;
                }
            }
            if (isset($map['text'], $map['price'])) {
                [$cols, $start] = [$map, $i + 1];
                break;
            }
        }

        $lines = [];
        foreach ($cols ? array_slice($rows, $start) : [] as $row) {
            $text = trim((string) ($row[$cols['text']] ?? ''));
            $price = self::number($row[$cols['price']] ?? null);
            if ($text === '' || preg_match('/^(sub ?total|total|ppn|diskon|discount|grand)/i', $text)) {
                continue;
            }
            $lines[] = ['text' => $text, 'qty' => isset($cols['qty']) ? self::number($row[$cols['qty']] ?? null) : null,
                'uom' => isset($cols['uom']) ? (trim((string) ($row[$cols['uom']] ?? '')) ?: null) : null, 'price' => $price, 'lead_time_day' => null];
        }

        return ['via' => 'EXCEL', 'supplier_name' => null, 'quote_date' => null, 'valid_until' => null, 'currency' => null,
            'payment_term' => null, 'lead_time_day' => null, 'lines' => $lines];
    }

    private static function sheetAsCsv(string $path): string
    {
        $out = '';
        foreach (IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false) as $row) {
            if (array_filter($row, fn ($v) => $v !== null && $v !== '')) {
                $out .= implode(';', array_map(fn ($v) => str_replace(["\n", ';'], [' ', ','], (string) $v), $row))."\n";
            }
        }

        return $out;
    }

    // "Rp 1.185.000,50" / "1,185,000.50" / 1185000 -> 1185000.5
    public static function number(mixed $v): ?float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = preg_replace('/[^\d.,-]/', '', (string) $v);
        if ($s === '' || $s === '-') {
            return null;
        }
        $lastDot = strrpos($s, '.');
        $lastComma = strrpos($s, ',');
        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $s = str_replace(',', '.', str_replace('.', '', $s));
        } elseif ($lastDot !== false && (substr_count($s, '.') > 1 || (strlen($s) - $lastDot === 4 && $lastComma === false))) {
            $s = str_replace('.', '', $s);
        } else {
            $s = str_replace(',', '', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }
}
