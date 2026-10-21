<?php

/*
 * REMARK
 * Tujuan       : Data layar CPDEL: baris pengiriman yang diposting ke produksi; post() membuat Work Order.
 * Sumber hulu  : co_order_delivery, co_order, WoGenerationService.
 * Input        : Id baris pengiriman.
 * Output       : WO (prd_wo + operasi + bahan) dan penanda posted_at; WO dibatalkan tidak menghalangi posting ulang.
 * Dipakai oleh : Layar CPDEL, PpicController::createWo, CpdelPromiseService (split / merge), StageBuilder.
 * Status       : Aktif di mockup (T10 tahap 7c, 2026-10-22: Split / Gabung memanggil CpdelEntryService, bukan komponen v1).
 */

namespace App\Support\V2\Data;

use App\Models\Master\CustCustomer;
use App\Models\Prd\PrdWo;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sys\SysConstant;
use App\Services\Sales\CpdelEntryService;
use App\Services\Sales\CpdelPromiseService as Promise;
use App\Services\Sales\DeliveryPromiseService;
use App\Services\Scheduling\Plan\PlanEvents;
use App\Services\Scheduling\WoGenerationService;
use App\Support\V2\Print\PrintDocs;
use App\Support\WorkCalendar;
use Carbon\CarbonImmutable;
use Devlib\Locale;
use Illuminate\Support\Facades\Auth;

// Customer Plan Delivery v2. Aturan Split/Pindah tanggal & Gabung = App\Services\Sales\CpdelEntryService (confirmSplit/confirmMerge).
// Hanya CO CONFIRMED; tidak menyentuh stok (itu Delivery Order).
// Dua janji (slot customer vs siap produksi), negosiasi & log = App\Services\Sales\CpdelPromiseService.
final class Cpdel
{
    public static function query()
    {
        return CoOrderDelivery::with(['order', 'item'])->whereHas('order', fn ($q) => $q->where('doc_status', 'CONFIRMED'));
    }

