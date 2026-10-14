<?php

/*
 * REMARK
 * Tujuan       : Membaca satu PDF Master Card (formulir Telasih Makmur, hasil ekspor Excel) menjadi array yang sama dengan MasterCardReader (xlsx).
 * Sumber hulu  : Berkas PDF di docs/attachments/mastercard; gambar artwork dari folder extracted/ di sebelahnya (nama = slug nama PDF).
 * Input        : Path PDF. Butuh program pdftotext (PATH atau env PDFTOTEXT_BIN).
 * Output       : card_code, design_no, product_name, part_label, card_date, customer_text, dim_l/w/h, knife_*, print_*, materials[], finishing[],
 *                join_text, up_text, notes, process[], colors{first,second}, acc{}, artwork (path berkas atau null). Semua ukuran mm.
 * Dipakai oleh : MasterCardDummyEngine (command dummy:mastercard).
 * Status       : Aktif (dummy ddt*). Nilai dibaca dari posisi kolom teks: tiap nilai milik label terdekat di atasnya pada kolom yang sama.
 */

namespace App\Services\MasterCard;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class MasterCardPdfReader
{
    // Label formulir; "ACC Digital" harus lebih dulu dari "Digital" supaya tidak terpotong.
    private const LABELS = ['No. Desain', 'Nama Barang', 'Tanggal', 'Kode Item', 'Nama Pelanggan', 'Dimensi P', 'Dimensi L', 'Dimensi T', 'Kode Pisau',
        'Ukuran Pisau', 'Material', 'Kualitas', 'Ukuran Bahan', 'Ukuran Cetak', 'Jenis Finishing', 'Bentuk Sambungan', 'Bentuk', 'Isi dalam Satu Cetakan',
        'Keterangan', 'Urutan Pekerjaan', 'Warna Pertama', 'Warna Cetak', 'Warna Kedua', 'Unit \d', 'ACC E-mail', 'ACC Digital', 'Digital', 'Box Polos',
        'Di ACC Oleh', 'Tgl ACC'];

    private const MONTHS = ['mei' => 'may', 'agu' => 'aug', 'agt' => 'aug', 'okt' => 'oct', 'des' => 'dec', 'sept' => 'sep', 'maret' => 'march'];

    public function __construct(private readonly MasterCardReader $units = new MasterCardReader) {}

