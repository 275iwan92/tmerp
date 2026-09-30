<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\FinPaymentTerm;
use App\Models\Master\SupSupplier;
use App\Models\Pur\PoOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Master Supplier (v1: pages::master.supplier) — semua vendor, termasuk BANK/EKSPEDISI.
class SupplierGrid extends MasterDataGrid
{
    private array $orders = [];

    public function slug(): string
    {
        return 'md-supplier';
    }

    public function title(): string
    {
        return 'Master Supplier';
    }

    protected function v1Route(): ?string
    {
        return 'master.supplier';
    }

    protected function v1Component(): ?string
    {
        return 'pages::master.supplier';
    }

    protected function models(): array
    {
        return [SupSupplier::class, FinPaymentTerm::class, PoOrder::class];
    }

    public function search(): string
    {
        return 'Cari nama, kode, kategori, kontak...';
    }

    protected function query(): Builder
    {
        return SupSupplier::query()->with('paymentTerm')->orderBy('supplier_name');
    }

    protected function prefetch(Collection $items): void
    {
        $this->orders = PoOrder::query()->whereIn('i_supplier_id', $items->modelKeys())->groupBy('i_supplier_id')
            ->selectRaw('i_supplier_id, COUNT(*) n')->pluck('n', 'i_supplier_id')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => '', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Supplier', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'category', 'label' => 'Kategori'],
            ['key' => 'contact', 'label' => 'Kontak PIC'],
            ['key' => 'term', 'label' => 'Termin'],
            ['key' => 'subcon', 'label' => 'Subkon', 'type' => 'badge', 'tones' => ['Ya' => 'info']],
            ['key' => 'blacklist', 'label' => 'Blacklist', 'type' => 'badge', 'tones' => ['Ya' => 'danger']],
            ['key' => 'orders', 'label' => 'Purchase Orders', 'type' => 'link', 'format' => 'number'],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'photo' => null, 'name' => $m->supplier_name, 'code' => $m->supplier_code, 'category' => $m->category,
            'contact' => $m->contact_name, 'term' => $m->paymentTerm?->term_label, 'subcon' => $m->is_subcon ? 'Ya' : null,
            'blacklist' => $m->is_blacklisted ? 'Ya' : null, 'orders' => (int) ($this->orders[$m->getKey()] ?? 0),
        ];
    }

    public function filters(): array
    {
        return ['category' => 'Kategori', 'term' => 'Termin'];
    }
}
