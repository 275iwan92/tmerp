<?php

namespace App\Services\MasterCard;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use ZipArchive;

// Baca 1 file Excel master card (format docs/attachments/MC-*.xlsx, peta sel di docs/attachments_index.md).
// Data asli di sheet "MC" (formulir); sheet "Data" = sisa template (sama di semua file) -> diabaikan.
// Posisi dicari lewat LABEL ("Urutan Pekerjaan:", "Bentuk Sambungan:", ...) bukan nomor baris tetap, karena
// jumlah baris proses/keterangan beda tiap kartu. Semua ukuran dikembalikan dalam mm.
final class MasterCardReader
{
    public function read(string $path, ?string $originalName = null): array
    {
        $book = IOFactory::createReaderForFile($path)->setReadDataOnly(false)->load($path);
        $ws = $book->getSheetByName('MC') ?? throw new RuntimeException('Sheet "MC" tidak ada — bukan format master card.');
        $cells = [];
        foreach ($ws->getRowIterator(1, min(60, $ws->getHighestRow())) as $row) {
            foreach ($row->getCellIterator('A', 'H') as $cell) {
                $v = $cell->getValue();
                if ($v !== null && $v !== '') {
                    $cells[$cell->getCoordinate()] = $this->value($cell->getValue(), $cell->getCoordinate(), $ws);
                }
            }
        }
        $at = fn (string $c) => isset($cells[$c]) ? trim((string) $cells[$c]) : null;
        $find = function (string $label) use ($cells): ?array {
            foreach ($cells as $c => $v) {
                if (is_string($v) && stripos($v, $label) === 0) {
                    preg_match('/^([A-Z]+)(\d+)$/', $c, $m);

                    return [$m[1], (int) $m[2]];
                }
            }

            return null;
        };
        $below = function (?array $pos, ?string $stopLabel = null, int $max = 15) use ($cells, $find): array {
            if (! $pos) {
                return [];
            }
            [$col, $row] = $pos;
            $stop = $stopLabel ? ($find($stopLabel)[1] ?? $row + $max + 1) : $row + $max + 1;
            $out = [];
            for ($r = $row + 1; $r < $stop; $r++) {
                $v = trim((string) ($cells[$col.$r] ?? ''));
                if ($v !== '' && $v !== '-') {
                    $out[] = $v;
                }
            }

            return $out;
        };

        [$dl, $dw, $dh] = [$this->length($at('B8')), $this->length($at('C8')), $this->length($at('D8'))];
        [$kw, $kh] = $this->pair($at('F8'));
        [$pw, $ph] = $this->pair($at('E10'));

        $materials = [];
        $matPos = $find('Material:');
        $shapeRow = $find('Bentuk:')[1] ?? 15;
        for ($r = ($matPos[1] ?? 9) + 1; $r < $shapeRow; $r++) {
            if (($name = $at('A'.$r)) === null) {
                continue;
            }
            [$sp, $sl] = $this->pair($at('D'.$r));
            $materials[] = ['name' => $name, 'quality' => $at('B'.$r), 'size_text' => $at('D'.$r), 'size_p' => $sp, 'size_l' => $sl];
        }
        $finishing = [];
        for ($r = ($matPos[1] ?? 9) + 1; $r < $shapeRow; $r++) {
            if (($f = $at('F'.$r)) !== null && $f !== '-') {
                $finishing[] = $f;
            }
        }

        $inks = fn (int $from) => array_values(array_filter(array_map(fn ($r) => $at('G'.$r), range($from, $from + 3)), fn ($v) => $v !== null && $v !== '-'));
        $first = $find('Warna Pertama');
        $second = $find('Warna Kedua');

        $acc = [];
        foreach (['ACC E-mail' => 'email', 'Digital:' => 'digital', 'Box Polos' => 'box_polos', 'Di ACC Oleh' => 'acc_by', 'ACC Digital' => 'acc_digital', 'Tgl ACC' => 'acc_date'] as $label => $key) {
            if (($p = $find($label)) && ($v = $at('G'.$p[1])) !== null) {
                $acc[$key] = $v;
            }
        }

        $name = $at('E5');

        return [
            'card_code' => $at('G3'),
            'design_no' => ($d = $at('C5')) !== null && $d !== '-' ? $d : null,
            'product_name' => $name,
            'card_date' => $this->date($at('G5')),
            'customer_text' => $at('A8'),
            'dim_l' => $dl, 'dim_w' => $dw, 'dim_h' => $dh,
            'knife_code' => $at('E8'),
            'knife_w' => $kw, 'knife_h' => $kh,
            'print_w' => $pw, 'print_h' => $ph,
            'materials' => $materials,
            'finishing' => $finishing,
            'join_text' => $below($find('Bentuk Sambungan'), null, 1)[0] ?? null,
            'up_text' => $below($find('Isi dalam Satu Cetakan'), null, 1)[0] ?? null,
            'notes' => implode(' ', $below($find('Keterangan'), 'Urutan Pekerjaan')) ?: null,
            'process' => $below($find('Urutan Pekerjaan'), 'ACC PROOF', 20),
            'colors' => ['first' => $first ? $inks($first[1] + 1) : [], 'second' => $second ? $inks($second[1] + 1) : []],
            'acc' => $acc,
            'part_label' => $this->partLabel($name, $originalName),
            'raw' => $cells,
            'images' => $this->images($path),
        ];
    }

