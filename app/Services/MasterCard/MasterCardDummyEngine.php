<?php

/*
 * REMARK
 * Tujuan       : Mengubah PDF Master Card baru di docs/attachments menjadi data dummy BERLEVEL (Inquiry, ProDev, ProCost, CO), selalu lengkap
 *                dengan dieline, artwork, ukuran produk (P x L x T) dan ukuran datar (mm). Set di level lebih maju membawa semua level di belakangnya.
 * Sumber hulu  : MasterCardPdfReader (isi kartu), MasterCardService (kartu Collection, tipe dieline, Inquiry), Prodev (layout), CostingEngine, Quotation, CustomerOrder.
 * Input        : Folder PDF, kuota set per level (inquiry:2,prodev:2,procost:2,co:2), qty order contoh, hari due date.
 * Output       : Kartu Collection + Inquiry (+ layout ProDev, costing published, Quotation WIN, CO Confirmed) per produk baru; laporan per produk.
 * Dipakai oleh : Command dummy:mastercard. Patokan: docs/PATOKAN-DATA-DUMMY.md.
 * Status       : Aktif, hanya ddt*. Idempotent: produk dengan no. desain / kode item yang sudah ada dilewati, bukan ditimpa.
 */

namespace App\Services\MasterCard;

use App\Models\Master\CustCustomer;
use App\Models\Master\FlowLog;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMasterCard;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use App\Models\Sys\SecUser;
use App\Services\Sales\InquiryStage;
use App\Services\Sales\InquiryWriter;
use App\Support\V2\Data\CustomerOrder;
use App\Support\V2\Data\Prodev;
use Database\Seeders\Concerns\PricedDummyChain;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class MasterCardDummyEngine
{
    use PricedDummyChain;

    public const LEVELS = ['inquiry', 'prodev', 'procost', 'co'];

    // Teks kartu -> machine.legacy_machine_names (mesin cetak). Tanpa kecocokan = Komori GL440 ("Lithrone G40" / "GL440").
    private const PRESS = ['/VERSAFIRE/i' => 'VERSAFIRE', '/HANWAY/i' => 'HANWAY', '/SUNTHINK/i' => 'DIGITAL SUNTHINKS', '/SAKURA/i' => 'SAKURA'];

    private const KOMORI = 'KOMORI_GL440*';

    private const BEDS = [1030, 720];

    private int $dueDays = 21;

    public function __construct(
        private readonly MasterCardPdfReader $reader = new MasterCardPdfReader,
        private readonly MasterCardService $cards = new MasterCardService('dummy'),
    ) {}

    /**
     * Status of every product found in the PDF folder, without writing anything.
     *
     * @return list<array{design: ?string, name: string, cards: int, status: string}>
     */
    public function plan(string $dir): array
    {
        return array_map(fn ($parts) => ['design' => $parts[0][0]['design_no'], 'name' => $parts[0][0]['product_name'], 'cards' => count($parts),
            'status' => $this->skipReason($parts) ?? 'ready'], array_values($this->groups($dir)));
    }

    /**
     * Dummy sets now in the database, by the highest level each one reached (Inquiry confirmed by Sales, ProDev, ProCost, CO).
     *
     * @return array<string,int>
     */
    public function counts(): array
    {
        $rows = DB::connection('tm2sdi')->table('iqp_inquiry')->where('data_origin', 'DUMMY')->where('stat_cancel', 0)
            ->get(['stat_confirm_sales', 'stat_confirm_prodev', 'stat_confirm_procost', 'co_id']);

        return [
            'inquiry' => $rows->where('stat_confirm_sales', 1)->where('stat_confirm_prodev', 0)->where('stat_confirm_procost', 0)->whereNull('co_id')->count(),
            'prodev' => $rows->where('stat_confirm_prodev', 1)->where('stat_confirm_procost', 0)->whereNull('co_id')->count(),
            'procost' => $rows->where('stat_confirm_procost', 1)->whereNull('co_id')->count(),
            'co' => $rows->whereNotNull('co_id')->count(),
        ];
    }

    /**
     * @param  array<string,int>  $quota  level => number of sets; new products beyond the quota are noted, not built.
     * @return list<array{design: ?string, name: string, level: ?string, note: string}>
     */
    public function run(string $dir, array $quota, int $qty, int $dueDays): array
    {
        $this->dueDays = $dueDays;
        $actor = Auth::user();
        $report = [];
        try {
            foreach ($this->groups($dir) as $parts) {
                $row = ['design' => $parts[0][0]['design_no'], 'name' => $parts[0][0]['product_name'], 'level' => null, 'note' => ''];
                try {
                    $row = [...$row, ...$this->one($parts, $quota, $qty)];
                } catch (ValidationException $e) {
                    $row['note'] = 'incomplete: '.implode(' ', array_merge(...array_values($e->errors())));
                }
                $report[] = $row;
            }
        } finally {
            $actor ? Auth::setUser($actor) : Auth::logout();
        }

        return $report;
    }

    // Cards of one product share a design no. (or card code). Dummy databases only.
    private function groups(string $dir): array
    {
        foreach (['tm2mst', 'tm2sdi', 'tm2prd'] as $c) {
            if (! str_starts_with(DB::connection($c)->getDatabaseName(), 'ddt')) {
                throw new RuntimeException('The dummy data engine only runs on ddt* databases (--env=mockup).');
            }
        }
        $groups = [];
        foreach (glob(rtrim($dir, '/\\').'/*.pdf') ?: [] as $pdf) {
            $d = $this->reader->read($pdf);
            $groups[$d['design_no'] ?: $d['card_code'] ?: basename($pdf)][] = [$d, $pdf];
        }

        return $groups;
    }

    // Why a product is not built (null = it can be built).
    private function skipReason(array $parts): ?string
    {
        if ($this->exists($parts[0][0])) {
            return 'already exists, skipped';
        }

        return ($gap = $this->missing($parts)) ? 'card incomplete: '.implode(', ', $gap) : null;
    }

    // One product (one or several part cards that share a design no.).
    private function one(array $parts, array &$quota, int $qty): array
    {
        if ($reason = $this->skipReason($parts)) {
            return ['note' => $reason];
        }
        $level = collect(self::LEVELS)->first(fn ($l) => ($quota[$l] ?? 0) > 0);
        if (! $level) {
            return ['note' => 'level quota used up, skipped'];
        }
        $quota[$level]--;

        $inquiry = $this->inquiry($parts, $qty);
        foreach (array_slice(self::LEVELS, 1, array_search($level, self::LEVELS, true)) as $stage) {
            $this->{'to'.ucfirst($stage)}($inquiry);
        }

        return ['level' => $level, 'note' => $inquiry->inquiry_no.', '.count($parts).' card(s)'];
    }

    private function exists(array $d): bool
    {
        $probe = new ProdMasterCard(['design_no' => $d['design_no'], 'card_code' => $d['card_code']]);

        return $this->cards->existingProduct($probe) !== null || ProdMasterCard::whereIn('status', ['INQUIRY', 'PRODUCT'])->where(fn ($q) => $q
            ->when($d['design_no'], fn ($x) => $x->orWhere('design_no', $d['design_no']))
            ->when($d['card_code'], fn ($x) => $x->orWhere('card_code', $d['card_code'])))->exists();
    }

    // Syarat dummy: artwork, ukuran P x L x T, pisau (ukuran datar), bahan.
    private function missing(array $parts): array
    {
        $gap = [];
        foreach ($parts as [$d, $pdf]) {
            foreach (['artwork' => ! $d['artwork'], 'size P x L x T' => ! ($d['dim_l'] && $d['dim_w'] && $d['dim_h']), 'knife size' => ! $d['knife_w'], 'material' => ! $d['materials']] as $what => $absent) {
                if ($absent) {
                    $gap[$what.' ('.basename($pdf).')'] = $what.' ('.basename($pdf).')';
                }
            }
        }

        return array_values($gap);
    }

    // ------------------------------------------------------------------ level 1: kartu -> Inquiry (Sales Confirm)

    private function inquiry(array $parts, int $qty): IqpInquiry
    {
        Auth::setUser($this->user('yuna'));
        $key = $parts[0][0]['design_no'] ?: $parts[0][0]['card_code'];
        $made = [];
        foreach ($parts as [$d, $pdf]) {
            $artwork = $d['artwork'];
            unset($d['artwork']);
            $card = $this->cards->fromData($d + ['images' => []], 'PDF', 'DUMMY', new UploadedFile($pdf, basename($pdf), 'application/pdf', null, true));
            $this->cards->storeFile($card, 'ARTWORK', file_get_contents($artwork), basename($artwork), mime_content_type($artwork), 1, $d['part_label']);
            $card->update(['group_key' => $key, 'customer_id' => $card->customer_id ?: $this->customer((string) $d['customer_text'])]);
            $made[] = $this->cards->refresh($card);
        }
        $this->cards->makeInquiry($made[0], ['qty' => $qty, 'deadline' => now()->addDays(7)->toDateString(), 'pic_sales' => 'Firman']);
        $inquiry = IqpInquiry::withoutGlobalScopes()->findOrFail($made[0]->refresh()->inquiry_id);
        $this->shapeParts($inquiry, collect($made));
        (new InquiryWriter($this->user('yuna')->login))->confirm($inquiry);

        return $inquiry->refresh();
    }

    // Customer yang belum ada di master dibuat (izin Jo 2026-10-14), supaya kartu tidak berhenti di Incomplete.
    private function customer(string $text): ?int
    {
        return $text === '' || preg_match('/^nama pelanggan/i', $text) ? null : ($this->cards->customerId($text)
            ?? CustCustomer::create(['customer_name' => $text, 'activate' => true, 'created_by' => 'dummy', 'updated_by' => 'dummy', 'data_origin' => 'DUMMY'])->id_customer);
    }

    // Ukuran datar tiap bagian = ukuran pisau per potong (bukan hasil rumus tipe dieline); urutan bagian: Cover dulu, lalu SET n dari terbesar ke 1.
    private function shapeParts(IqpInquiry $inquiry, $cards): void
    {
        $components = IqpPart::where('inquiry_id', $inquiry->id_inquiry)->where('part_kind', 'COMPONENT')->orderBy('part_seq')->get();
        $base = (int) IqpPart::where('inquiry_id', $inquiry->id_inquiry)->where('part_kind', 'ASSEMBLY')->count();
        $ordered = $components->sortBy(fn ($p) => $this->order($p->part_code))->values();
        foreach ($ordered as $i => $p) {
            $piece = $this->piece($this->cardOf($p, $cards));
            $p->update(['part_seq' => $base + $i + 1] + ($piece ? ['flat_w' => $piece[0], 'flat_h' => $piece[1], 'flat_area' => round($piece[0] * $piece[1], 2)] : []));
        }
    }

    private function order(string $code): array
    {
        return match (true) {
            (bool) preg_match('/COVER$/', $code) => [0, 0],
            (bool) preg_match('/SET (\d+)$/', $code, $m) => [1, -(int) $m[1]],
            default => [2, 0],
        };
    }

    private function cardOf(IqpPart $part, $cards): ProdMasterCard
    {
        $label = preg_replace('/^PART /', '', (string) $part->part_code);

        return $cards->first(fn ($c) => (string) $c->part_label === $label) ?? $cards->first();
    }

    // Ukuran pisau per potong [w, h] mm; null bila kartu tak punya pisau.
    private function piece(ProdMasterCard $c): ?array
    {
        $ref = ['w' => (float) ($c->flat_w ?: $c->knife_w), 'h' => (float) ($c->flat_h ?: $c->knife_h)];
        $p = MasterCardShape::knifePiece($c->toArray(), $ref);

        return $p ? [$p[0], $p[1]] : null;
    }

    // ------------------------------------------------------------------ level 2: ProDev (layout dari kartu; BOP/BOM = SpecEngine)

    private function toProdev(IqpInquiry $inquiry): void
    {
        $officer = $this->user('dedi');
        Auth::setUser($officer);
        InquiryStage::openInProdev($inquiry, $officer->login);
        $cards = ProdMasterCard::where('inquiry_id', $inquiry->id_inquiry)->get();
        foreach (IqpPart::where('inquiry_id', $inquiry->id_inquiry)->where('part_kind', 'COMPONENT')->orderBy('part_seq')->get() as $part) {
            $out = Prodev::component((int) $part->refresh()->prod_part_id)->saveLayout($this->layout((int) $part->prod_part_id, $this->cardOf($part, $cards)), true);
            if (! ($out['ok'] ?? false)) {
                throw new RuntimeException("ProDev {$inquiry->inquiry_no} {$part->part_code}: ".($out['message'] ?? 'gagal'));
            }
        }
        InquiryStage::syncProdev((int) $inquiry->id_inquiry);
    }

    // State kanvas ProDev dari kartu: kanvas = ukuran cetak, pisau = susunan potong, UP = jumlah potong, mesin = teks kartu, plano = bahan pertama bila sama.
    private function layout(int $prodPart, ProdMasterCard $c): array
    {
        [$pw, $ph] = $this->piece($c) ?? [(float) $c->flat_w, (float) $c->flat_h];
        $parts = count(MasterCardShape::parts($c->toArray()));
        $cols = $parts === 1 && $c->knife_w ? max(1, (int) round($c->knife_w / $pw)) : 1;
        $up = $parts === 1 ? MasterCardShape::pieces($c->toArray()) : 1;
        $rows = max(1, intdiv($up, $cols));
        [$kw, $kh] = [$pw * $cols / 10, $ph * $rows / 10];
        [$cl, $cw] = $c->print_w ? [$c->print_w / 10, $c->print_h / 10] : [$kw + 1, $kh + 1];
        $press = $this->press($c);
        $size = $c->materials_json[0] ?? [];
        $plano = DB::connection('tm2mst')->table('mat_plano_size')->where('activate', 1)->get()->first(fn ($p) => ! empty($size['size_p'])
            && abs(max($p->width_mm, $p->height_mm) - max($size['size_p'], $size['size_l'])) < 5 && abs(min($p->width_mm, $p->height_mm) - min($size['size_p'], $size['size_l'])) < 5);
        $objects = [];
        for ($i = 0; $i < $up; $i++) {
            $objects[] = ['x' => round(($i % $cols) * $pw / 10, 2), 'y' => round(intdiv($i, $cols) * $ph / 10, 2), 'w' => $pw / 10, 'h' => $ph / 10, 'r' => 0, 'fx' => false, 'fy' => false];
        }
        $sheet = [max($cl, $cw), min($cl, $cw)];

        return [
            'partId' => $prodPart, 'canvasL' => $sheet[0], 'canvasW' => $sheet[1], 'canvasLimit' => 'CUSTOM', 'gapCm' => 0.3, 'upCount' => $up, 'method' => 'CARD',
            'planoId' => $plano?->id_plano_size, 'efficiencyPct' => round(min(100, $up * $pw * $ph / 100 / ($sheet[0] * $sheet[1]) * 100), 2),
            'machine' => ['id' => $press->machine_id, 'name' => $press->machine_name, 'bedL' => round(($press->max_sheet_length_mm ?: self::BEDS[0]) / 10, 1), 'bedW' => round(($press->max_sheet_width_mm ?: self::BEDS[1]) / 10, 1)],
            'knifeW' => $kw, 'knifeH' => $kh,
            'spec' => ['sheetL' => $sheet[0], 'sheetW' => $sheet[1], 'planoL' => $sheet[0], 'planoW' => $sheet[1], 'planoName' => $plano?->plano_name ?? '', 'sheetsPerPlano' => 1,
                'printMachineId' => $press->machine_id, 'diecutMachineId' => null, 'upCount' => $up, 'orderQty' => 0, 'artworkColors' => null],
            'specOverride' => [], 'specReset' => [], 'objects' => $objects,
        ];
    }

    private function press(ProdMasterCard $c): ProdMachine
    {
        $text = implode(' ', $c->process_json ? array_column($c->process_json, 'text') : []);
        $legacy = collect(self::PRESS)->first(fn ($v, $re) => preg_match($re, $text)) ?? self::KOMORI;

        return ProdMachine::where('legacy_machine_names', $legacy)->first() ?? throw new RuntimeException("Mesin cetak '{$legacy}' tidak ada di master mesin.");
    }

    // ------------------------------------------------------------------ level 3: ProCost publish; level 4: Quotation WIN + CO Confirmed

    private function toProcost(IqpInquiry $inquiry): void
    {
        $costing = $this->user('wiwik');
        Auth::setUser($costing);
        $this->priceAndPublish((int) $inquiry->id_inquiry, $costing->login);
        InquiryStage::syncProcost((int) $inquiry->id_inquiry);
    }

    private function toCo(IqpInquiry $inquiry): void
    {
        Auth::setUser($this->user('yuna'));
        $order = $this->winViaQuotation((int) $inquiry->id_inquiry, ['sales_pic' => 'Firman', 'issued_date' => now()->toDateString(), 'valid_until' => now()->addDays(30)->toDateString(),
            'payment_term' => '30 hari', 'notes' => 'DUMMY-MASTERCARD', 'data_origin' => 'DUMMY']);
        $product = (int) $inquiry->refresh()->product_id;
        DB::connection('tm2sdi')->table('co_order')->where('id_order', $order->id_order)->update(['data_origin' => 'DUMMY']);
        DB::connection('tm2sdi')->table('co_order_item')->where('order_id', $order->id_order)->update(['data_origin' => 'DUMMY']);
        DB::connection('tm2mst')->table('prod_product')->where('id_product', $product)->update(['data_origin' => 'DUMMY']);
        DB::connection('tm2mst')->table('prod_part')->where('product_id', $product)->update(['data_origin' => 'DUMMY']);
        ProdMasterCard::where('inquiry_id', $inquiry->id_inquiry)->update(['status' => 'PRODUCT', 'product_id' => $product]);

        $co = CoOrder::findOrFail((int) $order->id_order);
        $item = $co->items()->where('line_kind', 'PRODUCT')->firstOrFail();
        CustomerOrder::pickQty($co, (int) $item->id_item);
        $due = now()->addDays($this->dueDays)->toDateString();
        CustomerOrder::save($co, ['items' => [$item->id_item => ['due_date' => $due]], 'customer_po_no' => 'PO-'.$inquiry->inquiry_no]);
        if ($error = CustomerOrder::confirm($co->refresh())) {
            throw new RuntimeException("Confirm {$co->co_no}: {$error}");
        }
        for ($n = 0; $n < 6 && $co->refresh()->doc_status === 'SUBMITTED'; $n++) {
            $this->approve($co);
        }
        if ($co->refresh()->doc_status !== 'CONFIRMED') {
            throw new RuntimeException("{$co->co_no} belum CONFIRMED setelah approval (status {$co->doc_status}).");
        }
    }

    private function approve(CoOrder $co): void
    {
        $log = FlowLog::where('module_code', 'CO')->where('doc_id', $co->id_order)->orderByDesc('flow_log_id')->first();
        $approver = SecUser::where('activate', true)->get()->first(fn ($u) => (int) $u->currentPositionId() === (int) $log?->actor_position_id)
            ?? throw new RuntimeException("{$co->co_no}: tidak ada user aktif pemegang jabatan approver.");
        Auth::setUser($approver);
        if ($error = CustomerOrder::decide($co, 'approve', '')) {
            throw new RuntimeException("Approve {$co->co_no}: {$error}");
        }
    }

    private function user(string $login): SecUser
    {
        return SecUser::where('login', $login)->where('activate', true)->first() ?? SecUser::findOrFail(1);
    }
}
