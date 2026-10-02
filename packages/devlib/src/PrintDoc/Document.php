<?php

namespace Devlib\PrintDoc;

use Closure;
use Devlib\Lang;
use Devlib\Locale;
use Devlib\Terbilang;
use InvalidArgumentException;

// Induk SEMUA cetakan dokumen transaksi (x-dl::print-doc). Tata letak dikunci di sini: kop, judul, nomor, tanggal + status,
// blok field 2 kolom, tabel (No | Nama | Qty | Unit | zona varian | Harga | Subtotal), catatan + total, tanda tangan, kaki.
// Satu dokumen = satu kelas turunan yang HANYA mendeklarasikan field yang berbeda (properti di bawah) dan menyerahkan data
// mentah (angka desimal, tanggal ISO). Format angka / tanggal / terbilang ikut bahasa cetak (Devlib\Locale).
// Aturan user 2026-10-01: kertas A5 landscape untuk 1-5 baris, A4 portrait di atas itu; dokumen tanpa nilai uang tidak
// punya total; tanda tangan dari kiri = pihak luar, Menyetujui, Mengetahui, Pembuat; kaki = "Dicetak pada ... oleh ...".
// Semua label DITULIS ENGLISH (huruf kapital diatur CSS) dan diterjemahkan Devlib\Lang::text().
//
//   class DeliveryNote extends Document {
//       public string $title = 'Delivery Note';
//       public array $refs = [['key' => 'co_no', 'label' => 'CO Ref.'], ['key' => 'vehicle', 'label' => 'Vehicle', 'hand' => true]];
//       public array $extra = [['key' => 'remark', 'label' => 'Remarks', 'width' => 84]];
//   }
//   <x-dl::print-doc :doc="new DeliveryNote(['number' => .., 'date' => .., 'status' => .., 'party' => [..], 'rows' => [..]])" />
abstract class Document
{
    public const A5_MAX = 5;                                   // sampai 5 baris = A5 landscape

    private const ZONE = ['plain' => 84, 'priced' => 38];      // mm, lebar zona kolom varian

    private const QTY_ZONE = 36;                               // mm, kolom Qty + Unit (ikut zona varian bila $qtyCols = false)

    private const PRICE_COLS = [['key' => 'price', 'label' => 'Price', 'width' => 21, 'type' => 'money'], ['key' => 'subtotal', 'label' => 'Subtotal', 'width' => 25, 'type' => 'money']];

    // ---- yang boleh berbeda per jenis dokumen ----
    public string $type = 'document';                          // kunci jenis dokumen (doc-share, konstanta penanda tangan)

    public string $title = '';

    public ?string $subtitle = null;

    public bool $titleCompact = false;                         // true = judul panjang dicetak dengan huruf lebih kecil

    public string $numberLabel = 'No.';

    /** @var list<array{key: string, label: string, type?: string, hand?: bool}> slot kiri blok field (di bawah nomor) */
    public array $refs = [];

    public string $partyLabel = '';                            // kanan: nama pihak + baris alamat

    public ?array $side = null;                                // slot kanan paling bawah

    public string $nb = '';

    public bool $nbPlain = false;                              // true = kalimat pembuka, tanpa awalan "NB:"

    public bool $nbRaw = false;                                // true = $nb sudah jadi (memuat data dokumen, mis. rekening): dicetak apa adanya, tidak diterjemahkan

    public string $nameLabel = 'Item Name';

    /** @var list<array{key: string, label: string, width: int|float, type?: string, hand?: bool, wrap?: bool}> kolom zona varian */
    public array $extra = [];

    public bool $priced = false;                               // true = kolom harga + kotak total + terbilang

    public bool $qtyCols = true;                               // false = tanpa kolom Qty / Unit

    /** @var list<array{0: string, 1?: string}> pihak luar / penerima, paling kiri */
    public array $receivers = [];

    // Judul slot kosong ('') = slot itu tidak dicetak (mis. tanda terima: tanpa "Approved By").
    public array $signLabels = ['approve' => 'Approved By', 'know' => 'Acknowledged By', 'make' => 'Prepared By'];

    public array $signNotes = ['approve' => '', 'know' => '', 'make' => ''];

    public string $qrHint = 'online document';

    public array $capacity = ['a5' => 5, 'a4' => 22];          // baris per lembar

    public float $rowHeight = 7.6;                             // mm

    public int $infoRows = 6;                                  // baris blok field

    public float $tailHeight = 18.5;                           // mm, zona catatan + total

    public string $currency = 'IDR';

    private static ?Closure $companyResolver = null;

    private static ?Closure $signerResolver = null;

    private static array $labels = [];

