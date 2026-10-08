<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InvPallet;
use App\Models\Inventory\InvPalletLog;
use App\Models\Inventory\InvTransfer;
use App\Models\Inventory\InvTransferLine;
use App\Models\Inventory\RmStock;
use App\Models\Master\WhLoc;
use App\Models\Pur\PvReceipt;
use Devlib\Locale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Palet berlabel (user 2026-10-02): "wajib buat label setiap kedatangan dan setiap habis pemakaian dalam satu palet".
// Saldo stok tetap satu-satunya kebenaran jumlah (kartu stok, per item per bin). Palet = satuan fisik berlabel di dalam
// saldo itu, jadi selisih keduanya adalah PEKERJAAN LABEL:
//   saldo > jumlah qty palet  -> ada barang datang yang belum berlabel palet  (need = NEW)
//   saldo < jumlah qty palet  -> isinya sudah dipakai, label palet belum diganti (need = RELABEL)
// Engine menghitung selisihnya; petugas yang menentukan palet mana berisi berapa (engine suggests, officer confirms).
// Palet tidak pernah mengubah stok.
// Tumpukan (user 2026-10-03: "selain posisi X dan Y juga ada stack"): tiap satuan punya stack_no di bin-nya, 1 = paling
// bawah. Service ini yang menjaganya: satuan baru / yang datang lewat transfer = paling atas; satuan yang habis atau pindah
// keluar = yang di atasnya turun; petugas boleh menukar urutan (restack). Tanpa dokumen stok: bin dan jumlah tidak berubah.
// Sisa (unit_kind PACK): lembaran yang tinggal WH_REMNANT_MAX_SHEETS lembar atau kurang, atau yang dipilih petugas.
final class PalletService
{
    private const EPS = 0.00005;

    public const KINDS = ['PALLET' => 'Pallet', 'PACK' => 'Pack'];

    // Jenis satuan: pilihan petugas (PALLET / PACK), atau otomatis = PACK bila lembaran tinggal sebanyak batas sisa atau kurang.
    public static function kindFor(?string $asked, float $qty, ?string $uom): string
    {
        if (isset(self::KINDS[(string) $asked])) {
            return $asked;
        }
        $sheets = WarehouseStock::sheetCount($qty, (string) $uom);

        return $sheets !== null && $sheets <= WarehouseStock::remnantSheets() + self::EPS ? 'PACK' : 'PALLET';
    }

    // Nomor tumpukan tertinggi yang terpakai di satu bin (0 = bin tanpa satuan berlabel).
    private static function top(int $locationId): int
    {
        return (int) InvPallet::query()->where('status', 'ACTIVE')->where('location_id', $locationId)->max('stack_no');
    }

    // Rapikan nomor tumpukan satu bin menjadi 1..n tanpa lubang (urutan yang ada dipertahankan; tanpa nomor = paling atas).
    public static function compact(int $locationId): void
    {
        $units = InvPallet::query()->where('status', 'ACTIVE')->where('location_id', $locationId)->lockForUpdate()
            ->orderByRaw('stack_no IS NULL')->orderBy('stack_no')->orderBy('arrived_at')->orderBy('id_pallet')->get();
        foreach ($units as $i => $u) {
            if ((int) $u->stack_no !== $i + 1) {
                $u->forceFill(['stack_no' => $i + 1])->save();
            }
        }
    }

