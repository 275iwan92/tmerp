<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Inventory\StockOnhand;
use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Mnt\MntLog;
use App\Models\Mnt\MntOrder;
use App\Models\Mnt\MntOrderPart;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrRequisition;
use App\Models\Sys\SysConstant;
use App\Services\Inventory\StockDocumentService;
use App\Services\Inventory\StockPostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Spare part order Maintenance: stok tersedia (Inventory), keluarkan dari gudang (Stock Out biasa, kartu stok ikut), dan PR untuk yang kurang.
// Inventory tidak ditulis langsung: dokumen dibuat lewat StockDocumentService dan diposting StockPostingService seperti layar Stock Out.
final class PartSupplyService
{
    public function __construct(private ?string $login = null)
    {
        $this->login ??= auth()->user()?->login ?? 'system';
    }

    /** [material_id => qty tersedia] di lokasi yang boleh dipakai, semua gudang. */
    public static function onHand(iterable $materialIds): array
    {
        $ids = collect($materialIds)->filter()->unique()->values()->all();

        return $ids === [] ? [] : StockOnhand::of('RM')->whereIn('material_id', $ids)->where('is_usable', 1)->where('qty_onhand', '>', 0)
            ->selectRaw('material_id, SUM(qty_onhand) AS qty')->groupBy('material_id')->pluck('qty', 'material_id')->map(fn ($q) => (float) $q)->all();
    }

    /** Jumlah yang perlu dikeluarkan lagi untuk satu baris: terpakai (atau rencana) dikurangi yang sudah keluar. */
    public static function toIssue(MntOrderPart $p): float
    {
        return max(0.0, (float) ($p->qty_used ?? $p->qty_plan) - (float) $p->qty_issued);
    }

    /**
     * Keluarkan part yang stoknya cukup; yang kurang dilaporkan untuk PR. Satu Stock Out per gudang (Stock Out punya satu gudang).
     *
     * @return array{issued: int, docs: list<string>, short: list<string>}
     */
    public function issue(MntOrder $order): array
    {
        $this->guard($order->docStatus() === 'OPEN' && in_array($order->work_status, ['IN_PROGRESS', 'WAITING_PART'], true), t('Parts are issued while the order is In Progress or Waiting Part.'));
        $parts = $order->parts()->get()->filter(fn ($p) => self::toIssue($p) > 0);
        $this->guard($parts->isNotEmpty(), t('Nothing left to issue on this order.'));

        $locations = StockOnhand::of('RM')->whereIn('material_id', $parts->pluck('material_id'))->where('is_usable', 1)->where('qty_onhand', '>', 0)
            ->orderByDesc('qty_onhand')->get(['material_id', 'location_id', 'warehouse_id', 'qty_onhand'])->groupBy('material_id');
        $byWarehouse = [];
        $short = [];
        $issuedParts = [];
        foreach ($parts as $p) {
            $need = self::toIssue($p);
            $rows = $locations[$p->material_id] ?? collect();
            if ($rows->sum('qty_onhand') + 1e-9 < $need) {
                $short[] = (string) (MatRaw::whereKey($p->material_id)->value('material_name') ?? '#'.$p->material_id);

                continue;
            }
            foreach ($rows as $loc) {
                if ($need <= 0) {
                    break;
                }
                $take = min($need, (float) $loc->qty_onhand);
                $byWarehouse[$loc->warehouse_id][] = ['material_id' => $p->material_id, 'qty' => $take, 'uom_code' => $p->uom_code, 'location_id' => $loc->location_id, 'action_type' => 'ISSUE'];
                $need -= $take;
            }
            $issuedParts[$p->id_order_part] = self::toIssue($p);
        }

        $docs = [];
        if ($byWarehouse !== []) {
            $svc = new StockDocumentService;
            $dept = $this->department();
            foreach ($byWarehouse as $warehouse => $lines) {
                $docNo = $svc->nextDocNo('RM', 'OUT');
                $id = $svc->saveDraft('RM', null, ['doc_no' => $docNo, 'doc_date' => now()->toDateString(), 'doc_type' => -1, 'doc_cat' => 'STOCK', 'ref_doc_type' => 'MANUAL',
                    'ref_doc_no' => $order->doc_no, 'warehouse_id' => $warehouse, 'issued_to_dept' => $dept, 'notes' => 'Maintenance Order '.$order->doc_no], $lines, $this->login);
                $result = (new StockPostingService)->postDocument('RM', $id, $this->login);
                $this->guard((bool) $result['posted'], (string) ($result['error'] ?? t('Stock Out could not be posted.')));
                $docs[] = $docNo;
            }
            foreach ($issuedParts as $partId => $qty) {
                $part = MntOrderPart::find($partId);
                $total = (float) $part->qty_issued + $qty;
                $part->update(['qty_issued' => $total, 'qty_used' => $total, 'stock_doc_no' => $docs[0], 'updated_by' => $this->login]);
            }
            (new OrderService($this->login))->recost($order);
            MntLog::add('ORDER', $order->id_order, 'Parts issued', implode(', ', $docs));
        }

        return ['issued' => count($issuedParts), 'docs' => $docs, 'short' => $short];
    }