    // $data: number, date, status, party [name, code?, lines[]], rows [[name, sub?, sub2?, qty?, unit?, cls?, <kolom>..]],
    //        notes [[label, teks]], ppn / pph / disc (persen), printedBy (nama orang yang login), + nilai tiap key di $refs / $side.
    public function __construct(public array $data = [])
    {
        $this->data['rows'] = array_values($this->prepare($this->data['rows'] ?? []));
        $width = array_sum(array_column($this->extra, 'width'));
        $must = self::ZONE[$this->priced ? 'priced' : 'plain'] + ($this->qtyCols ? 0 : self::QTY_ZONE);
        if (abs($width - $must) > 0.01) {
            throw new InvalidArgumentException(static::class.": variant columns are {$width} mm wide, must be {$must} mm");
        }
    }

    // Turunan boleh mengolah baris sebelum dicetak (mis. menandai split, menghitung durasi).
    protected function prepare(array $rows): array
    {
        return $rows;
    }

    // Kop perusahaan diambil aplikasi pemakai: fn (bool $ppn) => ['name', 'initials', 'lines' => [..], 'npwp' => ?string, 'logo' => ?url].
    // 'logo' diisi = gambar logo (sudah memuat nama perusahaan) menggantikan kotak inisial + nama.
    public static function companyUsing(?Closure $fn): void
    {
        self::$companyResolver = $fn;
    }

    // Nama penanda tangan per dokumen (konstanta aplikasi): fn (string $type) => ['approve' => ?string, 'know' => ?string].
    public static function signersUsing(?Closure $fn): void
    {
        self::$signerResolver = $fn;
    }

    // Istilah cetak tambahan milik aplikasi (teks English => Indonesia), mis. label field dokumen.
    public static function labels(array $id): void
    {
        self::$labels = array_merge(self::$labels, $id);
    }

    // Label cetak: bahasa Indonesia dari kamus cetak (Labels + tambahan aplikasi); selain itu kamus aplikasi.
    public static function t(?string $en): string
    {
        if ($en === null || trim($en) === '') {
            return (string) $en;
        }
        if (Locale::lang() === 'id') {
            $id = self::$labels[$en] ?? Labels::ID[$en] ?? null;
            if ($id !== null) {
                return $id;
            }
        }

        return Lang::text($en);
    }

    public function isPpn(): bool
    {
        return (float) ($this->data['ppn'] ?? 0) > 0;
    }

    public function company(): array
    {
        $c = self::$companyResolver ? (self::$companyResolver)($this->isPpn()) : [];

        return $c + ['name' => '', 'initials' => '', 'lines' => [], 'npwp' => null, 'logo' => null];
    }

    public function rows(): array
    {
        return $this->data['rows'];
    }

    public function paper(): string
    {
        return count($this->rows()) <= self::A5_MAX ? 'a5' : 'a4';
    }

    /** @return list<array{rows: array, from: int, last: bool}> lembar-lembar; total + tanda tangan hanya di lembar terakhir */
    public function sheets(): array
    {
        $per = $this->capacity[$this->paper()];
        $chunks = array_chunk($this->rows(), $per) ?: [[]];
        $n = count($chunks);

        return array_map(fn ($rows, $i) => ['rows' => array_pad($rows, $per, null), 'from' => $i * $per, 'last' => $i === $n - 1, 'page' => $i + 1, 'pages' => $n], $chunks, array_keys($chunks));
    }

    /** @return list<array> kolom zona varian + (dokumen berharga) Harga dan Subtotal di posisi tetap */
    public function columns(): array
    {
        return array_merge($this->extra, $this->priced ? $this->priceColumns() : []);
    }

    // Dua kolom nilai di posisi tetap (46 mm). Bawaan Price + Subtotal; dokumen jurnal menggantinya dengan Debit + Credit
    // -- lebar total harus tetap sama supaya posisi kolom tidak bergeser antar dokumen.
    protected function priceColumns(): array
    {
        return self::PRICE_COLS;
    }

    public function columnClass(array $c): string
    {
        return in_array($c['type'] ?? '', ['money', 'int'], true) ? 'is-num' : (! empty($c['hand']) ? 'is-hand' : (! empty($c['wrap']) ? 'is-wrap' : ''));
    }

    // Nilai satu field / sel, siap tampil (HTML aman). Kosong: garis isi tangan bila 'hand', selain itu tanda pisah.
    public function value(array $def, ?array $src = null, string $empty = '&mdash;'): string
    {
        $v = ($src ?? $this->data)[$def['key']] ?? null;
        if ($v === null || $v === '') {
            return ! empty($def['hand']) && $src === null ? '<span class="dl-pdoc__hand"></span>' : $empty;
        }

        return match ($def['type'] ?? 'text') {
            'date' => e(Locale::date($v)),
            'money' => e(Locale::number($v, 2)),
            'int' => e(Locale::number($v, 0)),
            'html' => (string) $v,
            default => e((string) $v),
        };
    }