    // Tukar satuan dengan tetangganya di tumpukan: $dir = up (naik satu) | down (turun satu).
    public static function restack(InvPallet $pallet, string $dir, string $login): InvPallet
    {
        if (! $pallet->isActive()) {
            throw new RuntimeException(self::msg('Pallet :no is already closed.', ['no' => $pallet->pallet_no]));
        }

        return DB::connection('tm2inv')->transaction(function () use ($pallet, $dir, $login) {
            self::compact((int) $pallet->location_id);
            $pallet->refresh();
            $from = (int) $pallet->stack_no;
            $to = $dir === 'up' ? $from + 1 : $from - 1;
            $other = InvPallet::query()->where('status', 'ACTIVE')->where('location_id', $pallet->location_id)->where('stack_no', $to)->lockForUpdate()->first();
            if (! $other) {
                throw new RuntimeException(self::msg($dir === 'up' ? 'This unit is already at the top.' : 'This unit is already at the bottom.'));
            }
            $other->forceFill(['stack_no' => $from, 'updated_by' => $login])->save();
            $pallet->forceFill(['stack_no' => $to, 'updated_by' => $login])->save();
            self::log($pallet, 'RESTACK', (float) $pallet->qty, (float) $pallet->qty, (int) $pallet->location_id, (int) $pallet->location_id, null, $from.' > '.$to, $login);

            return $pallet;
        });
    }

    private static function msg(string $text, array $values = []): string
    {
        return strtr(t($text), collect($values)->mapWithKeys(fn ($v, $k) => [':'.$k => (string) $v])->all());
    }

    // Semua posisi stok + palet aktifnya. $locationIds null = semua bin.
    /** @return Collection<int, array<string, mixed>> posisi WarehouseStock + pallets, pallet_qty, unlabelled, excess, need */
    public static function worklist(?array $locationIds = null): Collection
    {
        $positions = WarehouseStock::positions($locationIds)->keyBy('key');
        $pallets = InvPallet::query()->where('status', 'ACTIVE')->when($locationIds !== null, fn ($q) => $q->whereIn('location_id', $locationIds ?: [0]))
            ->orderBy('arrived_at')->orderBy('id_pallet')->get()->groupBy(fn (InvPallet $p) => $p->positionKey());
        $rows = $positions->map(fn (array $p) => self::row($p, $pallets->get($p['key'], collect())));
        // Palet aktif yang stoknya sudah habis di bin itu: tetap muncul supaya labelnya ditutup.
        $bins = WhLoc::query()->whereKey($pallets->keys()->diff($positions->keys())->map(fn ($k) => WarehouseStock::parseKey($k)['location_id'] ?? 0)->all())->pluck('location_code', 'location_id');
        foreach ($pallets as $key => $group) {
            if ($positions->has($key)) {
                continue;
            }
            $first = $group->first();
            $rows->put($key, self::row(['key' => $key, 'class' => $first->stock_class, 'item_id' => (int) $first->item_id, 'part_id' => $first->part_id ? (int) $first->part_id : null,
                'location_id' => (int) $first->location_id, 'location_code' => $bins[$first->location_id] ?? '#'.$first->location_id, 'qty' => 0.0, 'uom' => (string) $first->uom_code,
                'code' => $first->item_code, 'name' => $first->item_name, 'dead' => false, 'arrived' => null, 'shelf_life_days' => null, 'weight_kg' => null, 'volume_m3' => null,
                'last_movement' => null, 'idle_days' => null, 'group' => null, 'value' => null, 'avg' => null], $group));
        }

        return $rows->values();
    }

    private static function row(array $p, Collection $pallets): array
    {
        $labelled = round((float) $pallets->sum('qty'), 4);
        $diff = round($p['qty'] - $labelled, 4);

        return $p + ['pallets' => $pallets->values(), 'pallet_qty' => $labelled, 'unlabelled' => max(0.0, $diff), 'excess' => max(0.0, -$diff),
            'need' => $diff > self::EPS ? 'NEW' : ($diff < -self::EPS ? 'RELABEL' : null)];
    }

    // Tanggal kedaluwarsa usulan: tanggal datang + umur simpan master (mat_raw.shelf_life_days); null = master belum diisi.
    public static function suggestedExpiry(array $position, ?string $arrived): ?string
    {
        return $arrived && ! empty($position['shelf_life_days']) ? Carbon::parse($arrived)->addDays((int) $position['shelf_life_days'])->toDateString() : null;
    }