    // Satu baris grid = satu jadwal kirim dengan DUA janji (App\Services\Sales\CpdelPromiseService): slot customer vs siap produksi.
    public static function rows(): array
    {
        // Yang belum dikirim di atas, urut slot paling awal (lewat tanggal dulu); yang sudah dikirim di bawah.
        $rows = self::query()->orderByRaw("status = 'SHIPPED'")->orderBy('ship_date')->orderBy('ship_time')->limit(1000)->get();
        $customers = CustCustomer::whereIn('id_customer', $rows->pluck('order.customer_id')->unique())->pluck('customer_name', 'id_customer');
        $wo = PrdWo::whereIn('source_delivery_id', $rows->modelKeys())->get(['source_delivery_id', 'wo_status'])->groupBy('source_delivery_id');
        $cal = new WorkCalendar;
        $soon = (int) SysConstant::number('CPDEL_DUE_SOON_DAYS', 2);
        $today = CarbonImmutable::today();

        return $rows->map(fn (CoOrderDelivery $d) => Promise::due($d, $cal, $soon, $today) + [
            'id' => $d->id_delivery,
            'co' => $d->order?->co_no,
            'customer' => $customers[$d->order?->customer_id] ?? '—',
            'item' => $d->item?->description,
            // Baris anak grid (Expand): product baris kirim ini.
            'items' => [['product' => $d->item?->description, 'spec' => $d->item?->spec_note, 'order_qty' => (float) ($d->item?->qty ?? 0), 'unit' => $d->item?->qty_unit,
                'ship_qty' => (float) $d->ship_qty, 'due' => $d->item?->due_date?->toDateString()]],
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
        if (! PrdWo::where('source_delivery_id', $d->id_delivery)->where('wo_status', '!=', 'CANCELLED')->exists()) {
            try {
                app(WoGenerationService::class)->generateFromDelivery($d);
            } catch (\RuntimeException $e) {
                return $e->getMessage();
            }
        }
        $d->update(['posted_at' => now(), 'posted_by' => Auth::user()->login, 'updated_by' => Auth::user()->login]);
        // A new Work Order: the engines prepare a Draft plan (never the schedule in force).
        PlanEvents::replan('Work Order created from delivery line '.$d->id_delivery);

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

        return CpdelEntryService::confirmSplit($id, (string) $qty, $date) ?: null;
    }

    public static function merge(array $ids): ?string
    {
        if (CoOrderDelivery::whereIn('id_delivery', $ids)->whereNotNull('posted_at')->exists()) {
            return t('Lines Posted to Production cannot be joined.');
        }

        return CpdelEntryService::confirmMerge(array_map('intval', $ids)) ?: null;
    }

    public const STATE_LABELS = ['WAITING_PPIC' => 'Waiting for PPIC', 'NEED_NEGOTIATION' => 'Need negotiation', 'ESCALATED' => 'Escalated', 'ALIGNED' => 'Aligned',
        'LATE' => 'Late', 'SHIPPED' => 'Shipped'];

    private const NEXT = [
        'WAITING_PPIC' => 'Next: PPIC commits the time the goods will be ready.',
        'NEED_NEGOTIATION' => 'Next: Sales negotiates with the customer (WhatsApp / call), then changes the slot or splits the shipment. If the customer refuses, record it to escalate.',
        'ESCALATED' => 'Next: the Operational Manager decides (overtime, priority, subcontract, or a new offer).',
        'ALIGNED' => 'Both promises match. Keep the slot; Delivery records the arrival in Proof of Delivery.',
        'LATE' => 'The slot date has passed and the goods are not shipped: agree a new slot with the customer now.',
        'SHIPPED' => 'Shipped. The arrival and the customer receipt are recorded in Proof of Delivery.',
    ];

    // Isi objek form khusus x-mp::sales.promise-book (satu baris jadwal kirim): semua nilai, pilihan, hak dan alamat aksi
    // disiapkan di sini; objeknya hanya menampilkan. Bagian yang tidak berlaku (hak / status) = null, objek tidak menampilkannya.
    public static function promise(CoOrderDelivery $d, $user, string $home): array
    {
        $num = fn ($v) => Locale::number((float) $v, 0);
        $state = Promise::state($d);
        $planned = $d->status === 'PLANNED';
        $posted = (bool) $d->posted_at;
        $unit = $d->item?->qty_unit ?? '';
        $slotTime = $d->ship_time ? substr($d->ship_time, 0, 5) : null;
        $recv = $d->ship_date ? DeliveryPromiseService::receivingHours($d->order?->customer_id, $d->ship_date->toDateString()) : null;
        $woDue = PrdWo::where('source_delivery_id', $d->id_delivery)->min('due_date');
        $gap = Promise::gapDays($d);
        $contact = $d->order?->customer?->contact_name;
        $reasons = ['' => '—'] + Promise::reasons();
        $channels = array_map(fn ($l) => t($l), Promise::CHANNELS);
        $sell = $planned && Promise::canSell($user);
        $siblings = $sell && ! $posted ? self::siblings($d) : collect();

        return [
            'home' => $home, 'orderUrl' => route('v2.screen', 'customer-order').'?id='.$d->order_id, 'printUrl' => PrintDocs::url('cpdel', $d->order_id),
            'coNo' => (string) $d->order?->co_no, 'product' => (string) $d->item?->description,
            'state' => $state, 'stateLabel' => self::STATE_LABELS[$state], 'stateTone' => Promise::STATES[$state], 'posted' => $posted,
            'facts' => [
                ['Original promise', Locale::d($d->original_date ?? $d->ship_date) ?? '—', ['tm2sdi.co_order_delivery.original_date']],
                ['Customer slot', (Locale::d($d->ship_date) ?? '—').' '.($slotTime ?? t('time not set')), ['tm2sdi.co_order_delivery.ship_date', 'ship_time']],
                ['Production ready', Locale::dt($d->ready_at) ?? t('not committed'), ['tm2sdi.co_order_delivery.ready_at']],
                ['Gap', $gap === null ? '—' : ($gap > 0 ? '+'.$gap.' '.t('days late') : t('on time')), []],
                ['Qty', $num($d->ship_qty).' '.$unit.' / '.t('order').' '.$num($d->item?->qty).' '.$unit, ['tm2sdi.co_order_delivery.ship_qty']],
                ['Customer receives that day', $d->ship_date ? DeliveryPromiseService::receivingText($recv) : '—', ['tm2mst.cust_receiving_hour']],
                ['Due date on the order', Locale::d($d->item?->due_date) ?? '—', ['tm2sdi.co_order_item.due_date']],
                ['Production', $posted ? Locale::dt($d->posted_at).' · '.$d->posted_by : t('not posted'), ['tm2sdi.co_order_delivery.posted_at']],
            ],
            'next' => self::NEXT[$state],
            'problems' => $d->ship_date && $planned ? Promise::slotProblems($d->order?->customer_id, $d->ship_date->toDateString(), $slotTime) : [],
            'ppic' => $planned && Promise::canPlan($user) ? [
                'action' => route('v2.cpdel.ready', $d->id_delivery),
                'date' => old('ready_date', $d->ready_at?->toDateString() ?? ($woDue ? substr($woDue, 0, 10) : $d->ship_date?->toDateString())),
                'time' => old('ready_time', $d->ready_at?->format('H:i') ?? '07:30'),
                'woDue' => $woDue ? Locale::d($woDue) : null, 'reasons' => $reasons, 'reason' => old('reason_code'), 'note' => old('note'),
            ] : null,
            'slot' => $sell ? [
                'action' => route('v2.cpdel.reschedule', $d->id_delivery),
                'date' => old('ship_date', $d->ship_date?->toDateString()),
                'time' => old('ship_time', $slotTime ?? ($recv?->open_time ? substr($recv->open_time, 0, 5) : '08:00')),
                'maxQty' => $num($d->ship_qty), 'reasons' => $reasons, 'reason' => old('reason_code'), 'channels' => $channels,
                'channel' => old('channel', 'WHATSAPP'), 'contact' => old('customer_contact', $contact), 'note' => old('note'),
            ] : null,
            'refuse' => $sell && in_array($state, ['NEED_NEGOTIATION', 'LATE'], true)
                ? ['action' => route('v2.cpdel.reject', $d->id_delivery), 'channels' => $channels, 'contact' => $contact] : null,
            'decide' => $state === 'ESCALATED' && Promise::canDecide($user)
                ? ['action' => route('v2.cpdel.decide', $d->id_delivery), 'decisions' => array_map(fn ($l) => t($l), Promise::DECISIONS)] : null,
            'post' => $planned && ! $posted ? [
                'action' => route('v2.cpdel.post', $d->id_delivery),
                'description' => t('Creates the Work Order for this line.').' '.tf('After posting, the line can be pulled into a Delivery Order / Delivery Note right away (quick jobs of about :n minutes).', ['n' => Locale::number((float) SysConstant::number('QUICK_JOB_MINUTES', 30), 0)]).' '.t('Production output must still be entered afterwards: it is the KPI basis for all departments.'),
            ] : null,
            'postedInfo' => $posted ? [Locale::dt($d->posted_at).' · '.$d->posted_by, PrdWo::where('source_delivery_id', $d->id_delivery)->pluck('wo_no')->join(', ') ?: '—'] : null,
            'merge' => $siblings->isNotEmpty() ? [
                'action' => route('v2.cpdel.merge'), 'id' => $d->id_delivery,
                'options' => $siblings->mapWithKeys(fn ($s) => [$s->id_delivery => (Locale::d($s->ship_date) ?? '—').' · '.$num($s->ship_qty).' '.$unit])->all(),
            ] : null,
            'log' => $d->negotiations->map(fn ($n) => ['at' => Locale::dt($n->created_at), 'who' => $n->created_by.' ('.$n->actor_role.')', 'event' => t(ucfirst(strtolower(str_replace('_', ' ', $n->event)))),
                'change' => $n->event === 'READY_COMMITTED' ? t('ready').' '.Locale::dt($n->ready_at) : ($n->new_date ? (Locale::d($n->old_date) ?? '—').' '.substr((string) $n->old_time, 0, 5).' → '.Locale::d($n->new_date).' '.substr((string) $n->new_time, 0, 5).($n->new_qty !== null && (float) $n->new_qty !== (float) $n->old_qty ? ' · '.$num($n->new_qty).' '.$unit : '') : ($n->decision ? t(Promise::DECISIONS[$n->decision] ?? $n->decision) : '—')),
                'reason' => $n->reason_code ? (Promise::reasons()[$n->reason_code] ?? $n->reason_code) : '—',
                'via' => $n->channel ? t(Promise::CHANNELS[$n->channel] ?? $n->channel).($n->customer_contact ? ' · '.$n->customer_contact : '') : '—', 'note' => $n->note ?? '—'])->all(),
            'lines' => CoOrderDelivery::where('order_id', $d->order_id)->where('item_id', $d->item_id)->orderBy('ship_date')->get()->map(fn ($s) => ['date' => (Locale::d($s->ship_date) ?? '—').' '.($s->ship_time ? substr($s->ship_time, 0, 5) : ''),
                'qty' => $num($s->ship_qty), 'ready' => Locale::dt($s->ready_at) ?? '—', 'state' => t(self::STATE_LABELS[Promise::state($s)]).($s->id_delivery === $d->id_delivery ? ' · '.t('this line') : ''), 'from' => $s->split_from_id ? '#'.$s->split_from_id : '—'])->all(),
        ];
    }
}
