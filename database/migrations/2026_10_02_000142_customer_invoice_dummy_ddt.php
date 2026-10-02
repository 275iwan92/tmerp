<?php

use App\Models\Acc\FinInvoice;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoDeliveryPod;
use App\Services\Acc\CustomerInvoiceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Contoh data Customer Invoice untuk UAT -- HANYA di database dummy ddt*; di tm2* migration ini tidak menulis apa pun.
// Surat Jalan customer Confirmed pertama yang belum punya Proof of Delivery dicatat diterima penuh, lalu dibuatkan satu
// Draft invoice lewat service yang sama dengan layar (harga dari CO, uang muka order itu diusulkan sebagai potongan).
// Draft sengaja tidak di-Confirm: nomor dan jurnalnya lahir saat user menekan Confirm di layar.
return new class extends Migration
{
    private const TAG = '[UAT customer invoice]';

    private function isDummy(): bool
    {
        return str_starts_with(DB::connection('tm2acc')->getDatabaseName(), 'ddt');
    }

    public function up(): void
    {
        if (! $this->isDummy() || ! Schema::connection('tm2acc')->hasTable('fin_invoice_line') || FinInvoice::withoutGlobalScopes()->where('notes', self::TAG)->exists()) {
            return;
        }
        $do = DoDelivery::query()->with('lines')->where('do_type', 'CUSTOMER')->whereIn('status', ['CONFIRMED', 'SHIPPED'])
            ->whereDoesntHave('lines', fn ($q) => $q->whereNotNull('qty_received'))->orderBy('id_delivery')->first();
        $coId = $do?->lines->first()?->co_order_id;
        $co = $coId ? CoOrder::query()->find($coId) : null;
        if (! $do || ! $co) {
            return;
        }
        $day = ($do->scheduled_ship_date ?? $do->do_date ?? now())->toDateString();
        DoDeliveryPod::create(['delivery_id' => $do->id_delivery, 'attempt_no' => (int) DoDeliveryPod::where('delivery_id', $do->id_delivery)->max('attempt_no') + 1,
            'promise_date' => $day, 'arrived_at' => $day.' 10:00:00', 'result' => 'RECEIVED', 'received_by' => 'UAT receiver', 'promise_kept' => true,
            'notes' => self::TAG, 'created_by' => 'uat', 'updated_by' => 'uat']);
        foreach ($do->lines as $line) {
            $line->update(['qty_received' => $line->qty_do, 'updated_by' => 'uat']);
        }
        $inv = app(CustomerInvoiceService::class)->createInvoice($co, [], 'uat');
        $inv->forceFill(['notes' => self::TAG, 'data_origin' => 'DUMMY'])->save();
    }

    public function down(): void
    {
        if (! $this->isDummy()) {
            return;
        }
        foreach (FinInvoice::withoutGlobalScopes()->where('notes', self::TAG)->where('doc_status', 'DRAFT')->get() as $inv) {
            $inv->delete();
        }
        foreach (DoDeliveryPod::query()->where('notes', self::TAG)->get() as $pod) {
            DB::connection('tm2sdi')->table('do_line')->where('delivery_id', $pod->delivery_id)->where('qty_invoiced', 0)->update(['qty_received' => null]);
            $pod->delete();
        }
    }
};
