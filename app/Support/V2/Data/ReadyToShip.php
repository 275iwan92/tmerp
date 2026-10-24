<?php

/*
 * REMARK
 * Tujuan       : Data layar Ready to Ship (antrean pilih CO yang bisa diproses menjadi Delivery Order): kesiapan per CO dan pembuatan satu DO Draft dari CO terpilih.
 * Sumber hulu  : v_co_delivery_outstanding (kuota sisa kirim), co_order_delivery.posted_at (Post to Production), co_order, cust_customer; pembuatan DO = DeliveryOrder::create + DeliveryOrder::pull (alur Pull Lines, aturan kuota tetap di DispatchService).
 * Input        : id CO terpilih (dari x-dl::pick-queue).
 * Output       : Baris untuk x-dl::pick-queue; satu DO Draft (do_delivery + do_line) atau pesan penolakan. Tidak menulis tabel lain.
 * Dipakai oleh : resources/views/v2/screens/ready-to-ship.blade.php, routes/v2_ready_to_ship.php (v2.do.from-queue).
 * Status       : Aktif (2026-10-24). Kesiapan produksi / QC sengaja belum diperiksa: "siap" = kuota kirim masih ada DAN sudah di-Post to Production.
 */

namespace App\Support\V2\Data;

use App\Models\Master\CustCustomer;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\DoDelivery;
use Devlib\Locale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Satu baris = satu CO CONFIRMED yang masih punya sisa kuota kirim. have = sisa kuota yang sudah Posted to Production, want = seluruh sisa
// kuota CO itu. state: ready (have = want), partial (0 < have < want), waiting (have = 0, belum Posted to Production).
final class ReadyToShip
{
    public static function rows(): array
    {
        $db = DB::connection('tm2sdi');
        $lines = $db->table('v_co_delivery_outstanding')->where('co_status', 'CONFIRMED')->where('qty_available', '>', 0)->get();
        if ($lines->isEmpty()) {
            return [];
        }

        $posted = $db->table('co_order_delivery')->whereIn('id_delivery', $lines->pluck('co_delivery_id'))->whereNotNull('posted_at')->pluck('id_delivery')->flip();
        $cos = CoOrder::whereIn('id_order', $lines->pluck('co_order_id')->unique())->get()->keyBy('id_order');
        $customers = CustCustomer::whereIn('id_customer', $lines->pluck('customer_id')->unique())->pluck('customer_name', 'id_customer');
        $today = Carbon::today();
        $rows = [];

        foreach ($lines->groupBy('co_order_id') as $coId => $set) {
            $co = $cos->get($coId);
            $ready = $set->filter(fn ($l) => $posted->has($l->co_delivery_id));
            // Satuan campur: hitung jumlah baris jadwal, bukan jumlah barang.
            $mixed = $set->pluck('qty_unit')->unique()->count() > 1;
            $have = $mixed ? $ready->count() : (float) $ready->sum('qty_available');
            $want = $mixed ? $set->count() : (float) $set->sum('qty_available');
            $due = Carbon::parse(($ready->isNotEmpty() ? $ready : $set)->min('ship_date'));

            $rows[] = [
                'id' => (int) $coId,
                'title' => $set->first()->co_no,
                'party' => $customers[$set->first()->customer_id] ?? '—',
                'place' => $co?->ship_address_label ?: '—',
                'group' => implode('|', [$set->first()->customer_id, $co?->ship_area_level.':'.$co?->ship_area_id, md5(trim($co?->ship_address_label.'|'.$co?->ship_address_detail)), (int) $co?->is_ppn, $co?->shipment_method]),
                'state' => $have <= 0 ? 'waiting' : ($have >= $want ? 'ready' : 'partial'),
                'have' => $have,
                'want' => $want,
                'unit' => $mixed ? t('schedule lines') : $set->first()->qty_unit,
                'due' => Locale::d($due),
                'dueIn' => (int) $today->diffInDays($due->startOfDay(), false),
            ];
        }

        usort($rows, fn ($a, $b) => [$a['dueIn'], $a['title']] <=> [$b['dueIn'], $b['title']]);

        return $rows;
    }

    // Satu DO Draft dari CO terpilih lewat alur Pull Lines (DeliveryOrder::pull -> DispatchService::pullLines): hanya jadwal yang sudah
    // Posted to Production dan masih punya sisa. Kembalian [DO atau null, pesan]; pesan boleh ada walau DO terbentuk (sebagian baris ditolak).
    public static function createDo(array $coIds): array
    {
        $coIds = array_values(array_unique(array_map('intval', $coIds)));
        $picked = collect(self::rows())->whereIn('id', $coIds)->values();

        if (! $coIds || $picked->count() !== count($coIds) || $picked->contains('state', 'waiting')) {
            return [null, t('Choose CO that are ready to ship.')];
        }
        if ($picked->pluck('group')->unique()->count() > 1) {
            return [null, t('Choose CO from the same customer, address and tax status.')];
        }

        $db = DB::connection('tm2sdi');
        $schedule = $db->table('v_co_delivery_outstanding')->whereIn('co_order_id', $coIds)->where('co_status', 'CONFIRMED')->where('qty_available', '>', 0)
            ->whereIn('co_delivery_id', fn ($q) => $q->from('co_order_delivery')->select('id_delivery')->whereNotNull('posted_at'))->orderBy('co_no')->orderBy('co_delivery_id')->get();
        if ($schedule->isEmpty()) {
            return [null, t('Choose CO that are ready to ship.')];
        }
        $do = null;
        $message = '';

        try {
            $db->transaction(function () use ($schedule, &$do, &$message) {
                $do = DeliveryOrder::create((int) $schedule->first()->customer_id);
                $message = DeliveryOrder::pull($do, $schedule->pluck('co_delivery_id')->all());
                if (! DoDelivery::find($do->id_delivery)->lines()->exists()) {
                    throw new \DomainException($message ?: t('No schedule line could be pulled.'));
                }
            });
        } catch (\DomainException $e) {
            return [null, $e->getMessage()];
        }

        return [$do->refresh(), $message];
    }
}
