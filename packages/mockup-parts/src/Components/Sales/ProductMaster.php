<?php

namespace MockupParts\Components\Sales;

use Devlib\Components\DevlibComponent;
use Devlib\Locale;
use MockupParts\Src;

// Form khusus Master Product (docs/flow_product_master.md, mockup disetujui user 2026-09-29): halaman SATU product.
//   1. identitas: nama (diubah Sales supaya cocok dengan PO customer), pemilik, kode + nomor desain, ukuran jadi, induk, order terakhir
//   2. pohon rakitan (objek x-mp::sales.product-tree) dengan panel per node: rakitan = jenis sambungan + BOP rakitan;
//      komponen = tab Dieline & Mockup / Material & BOM / BOP / Colour & Finishing
//   3. tab Customer ACC / Order & Selling Price History / Revision Branches
// Product lahir otomatis saat Quotation WIN, jadi tidak ada tombol New. Layar hanya menyerahkan $doc (ProductMaster::doc()),
// $orders, $types (jenis dieline), $accTypes dan alamatnya. Mengubah objek ini = mengubah halaman Master Product di semua situs.
class ProductMaster extends DevlibComponent
{
    public function __construct(
        public array $doc = [],
        public array $orders = [],
        public array $types = [],
        public array $accTypes = [],
        public string $home = '#',
        public string $nameAction = '#',
        public ?string $nameValue = null,
    ) {}

    public function tag(): string
    {
        return 'x-mp::sales.product-master';
    }

    public function rp(mixed $v): string
    {
        return 'IDR '.Locale::number((float) $v, 0);
    }

    public function num(mixed $v): string
    {
        return Locale::number((float) $v, 0);
    }

    // Semua node pohon rakitan dalam satu daftar rata (rakitan dulu, lalu isinya), untuk panel per node.
    public function flat(?array $nodes = null): array
    {
        $out = [];
        foreach ($nodes ?? ($this->doc['tree'] ?? []) as $n) {
            $out[] = $n;
            $out = array_merge($out, $this->flat($n['children']));
        }

        return $out;
    }

    // ACC terakhir per jenis (DIGITAL, PROOF, ...); null = belum pernah dicatat.
    public function lastAcc(string $type): ?array
    {
        return collect($this->doc['accs'] ?? [])->firstWhere('type', $type);
    }

    public function src(string ...$tables): ?string
    {
        return $tables ? Src::of(...$tables) : null;
    }

    public function render()
    {
        return view('mp::sales.product-master');
    }
}