    public function qty(mixed $v): string
    {
        return $v === null || $v === '' ? '' : Locale::number($v, 2);
    }

    // Kotak total (hanya dokumen berharga): ['lines' => [[label, teks, tebal]], 'grand' => float|null].
    // Dokumen yang totalnya sudah dihitung dan disimpan sistem menyerahkannya apa adanya lewat $data['sums']:
    //   ['lines' => [[label English, angka, 'minus' => bool?, 'suffix' => ' 11%'?]], 'grand' => angka] -- cetakan tidak menghitung ulang.
    public function totals(): array
    {
        if (! $this->priced) {
            return ['lines' => [], 'grand' => null];
        }
        if (isset($this->data['sums'])) {
            $lines = array_map(function (array $l) {
                $amount = Locale::number($l[1], 2);

                return [self::t($l[0]).($l['suffix'] ?? ''), ! empty($l['minus']) ? '('.$amount.')' : $amount, false];
            }, $this->data['sums']['lines'] ?? []);
            $grand = (float) $this->data['sums']['grand'];
            $lines[] = [self::t('Total').' ('.$this->currency.')', Locale::number($grand, 2), true];

            return ['lines' => $lines, 'grand' => $grand];
        }
        $d = $this->data;
        $r2 = fn ($v) => round($v, 2);
        $sub = $r2(array_sum(array_map(fn ($r) => (float) ($r['subtotal'] ?? 0), $this->rows())));
        $disc = $r2($sub * (float) ($d['disc'] ?? 0) / 100);
        $dpp = $sub - $disc;
        $ppn = $r2($dpp * (float) ($d['ppn'] ?? 0) / 100);
        $pph = $r2($dpp * (float) ($d['pph'] ?? 0) / 100);
        $grand = $r2($dpp + $ppn - $pph);
        $num = fn ($v) => Locale::number($v, 2);
        $pct = fn ($v) => Locale::decimal($v, 2).'%';
        $lines = [[self::t('Subtotal'), $num($sub), false]];
        if ($disc) {
            $lines[] = [self::t('Discount').' '.$pct($d['disc']), '('.$num($disc).')', false];
        }
        $lines[] = [self::t('VAT').' '.$pct($d['ppn'] ?? 0), $num($ppn), false];
        if ($pph) {
            $lines[] = [self::t('Income Tax 23').' '.$pct($d['pph']), '('.$num($pph).')', false];
        }
        $lines[] = [self::t('Total').' ('.$this->currency.')', $num($grand), true];

        return ['lines' => $lines, 'grand' => $grand];
    }

    // Terbilang sampai sen (cetakan yang disetujui user menulis sen; Terbilang IDR sendiri berhenti di rupiah).
    public function amountWords(float $amount): string
    {
        $whole = floor($amount);
        $cents = (int) round(($amount - $whole) * 100);
        $words = Terbilang::words((int) $whole, $this->currency);
        if ($cents > 0 && $this->currency === 'IDR') {
            $words .= ' '.lcfirst(Terbilang::words($cents)).' sen';
        }

        return '# '.$words.' #';
    }

    /** @return list<array{0: string, 1: string}> catatan zona penutup: terbilang (bila berharga) + catatan dokumen */
    public function notes(): array
    {
        $grand = $this->totals()['grand'];
        $notes = $grand ? [[self::t('In Words'), $this->amountWords($grand)]] : [];

        return array_merge($notes, array_map(fn ($n) => [self::t($n[0]), (string) $n[1]], $this->data['notes'] ?? []));
    }

    /** @return list<array{0: string, 1: string, 2: ?string}> slot tanda tangan terisi: [peran, keterangan, nama] */
    public function signatures(): array
    {
        $names = self::$signerResolver ? (self::$signerResolver)($this->type) : [];
        $d = $this->data;
        $slot = fn (string $k, ?string $name) => [self::t($this->signLabels[$k]), self::t($this->signNotes[$k]), $name ?: null];
        $out = array_map(fn ($r) => [self::t($r[0]), self::t($r[1] ?? ''), null], $this->receivers);
        $out[] = $slot('approve', $d['approvedBy'] ?? $names['approve'] ?? null);
        $out[] = $slot('know', $d['knownBy'] ?? $names['know'] ?? null);
        $out[] = $slot('make', $d['printedBy'] ?? null);

        return array_values(array_filter($out, fn (array $s) => $s[0] !== ''));
    }

    public function printedLine(): string
    {
        return trim(self::t('Printed on').' '.Locale::stamp(now()).' '.(($this->data['printedBy'] ?? null) ? Lang::t('by').' '.$this->data['printedBy'] : ''));
    }

    public function fileTitle(): string
    {
        return self::t($this->title);
    }
}
