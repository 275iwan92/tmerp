<?php

namespace Devlib\Components;

// Peta gudang (warehouse map): denah satu gedung per lantai -- garis luar gedung (kotak, huruf L, atau bentuk apa pun),
// area penyimpanan berwarna, dan bin di dalamnya. Klik bin = panel isi di kanan (kapasitas, QR, item, tombol aksi).
// Semua ukuran dalam METER; komponen hanya menggambar apa yang diberikan (letak & isi dihitung aplikasi pemakai).
//
// $outline = [[x, y], ...]            garis luar gedung (searah jarum jam, mulai kiri-atas). Kosong = kotak $width x $height.
// $floors  = [['no' => 1, 'label' => 'Floor 1', 'zones' => [[
//               'id', 'code', 'name', 'tone' => accent|violet|amber|lime|green|danger|pink|neutral, 'x', 'y', 'w', 'h', 'note',
//               'cells' => [['x', 'y', 'w', 'h', 'label', 'bins' => [id bin, ...]], ...]   // 1 sel = 1 posisi di denah; rak bertingkat = beberapa bin
//             ], ...]], ...]
// $bins    = [id => ['code', 'title' => teks kecil di bawah kode, 'status' => EMPTY|IN_USE|FULL|OVER|BLOCKED, 'dead' => bool,
//               'capacity' => '1,935 / 3,200 kg', 'pct' => 60.5|null, 'note' => teks (alasan blokir, dst), 'qr' => isi QR, 'level' => label tingkat,
//               'caps' => [['label' => 'Weight', 'value' => '1,935 / 3,200 kg', 'pct' => 60.5|null, 'note' => teks kecil], ...]
//                         beberapa baris kapasitas (berat, volume, luas tapak); bila diisi menggantikan 'capacity' + 'pct',
//               'expiry' => bool (ada isi yang kedaluwarsa / segera kedaluwarsa -> titik kuning + saringan legenda),
//               'stack' => [['id', 'no' => nomor tumpukan (1 = paling bawah), 'kind' => pallet|pack, 'name', 'sub' => teks kecil,
//                            'tone' => danger|null, 'up' => bool, 'down' => bool (boleh ditukar ke atas / bawah),
//                            'tags' => [['label', 'tone' => danger|warn|info|violet|orange|null], ...]], ...]
//                          satuan yang ditumpuk di bin itu, urut dari ATAS ke bawah -> digambar tampak samping di panel,
//               'count' => jumlah satuan ditumpuk (angka kecil di kotak bin), 'remnant' => bool (ada sisa -> titik ungu +
//                          saringan legenda), 'buried' => bool (yang harus dipakai dulu tertimbun -> titik oranye + saringan),
//               'items' => [['code', 'name', 'qty' => teks, 'idle' => teks, 'dead' => bool,
//                            'tags' => [['label' => teks, 'tone' => danger|warn|null], ...]], ...]], ...]
// $actions = [['label', 'icon', 'url' => '...{id}...', 'tone' => primary|secondary|danger, 'when' => stock|usable|blocked|null], ...]
//             tombol di panel bin; {id} / {code} diganti milik bin terpilih; 'post' => true = dikirim POST (dengan konfirmasi 'confirm').
// $layer = lapisan opsional di atas peta (mis. alokasi pick list); [] = peta tanpa lapisan, tampil persis seperti biasa:
//   ['marks' => [['bin' => id bin, 'text' => '1', 'tone' => pick|put], ...]  lingkaran bernomor di tengah sel bin (put = putus-putus),
//    'route' => [id bin, ...]  garis putus-putus melewati bin berurutan (bin di lantai lain dilewati),
//    'end' => id bin tujuan (mis. staging) bertanda panah, 'title' / 'rows' => [['no', 'bin', 'sub', 'qty'], ...] / 'notes' => [teks]  isi panel kanan sebelum ada bin dipilih].
// $restackUrl = alamat POST untuk menukar urutan tumpukan: '{id}' = id satuan, '{dir}' = up | down (null = tanpa tombol tukar).
// $saveUrl = alamat POST {zone, x, y, w, h} saat area digeser / diubah ukurannya di mode Edit Layout (null = peta hanya-baca).
// Warna: kosong = putih, terisi = biru, penuh = kuning, lebih dari kapasitas = oranye, diblokir = arsir, DEAD STOCK = merah
// (merah menang atas warna isi). Event: dl:whmap-select {id, code}.
class WhMap extends DevlibComponent
{
    protected const TEXT_PROPS = ['title', 'search', 'empty'];

    public const STATUS = ['EMPTY' => 'Empty', 'IN_USE' => 'In use', 'FULL' => 'Full', 'OVER' => 'Over capacity', 'BLOCKED' => 'Blocked'];

    // 1 meter = 10 satuan gambar (huruf dan garis tetap terbaca di skala apa pun).
    public const SCALE = 10;