    // Gambar tertanam dibaca langsung dari zip xlsx (PhpSpreadsheet melewatkan gambar ber-anchor tertentu):
    // workbook -> sheet -> drawing -> media. Sheet MC = proof di atas pisau, sheet lain (mis. "Potongan") = rencana potong.
    private function images(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return [];
        }
        $rels = function (string $file) use ($zip): array {
            $xml = $zip->getFromName(dirname($file).'/_rels/'.basename($file).'.rels');
            if (! $xml) {
                return [];
            }
            preg_match_all('/Id="([^"]+)"[^>]*Target="([^"]+)"|Target="([^"]+)"[^>]*Id="([^"]+)"/', $xml, $m, PREG_SET_ORDER);
            $out = [];
            foreach ($m as $r) {
                [$id, $target] = $r[1] !== '' ? [$r[1], $r[2]] : [$r[4], $r[3]];
                $out[$id] = $this->resolve(dirname($file), $target);
            }

            return $out;
        };
        $wbRels = $rels('xl/workbook.xml');
        preg_match_all('/<sheet [^>]*name="([^"]+)"[^>]*r:id="([^"]+)"/', (string) $zip->getFromName('xl/workbook.xml'), $sheets, PREG_SET_ORDER);
        $out = [];
        foreach ($sheets as [, $name, $rid]) {
            foreach ($rels($wbRels[$rid] ?? '') as $drawing) {
                if (! str_contains($drawing, 'drawings/')) {
                    continue;
                }
                foreach ($rels($drawing) as $media) {
                    if (preg_match('/\.(jpe?g|png|gif|bmp)$/i', $media, $e) && ($bytes = $zip->getFromName($media))) {
                        $out[] = ['kind' => $name === 'MC' ? 'PROOF' : 'CUT_PLAN', 'bytes' => $bytes, 'ext' => strtolower($e[1]) === 'jpeg' ? 'jpg' : strtolower($e[1])];
                    }
                }
            }
        }
        $zip->close();

        return $out;
    }

    private function resolve(string $base, string $target): string
    {
        $parts = [];
        foreach (explode('/', str_starts_with($target, '/') ? ltrim($target, '/') : $base.'/'.$target) as $p) {
            if ($p === '..') {
                array_pop($parts);
            } elseif ($p !== '.' && $p !== '') {
                $parts[] = $p;
            }
        }

        return implode('/', $parts);
    }

    private function value(mixed $v, string $coord, Worksheet $ws): mixed
    {
        if (is_numeric($v) && Date::isDateTime($ws->getCell($coord))) {
            return Date::excelToDateTimeObject((float) $v)->format('Y-m-d');
        }

        return is_string($v) ? preg_replace('/\s+/', ' ', $v) : $v;
    }

    // "YAMA Din170 MF (B)" / "... B.xlsx" -> B; kartu utuh -> null.
    private function partLabel(?string $name, ?string $file): ?string
    {
        foreach ([$name, $file ? pathinfo($file, PATHINFO_FILENAME) : null] as $s) {
            if ($s && preg_match('/(?:\(([AB])\)|\s([AB]))\s*$/', $s, $m)) {
                return $m[1] ?: $m[2];
            }
        }

        return null;
    }

    public function date(?string $v): ?string
    {
        if (! $v) {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) {
            return substr($v, 0, 10);
        }
        $t = strtotime(str_replace('/', '-', $v));

        return $t ? date('Y-m-d', $t) : null;
    }

    // "37,5 cm" / "25, 7 cm" / "375 mm" / "37.5" (cm bila tanpa satuan) -> mm.
    public function length(?string $v): ?float
    {
        if ($v === null || ! preg_match('/(\d+(?:\s*[.,]\s*\d+)?)/', $v, $m)) {
            return null;
        }
        $n = (float) str_replace([',', ' '], ['.', ''], $m[1]);

        return round(stripos($v, 'mm') !== false ? $n : $n * 10, 2);
    }

    // "83,4 x 70,1 cm" -> [834, 701].
    public function pair(?string $v): array
    {
        if ($v === null || ! preg_match('/(\d+(?:[.,]\d+)?)\s*[xX×*]\s*(\d+(?:[.,]\d+)?)/', str_replace(', ', ',', $v), $m)) {
            return [null, null];
        }
        $k = stripos($v, 'mm') !== false ? 1 : 10;

        return [round((float) str_replace(',', '.', $m[1]) * $k, 2), round((float) str_replace(',', '.', $m[2]) * $k, 2)];
    }
}
