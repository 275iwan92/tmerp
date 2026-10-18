<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\FinPaymentTerm;
use App\Models\Master\SupSupplier;
use App\Models\Pur\PoOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Master Supplier: semua vendor (termasuk BANK / EKSPEDISI) dengan kategori, kontak, termin bayar, subkon, blacklist dan jumlah Purchase Order; Open dan + New Record membuka form v2 md-supplier-form.
 * Sumber hulu  : tm2mst.sup_supplier + fin_payment_term; jumlah PO dari tm2pur.po_order (query terpisah).
 * Input        : Pencarian, statistik, filter Category / Payment Term.
 * Output       : Baris grid; tombol aktif / nonaktif membalik sup_supplier.activate.
 * Dipakai oleh : Menu MD_SUPP (v2:md-supplier), layar md-supplier-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::master.supplier sudah dipindah ke tmerp_unused (aturan D.25).
 */
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

    protected function canCreate(): bool
    {
        return true;
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'md-supplier-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'md-supplier-form').'?new=1';
    }

    protected function models(): array
    {
        return [SupSupplier::class, FinPaymentTerm::class, PoOrder::class];
    }

    public function search(): string
    {
        return 'Search name, code, category, contact...';
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
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'contact', 'label' => 'Contact Person'],
            ['key' => 'term', 'label' => 'Payment Term'],
            ['key' => 'subcon', 'label' => 'Subcontractor', 'type' => 'badge', 'tones' => [t('Yes') => 'info']],
            ['key' => 'blacklist', 'label' => 'Blacklist', 'type' => 'badge', 'tones' => [t('Yes') => 'danger']],
            ['key' => 'orders', 'label' => 'Purchase Orders', 'type' => 'link', 'format' => 'number'],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'photo' => null, 'name' => $m->supplier_name, 'code' => $m->supplier_code, 'category' => $m->category,
            'contact' => $m->contact_name, 'term' => $m->paymentTerm?->term_label, 'subcon' => $m->is_subcon ? t('Yes') : null,
            'blacklist' => $m->is_blacklisted ? t('Yes') : null, 'orders' => (int) ($this->orders[$m->getKey()] ?? 0),
        ];
    }

    public function filters(): array
    {
        return ['category' => 'Category', 'term' => 'Payment Term'];
    }
}
