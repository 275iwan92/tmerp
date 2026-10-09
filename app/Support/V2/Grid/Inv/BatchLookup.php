<?php

declare(strict_types=1);

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvPallet;
use App\Models\Master\SupSupplier;
use App\Models\Sys\SysConstant;
use App\Services\Inventory\BatchService;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

// Inventory > Batch & Quality > Batch (tm2inv.inv_batch), Inventory & Warehouse v2.01: satu batch = sekelompok unit dengan asal
// dan mutu yang sama untuk satu item. Nomor batch otomatis dan Status hanya dibaca: status berubah lewat hasil sampling /
// keputusan QC (BatchService::setStatus), bukan diketik di sini. Batch baru dibuat lewat BatchService::create. Kolom On Hand
// dan Pallets dihitung dari kartu stok dan palet batch itu. Kotak statistik di atas = saringan.
// Sejak v2.01 (gambar inv_v201_batch_grid / batch_form) layar Batch = BatchListGrid (daftar) + form di resources/views/v2/screens/inv-batch.blade.php;
// kelas ini tidak lagi menjadi layar (tidak terdaftar di Grids) tetapi tetap satu-satunya jalur simpan dan sumber baris / kotak statistik daftar.
class BatchLookup extends DbLookup
{
    /** @var array<int, string> id supplier => nama (untuk kolom daftar) */
    private array $suppliers = [];

    private ?int $lastSavedId = null;

    private const CLASSES = ['RM' => 'Raw Material', 'SFG' => 'Semi Finished Goods', 'FG' => 'Finished Goods'];

    // kelas => [tabel kartu stok]
    private const CARDS = ['RM' => 'rm_stockcard', 'SFG' => 'sfg_stockcard', 'FG' => 'fg_stockcard'];

    /** @var array<int, float> id batch => jumlah di tangan */
    private array $onHand = [];

    /** @var array<int, int> id batch => jumlah palet aktif */
    private array $pallets = [];

    public function slug(): string
    {
        return 'inv-batch-edit';
    }

    public function title(): string
    {
        return 'Batch';
    }

    public function module(): string
    {
        return 'INV';
    }

    public function moduleLabel(): string
    {
        return 'Inventory';
    }

    public function group(): string
    {
        return 'Batch & Quality';
    }

    public function icon(): string
    {
        return 'fa-solid fa-tags';
    }

    public function guide(): ?string
    {
        return 'A batch is one lot of an item with the same origin and quality. The batch number is made by the system and Status is set by quality control, not typed here. '
            .'Type the item code and the class for a new batch; saved batches keep their item. On Hand and Pallets come from the stock card and pallet labels of the batch.';
    }

    protected function query(): Builder
    {
        return InvBatch::query()->orderByDesc('batch_no');
    }

    protected function models(): array
    {
        return [InvBatch::class, InvPallet::class];
    }

