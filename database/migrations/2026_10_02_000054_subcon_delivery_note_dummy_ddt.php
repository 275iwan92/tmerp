<?php

use App\Models\Sdi\CoOrderItem;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoLine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Contoh data Surat Jalan Subkon untuk UAT -- HANYA di database dummy ddt* (aturan B.12: layar baru wajib punya dummy).
// Di tm2* migration ini tidak menulis apa pun. Dua dokumen ke subkon dummy CENTRAL GRAFIK (supplier PO subkon PO2600008),
// memakai baris product Customer Order yang sudah Confirmed: satu Confirmed (siap cetak), satu Draft.
return new class extends Migration
{
    private const TAG = '[UAT subcon delivery note]';

    private function isDummy(): bool
    {
        return str_starts_with(DB::connection('tm2sdi')->getDatabaseName(), 'ddt');
    }

    public function up(): void
    {
        if (! $this->isDummy() || DoDelivery::query()->where('do_type', 'SUBCON')->where('notes', 'like', '%'.self::TAG.'%')->exists()) {
            return;
        }
        $supplier = DB::connection('tm2mst')->table('sup_supplier')->where('supplier_name', 'like', '%CENTRAL GRAFIK%')->first()
            ?? DB::connection('tm2mst')->table('sup_supplier')->where('activate', 1)->orderBy('id_supplier')->first();
        $items = CoOrderItem::query()->where('line_kind', 'PRODUCT')->whereHas('order', fn ($q) => $q->where('doc_status', 'CONFIRMED'))->orderBy('id_item')->limit(2)->get();
        if (! $supplier || $items->isEmpty()) {
            return;
        }
        // Nomor = urutan berikutnya (pola DispatchService::nextDoNo), supaya penomoran DO sesudahnya tidak melompat.
        $prefix = 'DO/'.now()->format('y').'/';
        $last = DoDelivery::query()->where('do_no', 'like', $prefix.'%')->orderByDesc('do_no')->value('do_no');
        $no = $prefix.str_pad((string) (($last ? (int) substr($last, -5) : 0) + 1), 5, '0', STR_PAD_LEFT);
        $docs = [
            ['CONFIRMED', $no, 'Lamination Doff', $items[0], 500, 'Printed body sheets'],
            ['DRAFT', null, 'UV Spot', $items[1] ?? $items[0], 300, 'Printed lid sheets'],
        ];
        DB::connection('tm2sdi')->transaction(function () use ($docs, $supplier) {
            foreach ($docs as [$status, $no, $process, $item, $qty, $what]) {
                $do = new DoDelivery(['do_no' => $no, 'do_type' => 'SUBCON', 'do_date' => now()->toDateString(), 'supplier_id' => $supplier->id_supplier,
                    'subcon_process' => $process, 'ship_address_label' => $supplier->supplier_name, 'ship_address_detail' => $supplier->default_address,
                    'ship_postal_code' => $supplier->postal_code ?? null, 'scheduled_ship_date' => now()->addDays(2)->toDateString(), 'status' => $status,
                    'notes' => 'Return within 3 working days. '.self::TAG, 'created_by' => 'uat', 'updated_by' => 'uat']);
                $do->forceFill(['data_origin' => 'DUMMY'])->save();
                $line = new DoLine(['delivery_id' => $do->id_delivery, 'line_no' => 1, 'co_order_id' => $item->order_id, 'co_order_item_id' => $item->id_item,
                    'item_description' => $what, 'qty_do' => $qty, 'qty_unit' => 'LBR', 'unit_price' => 0, 'subtotal' => 0,
                    'price_overridden' => 0, 'qty_invoiced' => 0, 'status' => 'PLANNED', 'created_by' => 'uat', 'updated_by' => 'uat']);
                $line->forceFill(['data_origin' => 'DUMMY'])->save();
            }
        });
    }

    public function down(): void
    {
        if (! $this->isDummy()) {
            return;
        }
        $ids = DoDelivery::query()->where('do_type', 'SUBCON')->where('notes', 'like', '%'.self::TAG.'%')->pluck('id_delivery');
        DoLine::query()->whereIn('delivery_id', $ids)->delete();
        DoDelivery::query()->whereKey($ids)->delete();
    }
};