    /** PR Draft untuk part yang stoknya kurang dari kebutuhan (kebutuhan dikurangi stok tersedia); sekali per baris. @return int|null ID PR atau null bila tidak ada yang kurang */
    public function createPr(MntOrder $order): ?int
    {
        $parts = $order->parts()->whereNull('pr_id')->get();
        $stock = self::onHand($parts->pluck('material_id'));
        $lines = [];
        foreach ($parts as $p) {
            $short = self::toIssue($p) - ($stock[$p->material_id] ?? 0.0);
            if ($short > 0.0005) {
                $lines[] = [$p, $short];
            }
        }
        if ($lines === []) {
            return null;
        }

        // Akun biaya wajib ada (kategori material, atau akun HPP item) -- sama dengan PR dari ProDev. Semua baris dicek dulu supaya tidak ada PR setengah jadi.
        $coa = [];
        $missing = [];
        foreach ($lines as [$p]) {
            $mat = MatRaw::find($p->material_id);
            $account = MatCategory::level(1)->where('category_name', $mat?->category1)->first()?->default_coa_account_id ?: $mat?->cogs_account_id;
            $account ? $coa[$p->id_order_part] = (int) $account : $missing[] = (string) ($mat?->material_name ?? '#'.$p->material_id);
        }
        $this->guard($missing === [], tf('There is no COA account for :name yet (material category / COGS account of the item). Complete it in Material Master first.', ['name' => implode(', ', $missing)]));

        return DB::connection('tm2pur')->transaction(function () use ($order, $lines, $coa) {
            $dept = $this->department();
            $pr = PrRequisition::create(['i_doc_date' => now()->toDateString(), 'i_source' => 'MANUAL', 'i_department_id' => $dept, 'i_need_date' => now()->addDays(7)->toDateString(),
                'i_notes' => 'Maintenance Order '.$order->doc_no.': spare parts short in stock', 'status' => 'DRAFT', 'created_by' => $this->login, 'updated_by' => $this->login]);
            foreach ($lines as $i => [$p, $qty]) {
                $mat = MatRaw::find($p->material_id);
                $line = new PrLine(['pr_id' => $pr->pr_id, 'line_no' => $i + 1, 'i_item_source' => 'MASTER_ITEM', 'item_id' => $p->material_id, 'c_item_code' => $mat?->material_code,
                    'i_description' => mb_substr((string) ($mat?->material_name ?? 'Spare part'), 0, 255), 'i_qty' => round($qty, 3), 'i_uom' => $p->uom_code, 'i_est_price' => (float) $p->unit_cost,
                    'i_department_id' => $dept, 'i_coa_account_id' => $coa[$p->id_order_part], 'is_stock' => true, 'i_need_date' => now()->addDays(7)->toDateString(), 'created_by' => $this->login, 'updated_by' => $this->login]);
                $line->recalcSubtotal();
                $line->save();
                $p->update(['pr_id' => $pr->pr_id, 'updated_by' => $this->login]);
            }
            $pr->recalcEstTotal();
            $pr->save();
            MntLog::add('ORDER', $order->id_order, 'PR created', 'PR #'.$pr->pr_id);

            return (int) $pr->pr_id;
        });
    }

    // Departemen peminta: kode di konstanta MNT_DEPARTMENT_CODE (Settings > Constants); bila tidak ada, PPIC, lalu departemen aktif pertama.
    private function department(): int
    {
        $db = DB::connection('tm2mst')->table('org_department');
        $code = SysConstant::text('MNT_DEPARTMENT_CODE', 'MAINT');

        return (int) ((clone $db)->where('department_code', $code)->value('department_id') ?: (clone $db)->where('department_code', 'PPIC')->value('department_id')
            ?: (clone $db)->where('activate', 1)->orderBy('department_id')->value('department_id'));
    }

    private function guard(bool $ok, string $msg): void
    {
        if (! $ok) {
            throw ValidationException::withMessages(['maintenance' => $msg]);
        }
    }
}