    public function columns(): array
    {
        return [
            ['key' => 'batch_no', 'label' => 'Batch', 'readonly' => true, 'width' => '110px'],
            ['key' => 'stock_class', 'label' => 'Class', 'type' => 'select', 'options' => self::CLASSES, 'required' => true, 'lockSaved' => true, 'width' => '150px'],
            ['key' => 'item_code', 'label' => 'Item Code', 'required' => true, 'lockSaved' => true, 'width' => '130px'],
            ['key' => 'item_name', 'label' => 'Item', 'readonly' => true, 'width' => '240px'],
            ['key' => 'supplier_id', 'label' => 'Supplier', 'type' => 'select', 'options' => SupSupplier::query()->where('activate', true)->orderBy('supplier_name')->pluck('supplier_name', 'id_supplier')
                ->map(fn ($n) => trim((string) $n))->all(), 'width' => '200px'],
            ['key' => 'supplier_lot', 'label' => 'Supplier Lot', 'width' => '130px'],
            ['key' => 'production_date', 'label' => 'Production', 'type' => 'date', 'width' => '110px'],
            ['key' => 'expiry_date', 'label' => 'Expiry', 'type' => 'date', 'width' => '110px'],
            ['key' => 'retest_date', 'label' => 'Retest', 'type' => 'date', 'width' => '110px'],
            ['key' => 'qc_status', 'label' => 'Status', 'readonly' => true, 'width' => '110px'],
            ['key' => 'certification', 'label' => 'Certification', 'type' => 'select', 'options' => InvBatch::CERTIFICATIONS, 'width' => '120px'],
            ['key' => 'food_grade', 'label' => 'Food Grade', 'type' => 'check', 'width' => '100px'],
            ['key' => 'grain_direction', 'label' => 'Grain Direction', 'type' => 'select', 'options' => InvBatch::GRAINS, 'width' => '130px'],
            ['key' => 'notes', 'label' => 'Notes', 'width' => '220px'],
            ['key' => 'on_hand', 'label' => 'On Hand (kg / unit)', 'type' => 'number', 'decimals' => 2, 'readonly' => true, 'width' => '140px'],
            ['key' => 'pallet_count', 'label' => 'Pallets', 'type' => 'number', 'decimals' => 0, 'readonly' => true, 'width' => '80px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function prefetch(Collection $items): void
    {
        $ids = $items->pluck('id_batch')->all();
        $this->onHand = $this->pallets = [];
        $this->suppliers = SupSupplier::query()->whereKey($items->pluck('supplier_id')->filter()->unique()->all())->pluck('supplier_name', 'id_supplier')->map(fn ($n) => trim((string) $n))->all();
        if (! $ids) {
            return;
        }
        $inv = DB::connection('tm2inv');
        foreach (self::CARDS as $table) {
            foreach ($inv->table($table)->whereIn('batch_id', $ids)->groupBy('batch_id')->selectRaw('batch_id, SUM(IFNULL(qty_in, 0) - IFNULL(qty_out, 0) + IFNULL(qty_adj, 0)) AS net')->get() as $r) {
                $this->onHand[(int) $r->batch_id] = ($this->onHand[(int) $r->batch_id] ?? 0.0) + (float) $r->net;
            }
        }
        foreach (InvPallet::query()->whereIn('batch_id', $ids)->where('status', 'ACTIVE')->where('unit_kind', 'PALLET')->groupBy('batch_id')->selectRaw('batch_id, COUNT(*) AS n')->get() as $r) {
            $this->pallets[(int) $r->batch_id] = (int) $r->n;
        }
    }

    protected function row(Model $m): array
    {
        /** @var InvBatch $m */
        $onHand = round($this->onHand[$m->id_batch] ?? 0.0, 4);
        $row = parent::row($m);
        // 'qc' dan 'expiring' = kunci saringan kotak statistik (bukan kolom); 'Expires soon' hanya untuk batch yang masih ada stoknya.
        $expiring = $onHand > 0 && $m->expiryState((int) SysConstant::number('WH_EXPIRY_SOON_DAYS', 30)) !== null;

        return ['qc_status' => InvBatch::STATUSES[$m->qc_status]['label'] ?? $m->qc_status, 'on_hand' => $onHand, 'pallet_count' => $this->pallets[$m->id_batch] ?? 0,
            'qc' => $m->qc_status, 'expiring' => $expiring, 'supplier_name' => $this->suppliers[$m->supplier_id] ?? '—', 'food_label' => $m->food_grade ? 'Yes' : 'No',
            'cert_label' => $m->certification && $m->certification !== 'NONE' ? $m->certification : '—', 'production_date' => $m->production_date?->toDateString(), 'expiry_date' => $m->expiry_date?->toDateString(),
            'retest_date' => $m->retest_date?->toDateString()] + $row;
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Quarantine', 'where' => ['qc' => 'QUARANTINE'], 'tone' => 'accent'],
            ['label' => 'Conditional', 'where' => ['qc' => 'CONDITIONAL'], 'tone' => 'warn'],
            ['label' => 'Released', 'where' => ['qc' => 'RELEASED'], 'tone' => 'success'],
            ['label' => 'Rejected', 'where' => ['qc' => 'REJECTED'], 'tone' => 'danger'],
            ['label' => 'Expires soon', 'where' => ['expiring' => true], 'tone' => 'warn'],
        ];
    }

    protected function saved(Model $row): void
    {
        $this->lastSavedId = (int) $row->getKey();
    }

    // id batch yang disimpan terakhir oleh save() (batch baru belum punya id di sisi layar).
    public function lastSavedId(): ?int
    {
        return $this->lastSavedId;
    }

    protected function prepare(array $in): array
    {
        $in['stock_class'] = strtoupper(trim((string) ($in['stock_class'] ?? '')));
        $in['item_code'] = trim((string) ($in['item_code'] ?? ''));
        $in['supplier_lot'] = trim((string) ($in['supplier_lot'] ?? ''));
        // Pilihan kosong (kunci '') = tanpa supplier / arah serat.
        foreach (['supplier_id', 'grain_direction'] as $k) {
            if (($in[$k] ?? '') === '') {
                $in[$k] = null;
            }
        }

        return $in;
    }

    protected function rules(): array
    {
        return ['stock_class' => [Rule::in(array_keys(self::CLASSES))], 'item_code' => ['string', 'max:60'], 'supplier_id' => ['nullable', 'integer', Rule::exists(SupSupplier::class, 'id_supplier')],
            'supplier_lot' => ['nullable', 'string', 'max:100'], 'production_date' => ['nullable', 'date'], 'expiry_date' => ['nullable', 'date'], 'retest_date' => ['nullable', 'date'],
            'certification' => ['nullable', Rule::in(array_keys(InvBatch::CERTIFICATIONS))], 'grain_direction' => ['nullable', Rule::in(array_keys(InvBatch::GRAINS))],
            'notes' => ['nullable', 'string', 'max:255']];
    }

    protected function check(array $in, ?int $id): ?string
    {
        $batch = $id ? InvBatch::query()->find($id) : null;
        $class = $batch?->stock_class ?? $in['stock_class'];
        $itemId = $batch?->item_id;
        if (! $batch) {
            $item = BatchService::itemByCode($class, $in['item_code']);
            if (! $item) {
                return tf('Item code :code was not found in :class.', ['code' => $in['item_code'], 'class' => t(self::CLASSES[$class] ?? $class)]);
            }
            $itemId = $item['id'];
            $same = BatchService::findByLot($class, $itemId, $in['supplier_lot']);
            if ($same) {
                return tf('This item already has batch :no for that supplier lot.', ['no' => $same->batch_no]);
            }
        }
        if (! empty($in['production_date']) && ! empty($in['expiry_date']) && $in['expiry_date'] < $in['production_date']) {
            return t('The expiry date is before the production date.');
        }
        if (empty($in['expiry_date']) && BatchService::needsExpiry($class, (int) $itemId)) {
            return t('This item has a shelf life: fill in the expiry date.');
        }

        return null;
    }

    // Batch baru lewat BatchService::create (nomor otomatis, status awal Released bila belum diputuskan QC).
    protected function create(Model $model, array $data, ?string $login): Model
    {
        $item = BatchService::itemByCode($data['stock_class'], $data['item_code']);
        $batch = BatchService::create($data['stock_class'], $item['id'], $data['supplier_lot'] ?? null, [
            'item_code' => $item['code'], 'item_name' => $item['name'], 'supplier_id' => $data['supplier_id'] ?? null, 'production_date' => $data['production_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null, 'retest_date' => $data['retest_date'] ?? null, 'certification' => $data['certification'] ?: 'NONE',
            'food_grade' => ! empty($data['food_grade']), 'grain_direction' => $data['grain_direction'] ?? null, 'notes' => $data['notes'] ?? null,
        ], (string) $login);
        if (array_key_exists('activate', $data) && ! $data['activate']) {
            $batch->forceFill(['activate' => false])->save();
        }

        return $batch;
    }
}