    // Label kedatangan: palet baru untuk barang yang belum berlabel di satu posisi stok.
    // $rows = [['qty', 'supplier_pallet_code', 'lot_no', 'expiry_date'], ...]; $common = ['arrived_at', 'ref_doc_no', 'notes',
    // 'unit_kind' => PALLET | PACK | kosong = otomatis]. Satuan baru ditaruh di atas tumpukan bin itu, berurutan.
    /** @return Collection<int, InvPallet> */
    public static function create(string $positionKey, array $rows, array $common, string $login): Collection
    {
        $rows = array_values(array_filter($rows, fn ($r) => (float) ($r['qty'] ?? 0) > 0));
        if (! $rows) {
            throw new RuntimeException(self::msg('Fill in the quantity of at least one pallet.'));
        }
        $parsed = WarehouseStock::parseKey($positionKey);
        $p = $parsed ? self::worklist([$parsed['location_id']])->firstWhere('key', $positionKey) : null;
        if (! $p || $p['qty'] <= 0) {
            throw new RuntimeException(self::msg('This stock is no longer in that bin.'));
        }
        $total = round(array_sum(array_map(fn ($r) => (float) $r['qty'], $rows)), 4);
        if ($total > $p['unlabelled'] + self::EPS) {
            throw new RuntimeException(self::msg(':item: only :qty in this bin is not on a pallet label yet.', ['item' => $p['code'], 'qty' => Locale::decimal($p['unlabelled'], 4).' '.$p['uom']]));
        }
        $arrived = self::dateOrNull($common['arrived_at'] ?? null) ?? $p['arrived'] ?? now()->toDateString();

        return DB::connection('tm2inv')->transaction(function () use ($rows, $p, $common, $arrived, $login) {
            $out = collect();
            self::compact((int) $p['location_id']);
            $top = self::top((int) $p['location_id']);
            foreach ($rows as $r) {
                $pallet = InvPallet::create([
                    'unit_kind' => self::kindFor($common['unit_kind'] ?? null, (float) $r['qty'], $p['uom']), 'stack_no' => ++$top,
                    'pallet_no' => InvPallet::nextNo(), 'stock_class' => $p['class'], 'item_id' => $p['item_id'], 'part_id' => $p['part_id'],
                    'item_code' => mb_substr((string) $p['code'], 0, 60), 'item_name' => mb_substr((string) $p['name'], 0, 255),
                    'lot_no' => self::text($r['lot_no'] ?? null, 100), 'batch_id' => BatchService::findByLot($p['class'], (int) $p['item_id'], $r['lot_no'] ?? null)?->id_batch,
                    'supplier_pallet_code' => self::text($r['supplier_pallet_code'] ?? null, 60),
                    'ref_doc_no' => self::text($common['ref_doc_no'] ?? null, 50), 'uom_code' => $p['uom'] ?: null,
                    'qty_initial' => (float) $r['qty'], 'qty' => (float) $r['qty'], 'location_id' => $p['location_id'], 'arrived_at' => $arrived,
                    'expiry_date' => self::dateOrNull($r['expiry_date'] ?? null) ?? self::suggestedExpiry($p, $arrived),
                    'status' => 'ACTIVE', 'label_count' => 1, 'last_label_at' => now(), 'notes' => self::text($common['notes'] ?? null, 255),
                    'created_by' => $login, 'updated_by' => $login,
                ]);
                self::log($pallet, 'CREATED', null, (float) $r['qty'], null, $p['location_id'], $pallet->ref_doc_no, null, $login);
                $out->push($pallet);
            }

            return $out;
        });
    }

