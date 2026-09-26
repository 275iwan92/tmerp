<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Data UAT Quotation (user 2026-09-26): inquiry berharga (costing published) yang
// belum di-quotation-kan, dari 3 menu Inquiry (NEW/REVISE/RECALC), untuk mencoba
// "Choose inquiry window". Tiap inquiry = salinan utuh inquiry DUMMY berharga yang
// sudah ada (part/bahan/proses/tier/tooling/costing/harga tier), jadi angkanya tetap
// hasil engine, bukan karangan. Hanya jalan di ddt*. Diulang = reset (IQ99UAT* lama dihapus).
//   php artisan db:seed --class=UatQuotationInquirySeeder --env=mockup
class UatQuotationInquirySeeder extends Seeder
{
    private const PREFIX = 'IQ99UAT';

    // [no. sumber, jenis, grup customer]; grup sama = customer sama (uji pilih >1 inquiry).
    private const PLAN = [
        ['IQ99DUM001', 'NEW', 0], ['IQ99DUM004', 'NEW', 0],
        ['IQ99DUM002', 'NEW', 1], ['IQ99DUM003', 'REVISE', 1],
        ['IQ99DUM005', 'REVISE', 2], ['IQ99DUM006', 'RECALC', 2],
        ['IQ99DUM001', 'RECALC', 3], ['IQ99DUM002', 'NEW', 3],
        ['IQ99DUM003', 'NEW', 4],
    ];

    private $db;

    private array $generated = [];

    public function run(): void
    {
        $this->db = DB::connection('tm2sdi');

        if (! str_starts_with($this->db->getDatabaseName(), 'ddt') || ! str_starts_with(DB::connection('tm2mst')->getDatabaseName(), 'ddt')) {
            throw new RuntimeException('UatQuotationInquirySeeder hanya boleh di database dummy ddt* (--env=mockup).');
        }

        $customers = $this->customers();
        $sales = DB::connection('tm2mst')->table('sales_person')->where('activate', 1)->orderBy('id_sales_person')->pluck('sales_name')->all() ?: [null];

        $this->db->transaction(function () use ($customers, $sales) {
            $this->reset();

            foreach (self::PLAN as $i => [$sourceNo, $type, $group]) {
                $source = $this->db->table('iqp_inquiry')->where('inquiry_no', $sourceNo)->first();
                $customer = $customers[$group % count($customers)];

                $this->cloneInquiry($source, [
                    'inquiry_no' => self::PREFIX.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'inquiry_type' => $type,
                    'customer_id' => $customer->id_customer,
                    'inquiry_date' => now()->subDays(9 - $i)->toDateString(),
                    'deadline_quotation' => now()->addDays(3 + $i)->toDateString(),
                    'pic_sales' => $sales[$i % count($sales)],
                    'revise_scope' => $type === 'REVISE' ? 'MATERIAL' : null,
                    'revise_reason' => $type === 'REVISE' ? 'Customer minta ganti bahan (data UAT)' : null,
                    'sub_state' => 'PRICE_CALCULATED',
                    'co_id' => null,
                    'data_origin' => 'DUMMY',
                ]);
            }
        });

        $this->command?->info(count(self::PLAN).' inquiry '.self::PREFIX.'* siap di '.$this->db->getDatabaseName().'.');
    }

    // Customer nyata (salinan master) yang kontak & termin bayarnya terisi, termin dibuat beragam.
    private function customers(): array
    {
        return DB::connection('tm2mst')->table('cust_customer')
            ->where('activate', 1)->where('contact_name', '<>', '')->where('payment_term', '<>', '')
            ->orderBy('id_customer')->get()
            ->groupBy('payment_term')->map->first()->values()
            ->concat(DB::connection('tm2mst')->table('cust_customer')->where('activate', 1)->where('contact_name', '<>', '')->orderBy('id_customer')->limit(5)->get())
            ->unique('id_customer')->take(5)->values()->all();
    }

