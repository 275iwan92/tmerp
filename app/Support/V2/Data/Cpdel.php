<?php

namespace App\Support\V2\Data;

use App\Models\Master\CustCustomer;
use App\Models\Prd\PrdWo;
use App\Models\Sdi\CoOrderDelivery;
use App\Services\Sales\CpdelPromiseService as Promise;
use App\Services\Scheduling\WoGenerationService;
use Devlib\Locale;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

// Customer Plan Delivery v2. Aturan Split/Pindah tanggal & Gabung = komponen v1 pages::co.delivery-plan yang SAMA
// (confirmSplit/confirmMerge). Hanya CO CONFIRMED; tidak menyentuh stok (itu Delivery Order).
// Dua janji (slot customer vs siap produksi), negosiasi & log = App\Services\Sales\CpdelPromiseService.
final class Cpdel
{
    public static function component(): Component
    {
        return app('livewire')->new('pages::co.delivery-plan');
    }

    public static function query()
    {
        return CoOrderDelivery::with(['order', 'item'])->whereHas('order', fn ($q) => $q->where('doc_status', 'CONFIRMED'));
    }

    // Satu baris grid = satu jadwal kirim dengan DUA janji (App\Services\Sales\CpdelPromiseService): slot customer vs siap produksi.
    public static function rows(): array
    {
        $rows = self::query()->orderBy('ship_date')->orderBy('ship_time')->limit(1000)->get();
        $customers = CustCustomer::whereIn('id_customer', $rows->pluck('order.customer_id')->unique())->pluck('customer_name', 'id_customer');
        $wo = PrdWo::whereIn('source_delivery_id', $rows->modelKeys())->get(['source_delivery_id', 'wo_status'])->groupBy('source_delivery_id');

        return $rows->map(fn (CoOrderDelivery $d) => [
            'id' => $d->id_delivery,
            'co' => $d->order?->co_no,
            'customer' => $customers[$d->order?->customer_id] ?? '—',
            'item' => $d->item?->description,
            'slot' => $d->ship_date?->toDateString(),
            'time' => $d->ship_time ? substr($d->ship_time, 0, 5) : t('not set'),
            'ready' => Locale::dt($d->ready_at) ?? '—',
            'gap' => Promise::gapDays($d),
            'qty' => (float) $d->ship_qty,
            'original' => ($d->original_date ?? $d->ship_date)?->toDateString(),
            'moved' => $d->original_date && $d->ship_date && ! $d->original_date->equalTo($d->ship_date),
            'production' => $d->posted_at ? t('Posted').' · '.($wo->get($d->id_delivery)?->pluck('wo_status')->join(', ') ?: 'WO') : '—',
            'state' => Promise::state($d),
        ])->all();
    }

    // Papan Operational Manager: hitungan per status + beban kirim 14 hari ke depan.
    public static function monitor(): array
    {
        $rows = self::query()->where('status', 'PLANNED')->get();
        $states = $rows->map(fn ($d) => Promise::state($d))->countBy()->all();
        $today = now()->startOfDay();

        return ['states' => $states, 'today' => $rows->filter(fn ($d) => $d->ship_date?->isSameDay($today))->count(),
            'week' => $rows->filter(fn ($d) => $d->ship_date && $d->ship_date->between($today, $today->copy()->addDays(6)))->count(),
            'no_time' => $rows->whereNull('ship_time')->count(),
            'moved' => $rows->filter(fn ($d) => $d->original_date && $d->ship_date && ! $d->original_date->equalTo($d->ship_date))->count()];
    }

    // Post to Production (aturan A.3): WO lahir dari baris ini, lalu baris boleh ditarik ke DO / Surat Jalan.
    // Kembalian: pesan error atau null.
    public static function post(int $id): ?string
    {
        $d = self::query()->find($id);
        if (! $d) {
            return t('Delivery line not found, or its Customer Order is not Confirmed.');
        }
        if ($d->posted_at) {
            return t('This line is already Posted to Production.');
        }
        if ($d->status !== 'PLANNED') {
            return t('Only lines not yet shipped can be Posted to Production.');
        }
        // WO yang sudah dibuat lewat layar Generate WO lama cukup ditandai Posted (tidak dibuat dobel).
        if (! PrdWo::where('source_delivery_id', $d->id_delivery)->exists()) {
            try {
                app(WoGenerationService::class)->generateFromDelivery($d);
            } catch (\RuntimeException $e) {
                return $e->getMessage();
            }
        }
        $d->update(['posted_at' => now(), 'posted_by' => Auth::user()->login, 'updated_by' => Auth::user()->login]);

        return null;
    }

    // Baris lain CO + product yang sama (kandidat Gabung).
    public static function siblings(CoOrderDelivery $d)
    {
        return CoOrderDelivery::where('order_id', $d->order_id)->where('item_id', $d->item_id)->where('status', 'PLANNED')->whereNull('posted_at')
            ->whereKeyNot($d->id_delivery)->orderBy('ship_date')->get();
    }

    // qty kosong = pindah tanggal saja; qty diisi = pecah jadi baris baru di tanggal itu. Kembalian: pesan error atau null.
    public static function split(int $id, ?string $qty, string $date): ?string
    {
        if (CoOrderDelivery::whereKey($id)->whereNotNull('posted_at')->exists()) {
            return t('Posted to Production (Work Order created): date and qty can no longer change here.');
        }
        $c = self::component();
        $c->splitDeliveryId = $id;
        $c->splitQty = (string) $qty;
        $c->splitDate = $date;
        $c->confirmSplit();

        return $c->splitError ?: null;
    }

    public static function merge(array $ids): ?string
    {
        if (CoOrderDelivery::whereIn('id_delivery', $ids)->whereNotNull('posted_at')->exists()) {
            return t('Lines Posted to Production cannot be joined.');
        }
        $c = self::component();
        $c->mergeSelectedIds = array_map('intval', $ids);
        $c->confirmMerge();

        return $c->mergeError ?: null;
    }
}