    public function read(string $path): array
    {
        $labels = [];
        $values = [];
        foreach ($this->lines($this->text($path)) as $line => $text) {
            foreach ($this->pieces($text) as [$col, $s, $isLabel]) {
                if ($isLabel) {
                    $labels[] = ['key' => preg_replace('/\s*\d$/', '', rtrim($s, ': ')), 'line' => $line, 'col' => $col, 'end' => $col + strlen($s), 'v' => []];
                } else {
                    $values[] = [$line, $col, $s];
                }
            }
        }
        foreach ($values as [$line, $col, $s]) {
            $owner = null;
            foreach ($labels as $i => $l) {
                $above = $l['line'] < $line || ($l['line'] === $line && $l['end'] <= $col);
                if ($above && $l['col'] <= $col + 3 && (! $owner || [$l['col'], $l['line']] >= [$labels[$owner]['col'], $labels[$owner]['line']])) {
                    $owner = $i;
                }
            }
            if ($owner !== null) {
                $labels[$owner]['v'][] = trim(ltrim($s, ': '));
            }
        }
        $all = fn (string $k) => array_values(array_filter(array_merge(...array_column(array_filter($labels, fn ($l) => $l['key'] === $k), 'v') ?: [[]]),
            fn ($v) => $v !== '' && $v !== '-'));
        $one = fn (string $k) => $all($k) ? implode(' ', $all($k)) : null;

        $name = $one('Nama Barang');
        $label = $this->partLabel((string) $name);
        $materials = [];
        $sizes = $this->sizes($all('Ukuran Bahan'));
        foreach ($all('Material') as $i => $m) {
            [$sp, $sl] = $this->units->pair($sizes[$i] ?? null);
            $materials[] = ['name' => $m, 'quality' => $all('Kualitas')[$i] ?? null, 'size_text' => $sizes[$i] ?? null, 'size_p' => $sp, 'size_l' => $sl];
        }
        $acc = [];
        foreach (['ACC E-mail' => 'email', 'Digital' => 'digital', 'Box Polos' => 'box_polos', 'Di ACC Oleh' => 'acc_by', 'ACC Digital' => 'acc_digital', 'Tgl ACC' => 'acc_date'] as $k => $key) {
            if ($v = $one($k)) {
                $acc[$key] = $v;
            }
        }
        [$kw, $kh] = $this->units->pair($one('Ukuran Pisau'));
        [$pw, $ph] = $this->units->pair($all('Ukuran Cetak')[0] ?? null);

        return [
            'card_code' => $one('Kode Item'),
            'design_no' => $one('No. Desain'),
            'product_name' => $this->cleanName((string) $name),
            'part_label' => $label,
            'card_date' => $this->units->date($this->date($one('Tanggal'))),
            'customer_text' => $one('Nama Pelanggan'),
            'dim_l' => $this->units->length($one('Dimensi P')), 'dim_w' => $this->units->length($one('Dimensi L')), 'dim_h' => $this->units->length($one('Dimensi T')),
            'knife_code' => $one('Kode Pisau'), 'knife_w' => $kw, 'knife_h' => $kh, 'print_w' => $pw, 'print_h' => $ph,
            'materials' => $materials,
            'finishing' => array_filter([$one('Jenis Finishing')]),
            'join_text' => $one('Bentuk Sambungan'),
            'up_text' => $all('Isi dalam Satu Cetakan')[0] ?? null,
            'notes' => $one('Keterangan'),
            'process' => $this->steps($all('Urutan Pekerjaan')),
            'colors' => $this->colors($labels),
            'acc' => $acc,
            'artwork' => $this->artwork($path),
        ];
    }

    private function text(string $path): string
    {
        $run = new Process([$this->binary(), '-layout', $path, '-']);
        $run->run();
        if (! $run->isSuccessful()) {
            throw new RuntimeException('pdftotext gagal untuk '.basename($path).': '.trim($run->getErrorOutput()));
        }

        return explode('ACC PROOF', $run->getOutput())[0];
    }

    // env PDFTOTEXT_BIN, lalu PATH, lalu pdftotext bawaan Git for Windows (dipakai bila terminal tidak punya PATH-nya).
    private function binary(): string
    {
        $finder = new ExecutableFinder;
        foreach (array_filter([getenv('PDFTOTEXT_BIN') ?: null, 'pdftotext', 'C:/Program Files/Git/mingw64/bin/pdftotext.exe', 'C:/Program Files/Git/usr/bin/pdftotext.exe']) as $c) {
            if ($found = is_file($c) ? $c : $finder->find($c)) {
                return $found;
            }
        }

        throw new RuntimeException('pdftotext tidak ditemukan. Pasang xpdf / poppler dan masukkan ke PATH, atau isi PDFTOTEXT_BIN di .env.mockup.');
    }

    private function lines(string $text): array
    {
        return array_values(array_filter(preg_split('/\R/', $text), fn ($l) => trim($l) !== ''));
    }