    public function __construct(
        public string $id = 'whmap',
        public ?string $title = null,
        public float $width = 0,
        public float $height = 0,
        public array $outline = [],
        public array $floors = [],
        public array $bins = [],
        public array $actions = [],
        public ?int $floor = null,
        public int|string|null $selected = null,
        public string $search = 'Search bin, item, lot...',
        public string $empty = 'Click a bin on the map to see what is inside.',
        public ?string $saveUrl = null,
        public ?string $restackUrl = null,
        public ?string $source = null,
        public array $layer = [],
    ) {
        $this->floor ??= $this->floors[0]['no'] ?? 1;
        if (! $this->outline) {
            $this->outline = [[0, 0], [$this->width, 0], [$this->width, $this->height], [0, $this->height]];
        }
        $this->width = $this->width ?: (float) max(array_column($this->outline, 0) ?: [0]);
        $this->height = $this->height ?: (float) max(array_column($this->outline, 1) ?: [0]);
    }

    public function tag(): string
    {
        return 'x-dl::wh-map id='.$this->id;
    }

    public function source(): string
    {
        return $this->source ?? 'denah gudang + isi bin';
    }

    // Status satu sel = gabungan bin di dalamnya (rak bertingkat): yang paling perlu diperhatikan menang.
    public function cellStatus(array $cell): string
    {
        $all = array_map(fn ($id) => $this->bins[$id]['status'] ?? 'EMPTY', $cell['bins'] ?? []);
        foreach (['OVER', 'FULL', 'IN_USE'] as $s) {
            if (in_array($s, $all, true)) {
                return $s;
            }
        }

        return $all && count(array_unique($all)) === 1 && $all[0] === 'BLOCKED' ? 'BLOCKED' : 'EMPTY';
    }

    public function cellDead(array $cell): bool
    {
        foreach ($cell['bins'] ?? [] as $id) {
            if (! empty($this->bins[$id]['dead'])) {
                return true;
            }
        }

        return false;
    }

    public function cellExpiry(array $cell): bool
    {
        return $this->cellHas($cell, 'expiry');
    }

    public function cellHas(array $cell, string $flag): bool
    {
        foreach ($cell['bins'] ?? [] as $id) {
            if (! empty($this->bins[$id][$flag])) {
                return true;
            }
        }

        return false;
    }

    // Jumlah satuan yang ditumpuk di satu sel (semua bin di sel itu).
    public function cellCount(array $cell): int
    {
        return (int) array_sum(array_map(fn ($id) => (int) ($this->bins[$id]['count'] ?? 0), $cell['bins'] ?? []));
    }

    // Jumlah per status + dead stock + kedaluwarsa untuk baris ringkasan di atas peta.
    public function counts(): array
    {
        $c = array_fill_keys(array_keys(self::STATUS), 0) + ['DEAD' => 0, 'EXPIRY' => 0, 'REMNANT' => 0, 'BURIED' => 0];
        foreach ($this->bins as $b) {
            $c[$b['status'] ?? 'EMPTY']++;
            $c['DEAD'] += empty($b['dead']) ? 0 : 1;
            $c['EXPIRY'] += empty($b['expiry']) ? 0 : 1;
            $c['REMNANT'] += empty($b['remnant']) ? 0 : 1;
            $c['BURIED'] += empty($b['buried']) ? 0 : 1;
        }

        return $c;
    }

    // Titik tengah sel yang memuat bin $binId di lantai $floorNo (satuan gambar), atau null bila bin itu tidak digambar di lantai tersebut.
    /** @return array{0: float, 1: float}|null */
    public function layerPoint(int|string $binId, int $floorNo): ?array
    {
        foreach ($this->floors as $f) {
            if ((int) $f['no'] !== $floorNo) {
                continue;
            }
            foreach ($f['zones'] ?? [] as $z) {
                foreach ($z['cells'] ?? [] as $c) {
                    if (in_array((int) $binId, array_map('intval', $c['bins'] ?? []), true)) {
                        return [($c['x'] + $c['w'] / 2) * self::SCALE, ($c['y'] + $c['h'] / 2) * self::SCALE];
                    }
                }
            }
        }

        return null;
    }

    public function points(): string
    {
        return implode(' ', array_map(fn ($p) => round($p[0] * self::SCALE, 2).','.round($p[1] * self::SCALE, 2), $this->outline));
    }

    public function config(): array
    {
        return ['id' => $this->id, 'bins' => (object) $this->bins, 'actions' => $this->actions, 'selected' => $this->selected, 'floor' => $this->floor,
            'saveUrl' => $this->saveUrl, 'restackUrl' => $this->restackUrl, 'csrf' => $this->saveUrl || $this->actions || $this->restackUrl ? csrf_token() : null, 'scale' => self::SCALE, 'status' => self::STATUS, 'empty' => $this->empty];
    }

    public function render()
    {
        return view('devlib::components.wh-map');
    }
}