    // Label baru setelah isi palet dipakai: qty sisa menggantikan qty lama; sisa 0 = palet ditutup (yang di atasnya turun).
    // $kind = PALLET | PACK | null (otomatis: lembaran tinggal sebanyak batas sisa atau kurang menjadi PACK; PACK tetap PACK).
    public static function relabel(InvPallet $pallet, float $remaining, string $login, ?string $note = null, ?string $kind = null): InvPallet
    {
        if (! $pallet->isActive()) {
            throw new RuntimeException(self::msg('Pallet :no is already closed.', ['no' => $pallet->pallet_no]));
        }
        if ($remaining < 0 || $remaining > $pallet->qty_initial + self::EPS) {
            throw new RuntimeException(self::msg('Pallet :no: the remaining quantity must be between 0 and :max.', ['no' => $pallet->pallet_no, 'max' => Locale::decimal($pallet->qty_initial, 4)]));
        }

        return DB::connection('tm2inv')->transaction(function () use ($pallet, $remaining, $login, $note, $kind) {
            $before = (float) $pallet->qty;
            $closed = $remaining <= self::EPS;
            $newKind = $kind === null && $pallet->isPack() ? 'PACK' : self::kindFor($kind, $remaining, $pallet->uom_code);
            $pallet->forceFill(['qty' => $closed ? 0 : $remaining, 'status' => $closed ? 'CLOSED' : 'ACTIVE', 'updated_by' => $login]
                + ($closed ? ['stack_no' => null] : ['unit_kind' => $newKind, 'label_count' => $pallet->label_count + 1, 'last_label_at' => now()]))->save();
            self::log($pallet, $closed ? 'CLOSED' : 'RELABEL', $before, $closed ? 0 : $remaining, null, null, null, $note, $login);
            if ($closed) {
                self::compact((int) $pallet->location_id);
            }

            return $pallet;
        });
    }

    // Cetak ulang (label rusak / hilang): hanya menghitung cetakan.
    public static function printed(Collection $pallets, string $login): void
    {
        foreach ($pallets as $p) {
            $p->forceFill(['label_count' => $p->label_count + 1, 'last_label_at' => now(), 'updated_by' => $login])->save();
        }
    }

    // Stock Transfer Confirmed: palet ikut pindah. Baris yang menyebut palet -> palet itu. Baris tanpa palet: seluruh isi
    // posisi pindah -> semua paletnya ikut; sebagian -> palet tertua (FIFO) yang jumlahnya PERSIS sama dengan qty baris;
    // selain itu palet tetap di tempat dan selisihnya muncul di daftar pekerjaan label (petugas yang memutuskan).
    public static function followTransfer(InvTransfer $t, string $login): void
    {
        $stock = new StockTransferService;
        foreach ($t->lines as $l) {
            $here = InvPallet::query()->where('status', 'ACTIVE')->where('stock_class', $l->stock_class)->where('item_id', $l->item_id)
                ->where('location_id', $l->from_location_id)->when($l->stock_class === 'FG', fn ($q) => $l->part_id ? $q->where('part_id', $l->part_id) : $q->whereNull('part_id'))
                ->orderBy('arrived_at')->orderBy('id_pallet')->lockForUpdate()->get();
            // Urutan tumpukan asal dipertahankan: yang paling bawah pindah lebih dulu, lalu ditaruh di atas tumpukan bin tujuan.
            $moving = self::palletsToMove($l, $here, $stock)->sortBy(fn (InvPallet $p) => [$p->stack_no === null ? 1 : 0, (int) $p->stack_no, (int) $p->id_pallet]);
            foreach ($moving as $pallet) {
                self::move($pallet, (int) $l->from_location_id, (int) $l->to_location_id, $t->transfer_no, null, $login);
            }
            if ($moving->isNotEmpty()) {
                self::compact((int) $l->from_location_id);
            }
        }
    }

    // Satu satuan pindah bin: menjadi paling atas di bin tujuan.
    private static function move(InvPallet $pallet, int $from, int $to, ?string $ref, ?string $note, string $login): void
    {
        $pallet->forceFill(['location_id' => $to, 'stack_no' => self::top($to) + 1, 'updated_by' => $login])->save();
        self::log($pallet, 'MOVED', (float) $pallet->qty, (float) $pallet->qty, $from, $to, $ref, $note, $login);
    }