    private function reset(): void
    {
        $inq = $this->db->table('iqp_inquiry')->where('inquiry_no', 'like', self::PREFIX.'%')->pluck('id_inquiry');

        if ($inq->isEmpty()) {
            return;
        }

        $quoIds = $this->db->table('quo_quotation_item')->whereIn('inquiry_id', $inq)->distinct()->pluck('quotation_id');
        $this->db->table('quo_quotation_item')->whereIn('inquiry_id', $inq)->delete();
        $this->db->table('quo_quotation')->whereIn('id_quotation', $quoIds)->where('doc_status', 'DRAFT')
            ->whereNotExists(fn ($q) => $q->from('quo_quotation_item')->whereColumn('quotation_id', 'quo_quotation.id_quotation'))->delete();

        $parts = $this->db->table('iqp_part')->whereIn('inquiry_id', $inq)->pluck('id_part');
        $costings = $this->db->table('iqp_costing')->whereIn('inquiry_id', $inq)->pluck('id_costing');
        $cparts = $this->db->table('iqp_costing_part')->whereIn('costing_id', $costings)->pluck('id_costing_part');
        $proves = $this->db->table('iqp_prove')->whereIn('inquiry_id', $inq)->pluck('id_prove');

        $this->db->table('iqp_costing_part_coating')->whereIn('costing_part_id', $cparts)->delete();
        $this->db->table('iqp_costing_price_tier')->whereIn('costing_id', $costings)->delete();
        $this->db->table('iqp_costing_state_log')->whereIn('costing_id', $costings)->delete();
        $this->db->table('iqp_costing_part')->whereIn('costing_id', $costings)->delete();
        $this->db->table('iqp_costing')->whereIn('id_costing', $costings)->delete();
        foreach (['iqp_part_finishing', 'iqp_part_ink', 'iqp_part_material', 'iqp_part_process', 'iqp_layout'] as $t) {
            $this->db->table($t)->whereIn('part_id', $parts)->delete();
        }
        $this->db->table('iqp_prove_log')->whereIn('prove_id', $proves)->delete();
        foreach (['iqp_prove', 'iqp_tooling', 'iqp_external_cost', 'iqp_tier', 'iqp_part'] as $t) {
            $this->db->table($t)->whereIn('inquiry_id', $inq)->delete();
        }
        $this->db->table('iqp_inquiry')->whereIn('id_inquiry', $inq)->delete();
    }

    private function cloneInquiry(object $src, array $override): void
    {
        $inqId = $this->insert('iqp_inquiry', 'id_inquiry', $src, $override);

        $partMap = $this->cloneChildren('iqp_part', 'id_part', 'inquiry_id', [$src->id_inquiry => $inqId]);
        $tierMap = $this->cloneChildren('iqp_tier', 'id_tier', 'inquiry_id', [$src->id_inquiry => $inqId]);
        $this->cloneChildren('iqp_tooling', 'id_tooling', 'inquiry_id', [$src->id_inquiry => $inqId]);
        $this->cloneChildren('iqp_external_cost', 'id_external_cost', 'inquiry_id', [$src->id_inquiry => $inqId]);
        $proveMap = $this->cloneChildren('iqp_prove', 'id_prove', 'inquiry_id', [$src->id_inquiry => $inqId]);
        $this->cloneChildren('iqp_prove_log', 'id_prove_log', 'prove_id', $proveMap);

        foreach (['iqp_part_finishing' => 'id_part_finishing', 'iqp_part_ink' => 'id_part_ink', 'iqp_part_material' => 'id_part_material',
            'iqp_part_process' => 'id_part_process', 'iqp_layout' => 'id_layout'] as $table => $pk) {
            $this->cloneChildren($table, $pk, 'part_id', $partMap);
        }

        $costingMap = $this->cloneChildren('iqp_costing', 'id_costing', 'inquiry_id', [$src->id_inquiry => $inqId], ['customer_id' => $override['customer_id']]);
        $cpartMap = $this->cloneChildren('iqp_costing_part', 'id_costing_part', 'costing_id', $costingMap, [], ['part_id' => $partMap]);
        $this->cloneChildren('iqp_costing_part_coating', 'id_coating', 'costing_part_id', $cpartMap);
        $this->cloneChildren('iqp_costing_price_tier', 'id_price_tier', 'costing_id', $costingMap, [], ['tier_id' => $tierMap]);
    }

    // Salin semua baris anak milik induk lama -> induk baru; $remap = kolom FK lain yang ikut dipetakan.
    private function cloneChildren(string $table, string $pk, string $fk, array $parentMap, array $set = [], array $remap = []): array
    {
        $map = [];

        foreach ($this->db->table($table)->whereIn($fk, array_keys($parentMap))->orderBy($pk)->get() as $row) {
            $values = [$fk => $parentMap[$row->{$fk}]] + $set;
            foreach ($remap as $col => $m) {
                if ($row->{$col} !== null) {
                    $values[$col] = $m[$row->{$col}] ?? $row->{$col};
                }
            }
            $map[$row->{$pk}] = $this->insert($table, $pk, $row, $values);
        }

        return $map;
    }

    private function insert(string $table, string $pk, object $row, array $override): int
    {
        $data = array_diff_key((array) $row, array_flip([$pk, ...$this->generatedColumns($table)]));

        foreach (['created_at', 'updated_at'] as $ts) {
            if (array_key_exists($ts, $data)) {
                $data[$ts] = now();
            }
        }
        if (array_key_exists('data_origin', $data)) {
            $data['data_origin'] = 'DUMMY';
        }

        return (int) $this->db->table($table)->insertGetId(array_intersect_key($override, $data) + $data, $pk);
    }

    private function generatedColumns(string $table): array
    {
        return $this->generated[$table] ??= array_column($this->db->select(
            "select column_name c from information_schema.columns where table_schema = database() and table_name = ? and extra like '%VIRTUAL%' or (table_schema = database() and table_name = ? and extra like '%STORED%')",
            [$table, $table]
        ), 'c');
    }
}