    // Potongan teks per baris: [kolom, teks, apakah label]. Dipisah di 2+ spasi, lalu di tiap label yang menempel di tengah teks.
    private function pieces(string $line): array
    {
        $re = '/('.implode('|', self::LABELS).'):/u';
        $out = [];
        preg_match_all('/\S+(?: \S+)*/u', $line, $segs, PREG_OFFSET_CAPTURE);
        // Angka berunit yang menempel di teks sebelumnya ("YUKHSINU FOOD SOLUTION 20 cm") dipisah, kecuali bagian dari "a x b cm".
        $chunks = [];
        foreach ($segs[0] as [$seg, $col]) {
            foreach (preg_split('/(?<![xX\d,.]) (?=\d+(?:[.,]\d+)?\s*cm\b)/u', $seg, -1, PREG_SPLIT_OFFSET_CAPTURE) as [$part, $off]) {
                $chunks[] = [$part, $col + $off];
            }
        }
        foreach ($chunks as [$seg, $col]) {
            $at = 0;
            preg_match_all($re, $seg, $m, PREG_OFFSET_CAPTURE);
            foreach ($m[0] as [$lab, $pos]) {
                if (($before = trim(substr($seg, $at, $pos - $at))) !== '') {
                    $out[] = [$col + $at, $before, false];
                }
                $out[] = [$col + $pos, $lab, true];
                $at = $pos + strlen($lab);
            }
            if (($rest = trim(substr($seg, $at))) !== '') {
                $out[] = [$col + $at + (strlen(substr($seg, $at)) - strlen(ltrim(substr($seg, $at)))), $rest, false];
            }
        }

        return $out;
    }

    // Unit 1..4 di bawah "Warna Pertama/Cetak" = warna pertama, di bawah "Warna Kedua" = warna kedua.
    private function colors(array $labels): array
    {
        usort($labels, fn ($a, $b) => [$a['line'], $a['col']] <=> [$b['line'], $b['col']]);
        $set = 'first';
        $out = ['first' => [], 'second' => []];
        foreach ($labels as $l) {
            if ($l['key'] === 'Warna Kedua') {
                $set = 'second';
            } elseif ($l['key'] === 'Unit' && ($v = trim(implode(' ', $l['v']))) !== '' && $v !== '-') {
                $out[$set][] = $v;
            }
        }

        return $out;
    }

    // Langkah yang terpotong baris ("& Ikat 200 / ikat", "Cetak di mesin" + nama mesin) disambung ke langkah sebelumnya.
    private function steps(array $lines): array
    {
        $out = [];
        foreach ($lines as $l) {
            $last = count($out) - 1;
            if ($last >= 0 && (str_starts_with($l, '&') || preg_match('/mesin\s*:?$/i', $out[$last]))) {
                $out[$last] .= ' '.$l;
            } else {
                $out[] = $l;
            }
        }

        return $out;
    }

    // Satu ukuran per bahan: baris yang terpotong ("... cm/120 x" + "102 cm") disambung; alternatif setelah "/" diabaikan.
    private function sizes(array $lines): array
    {
        $merged = [];
        foreach ($lines as $l) {
            if ($merged && ! preg_match('/\d\s*x\s*\d/i', $l)) {
                $merged[count($merged) - 1] .= ' '.$l;
            } else {
                $merged[] = $l;
            }
        }

        return array_map(fn ($l) => preg_match('/\d+(?:[.,]\d+)?\s*x\s*\d+(?:[.,]\d+)?\s*cm/i', $l, $m) ? $m[0] : $l, $merged);
    }

    private function partLabel(string $name): ?string
    {
        return match (true) {
            (bool) preg_match('/\bSET\s*(\d+)\s*$/i', $name, $m) => 'SET '.$m[1],
            (bool) preg_match('/^Cover\b/i', $name) => 'COVER',
            (bool) preg_match('/\s\(?([AB])\)?\s*$/', $name, $m) => $m[1],
            default => null,
        };
    }

    // "Cover Buku ... (DS002948) SET 7" -> nama produk bersama untuk semua bagian.
    private function cleanName(string $name): string
    {
        return trim(preg_replace(['/^Cover\s+/i', '/\s*\(DS\d+\)/i', '/\s*SET\s*\d+\s*$/i'], '', $name));
    }

    private function date(?string $v): ?string
    {
        return $v ? strtr(strtolower($v), self::MONTHS) : null;
    }

    // Gambar bentuk/dieline dari kartu: extracted/<slug nama PDF>.(png|jpg|jpeg).
    private function artwork(string $pdf): ?string
    {
        $slug = Str::slug(pathinfo($pdf, PATHINFO_FILENAME));

        return glob(dirname($pdf).'/extracted/'.$slug.'.{png,jpg,jpeg}', GLOB_BRACE)[0] ?? null;
    }
}