    /** @param  Collection<int, InvPallet>  $here */
    private static function palletsToMove(InvTransferLine $l, Collection $here, StockTransferService $stock): Collection
    {
        if ($l->pallet_id) {
            return $here->where('id_pallet', $l->pallet_id)->values();
        }
        // Dipanggil sesudah kartu stok ditulis: saldo asal 0 = seluruh isi posisi sudah pindah.
        if ($stock->balance($l->stock_class, (int) $l->item_id, $l->part_id, (int) $l->from_location_id) <= self::EPS) {
            return $here;
        }
        $take = collect();
        $sum = 0.0;
        foreach ($here as $p) {
            if ($sum + $p->qty <= $l->qty + self::EPS) {
                $take->push($p);
                $sum += $p->qty;
            }
        }

        return abs($sum - $l->qty) <= self::EPS ? $take : collect();
    }

    // Stock Transfer Cancelled: palet yang pindah karena transfer itu dan masih di bin tujuan kembali ke bin asal.
    public static function revertTransfer(InvTransfer $t, string $login): void
    {
        if (! $t->transfer_no) {
            return;
        }
        $logs = InvPalletLog::query()->where('action', 'MOVED')->where('ref_no', $t->transfer_no)->whereNull('note')->get();
        foreach ($logs as $log) {
            $pallet = InvPallet::query()->lockForUpdate()->find($log->pallet_id);
            if ($pallet && $pallet->isActive() && (int) $pallet->location_id === (int) $log->to_location_id) {
                self::move($pallet, (int) $log->to_location_id, (int) $log->from_location_id, $t->transfer_no, 'Transfer cancelled', $login);
                self::compact((int) $log->to_location_id);
            }
        }
    }

    // Baris penerimaan barang (SPB Posted) sebagai posisi stok: yang datang, bin-nya, lot, dan yang belum berlabel palet.
    // Sumber = dokumen stok masuk yang dibuat posting SPB (pv_receipt.rm_stock_doc_id -> rm_stock_d).
    /** @return list<array<string, mixed>> */
    public static function receiptLines(PvReceipt $pv): array
    {
        $doc = $pv->rm_stock_doc_id ? RmStock::with('lines')->find($pv->rm_stock_doc_id) : null;
        if (! $doc) {
            return [];
        }
        $work = self::worklist($doc->lines->pluck('location_id')->filter()->unique()->values()->all())->keyBy('key');
        $out = [];
        foreach ($doc->lines->groupBy(fn ($l) => WarehouseStock::key('RM', (int) $l->material_id, null, (int) $l->location_id)) as $key => $lines) {
            $p = $work->get($key);
            $out[] = ['key' => $key, 'received' => (float) $lines->sum('qty'), 'lot_no' => $lines->pluck('lot_no')->filter()->unique()->implode(', ') ?: null,
                'position' => $p, 'receipt_pallets' => $p ? $p['pallets']->where('ref_doc_no', $pv->doc_no)->values() : collect()];
        }

        return $out;
    }

    private static function log(InvPallet $p, string $action, ?float $before, ?float $after, ?int $from, ?int $to, ?string $ref, ?string $note, string $login): void
    {
        InvPalletLog::create(['pallet_id' => $p->id_pallet, 'action' => $action, 'qty_before' => $before, 'qty_after' => $after, 'from_location_id' => $from,
            'to_location_id' => $to, 'ref_no' => $ref, 'note' => self::text($note, 255), 'created_by' => $login]);
    }

    private static function text(mixed $v, int $max): ?string
    {
        return mb_substr(trim((string) $v), 0, $max) ?: null;
    }

    private static function dateOrNull(mixed $v): ?string
    {
        $v = trim((string) $v);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }
}
