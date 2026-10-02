<?php

use App\Models\Master\CustCustomer;
use App\Models\Master\CustCustomerContact;
use App\Models\Master\ProdProduct;
use App\Models\Prd\QcComplaintReview;
use App\Models\Sdi\CrmActivity;
use App\Models\Sdi\CrmComplaint;
use App\Models\Sdi\CrmComplaintItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Contoh data CRM untuk UAT -- HANYA di database dummy ddt* (aturan B.12: layar baru wajib punya dummy). Di tm2* tidak
// menulis apa pun. Penanda baris contoh: updated_by = 'uat-crm' (kontak: data_origin = DUMMY).
// Isi: kontak untuk 8 customer yang punya riwayat order, 9 aktivitas dengan follow-up lewat / hari ini / segera / nanti /
// selesai, dan 4 komplain: Draft, Confirmed menunggu QA (retur), Confirmed sudah dijawab QA, Closed.
return new class extends Migration
{
    private const TAG = 'uat-crm';

    // kode customer => [[nama kontak, jabatan, telepon], ...]
    private const CONTACTS = [
        '1150000052' => [['Ibu Lestari', 'Purchasing', '0812-3300-1452'], ['Bp. Hartono', 'Warehouse Head', '0813-5100-2231']],
        '1130000001' => [['Bp. Rudi', 'Owner', '0812-4900-7781']],
        '1150000034' => [['Bp. Mukri', 'Owner', '0813-3398-1120']],
        '1150000037' => [['Ibu Sinta', 'Purchasing', '0811-3600-482'], ['Bp. Yusuf', 'Quality Control', '0812-1700-9934']],
        '1150000043' => [['Bp. Made', 'Purchasing', '0819-9900-3317']],
        '1160000003' => [['Ibu Fenny', 'Owner', '0812-5200-6645']],
        '1150000016' => [['Bp. Anwar', 'Purchasing', '0851-0044-2290']],
        '1150000029' => [['Ibu Wulan', 'Purchasing', '0812-3111-7702']],
    ];

    private function isDummy(): bool
    {
        return str_starts_with(DB::connection('tm2sdi')->getDatabaseName(), 'ddt');
    }

    public function up(): void
    {
        if (! $this->isDummy() || CrmActivity::query()->where('updated_by', self::TAG)->exists()) {
            return;
        }
        $cust = CustCustomer::query()->whereIn('customer_code', array_keys(self::CONTACTS))->get()->keyBy('customer_code');
        $contact = [];
        foreach (self::CONTACTS as $code => $people) {
            if (! ($c = $cust->get($code)) || CustCustomerContact::query()->where('customer_id', $c->id_customer)->exists()) {
                continue;
            }
            foreach ($people as $i => [$name, $position, $phone]) {
                $k = CustCustomerContact::create(['customer_id' => $c->id_customer, 'contact_name' => $name, 'position' => $position, 'phone' => $phone,
                    'is_default' => $i === 0, 'activate' => true, 'created_by' => self::TAG, 'updated_by' => self::TAG]);
                DB::connection('tm2mst')->table('cust_customer_contact')->where('id_customer_contact', $k->id_customer_contact)->update(['data_origin' => 'DUMMY']);
                $contact[$code] ??= $k;
            }
        }

        // Follow-up quotation lama: yang sudah disusul aktivitas berikutnya pada quotation yang sama dianggap selesai.
        CrmActivity::query()->whereNotNull('quotation_id')->whereNotNull('next_action_date')->where('next_done', 0)->get()->each(function (CrmActivity $a) {
            if (CrmActivity::query()->where('quotation_id', $a->quotation_id)->where('id_activity', '>', $a->id_activity)->exists()) {
                $a->forceFill(['next_done' => true, 'next_done_at' => now(), 'next_done_by' => self::TAG])->save();
            }
        });

        $day = fn (int $n) => now()->addDays($n)->toDateString();
        // [kode customer, pencatat, hari, jenis, topik, hasil, catatan, hari follow-up|null, tindak lanjut, selesai?]
        $activities = [
            ['1150000052', 'eni', -12, 'VISIT', 'RELATION', 'POSITIVE', 'Routine visit. The customer plans a new sleeve box for the year-end promotion.', -5, 'Send a sample of 310 gsm ivory board', false],
            ['1150000052', 'eni', -4, 'CALL', 'ORDER', 'NEUTRAL', 'Asked about the delivery of the last order; promised to check the slot with PPIC.', 0, 'Confirm the delivery slot', false],
            ['1150000052', 'eni', -1, 'WHATSAPP', 'PAYMENT', 'POSITIVE', 'Reminded about the invoice due this week; they will transfer on Friday.', 2, 'Check the transfer', false],
            ['1130000001', 'widita', -20, 'CALL', 'PROSPECT', 'NO_ANSWER', 'No answer, tried twice.', -10, 'Call again', true],
            ['1130000001', 'widita', -9, 'VISIT', 'QUOTATION', 'POSITIVE', 'Price is acceptable when the quantity goes up to 5,000 pcs.', 6, 'Bring the revised price', false],
            ['1150000034', 'widita', -30, 'MEETING', 'RELATION', 'NEUTRAL', 'Courtesy meeting; no new project this quarter.', null, null, false],
            ['1150000029', 'yuna', -2, 'EMAIL', 'COMPLAINT', 'NEGATIVE', 'The customer complains about a colour difference on the last batch.', 1, 'Call back with the QA answer', false],
            ['1150000016', 'yuna', -6, 'CALL', 'ORDER', 'POSITIVE', 'Repeat order next month, same specification.', 12, 'Ask for the PO', false],
            ['1160000003', 'eni', -45, 'VISIT', 'PROSPECT', 'NEUTRAL', 'Showed new hang-tag samples; they will think about it.', -30, 'Ask for their decision', false],
        ];
        foreach ($activities as [$code, $by, $d, $type, $topic, $result, $notes, $next, $action, $done]) {
            if (! ($c = $cust->get($code))) {
                continue;
            }
            $k = $contact[$code] ?? null;
            CrmActivity::create(['customer_id' => $c->id_customer, 'contact_id' => $k?->id_customer_contact, 'contact_person' => $k?->contact_name, 'activity_type' => $type,
                'topic' => $topic, 'activity_date' => $day($d), 'result' => $result, 'notes' => $notes, 'next_action_date' => $next === null ? null : $day($next),
                'next_action' => $action, 'next_done' => $done, 'next_done_at' => $done ? now() : null, 'next_done_by' => $done ? $by : null, 'created_by' => $by, 'updated_by' => self::TAG]);
        }

        // [kode customer, pencatat, hari, jenis, kategori, perlu QA, status, keluhan, permintaan, qty keluhan, qty kembali, masalah]
        $complaints = [
            ['1150000052', 'eni', -3, 'RETURN', 'QUALITY', true, 'CONFIRMED', 'The customer returned part of the last delivery: the glue line on the side flap opens during packing.', 'REPLACE', 500, 500, 'Side flap glue opens'],
            ['1150000037', 'widita', -8, 'COMPLAINT', 'QUALITY', true, 'ANSWERED', 'Sheets stick together; the varnish peels when the boxes are separated.', 'REWORK', 300, 0, 'Varnish sticks / peels'],
            ['1150000043', 'yuna', -15, 'COMPLAINT', 'DELIVERY', false, 'CLOSED', 'The delivery arrived one day late and outside the receiving hours.', 'EXPLANATION', 1200, 0, 'Late delivery'],
            ['1150000034', 'widita', -1, 'COMPLAINT', 'QUANTITY', false, 'DRAFT', 'The customer counted 2 bundles less than the delivery note.', 'REPLACE', 200, 0, 'Short quantity'],
        ];
        DB::connection('tm2sdi')->transaction(function () use ($complaints, $cust, $contact, $day) {
            foreach ($complaints as [$code, $by, $d, $type, $category, $qa, $state, $text, $ask, $qty, $ret, $problem]) {
                if (! ($c = $cust->get($code))) {
                    continue;
                }
                $k = $contact[$code] ?? null;
                $confirmed = $state !== 'DRAFT';
                $ref = DB::connection('tm2sdi')->table('co_delivery_legacy')->where('customer_id', $c->id_customer)->orderByDesc('dnote_date')->value('legacy_dnote_no');
                $doc = CrmComplaint::create([
                    'complaint_no' => $confirmed ? CrmComplaint::nextNo() : null, 'complaint_date' => $day($d), 'complaint_type' => $type, 'customer_id' => $c->id_customer,
                    'contact_id' => $k?->id_customer_contact, 'contact_person' => $k?->contact_name, 'received_via' => 'WHATSAPP', 'category' => $category, 'needs_qa' => $qa,
                    'ref_doc_no' => $ref, 'description' => $text, 'requested_action' => $ask, 'stat_confirm' => $confirmed, 'confirmed_at' => $confirmed ? now()->addDays($d) : null,
                    'confirmed_by' => $confirmed ? $by : null, 'stat_close' => $state === 'CLOSED', 'closed_at' => $state === 'CLOSED' ? now()->addDays($d + 2) : null,
                    'closed_by' => $state === 'CLOSED' ? $by : null, 'resolution' => $state === 'CLOSED' ? 'EXPLAINED' : null,
                    'resolution_note' => $state === 'CLOSED' ? 'Apologised by phone; the next delivery follows the receiving hours.' : null, 'created_by' => $by, 'updated_by' => self::TAG,
                ]);
                $p = ProdProduct::query()->where('customer_id', $c->id_customer)->where('activate', true)->orderByDesc('id_product')->first();
                CrmComplaintItem::create(['complaint_id' => $doc->id_complaint, 'line_no' => 1, 'product_id' => $p?->id_product, 'product_name' => $p?->product_name ?? 'Printed box',
                    'qty_complaint' => $qty, 'qty_return' => $ret, 'qty_unit' => 'pcs', 'defect_note' => $problem, 'created_by' => $by, 'updated_by' => self::TAG]);
                if ($state === 'ANSWERED') {
                    QcComplaintReview::create(['complaint_id' => $doc->id_complaint, 'complaint_no' => $doc->complaint_no, 'verdict' => 'ACCEPTED',
                        'root_cause' => 'The UV varnish was not fully dry before the sheets were stacked.', 'corrective_action' => 'Re-run 300 pcs with a longer drying time.',
                        'preventive_action' => 'Add a drying check to the QC checklist for UV varnish jobs.', 'disposition' => 'NONE', 'stat_confirm' => true,
                        'reviewed_at' => now()->addDays($d + 3), 'reviewed_by' => 'johiwan', 'created_by' => 'johiwan', 'updated_by' => self::TAG]);
                }
            }
        });
    }

    public function down(): void
    {
        if (! $this->isDummy()) {
            return;
        }
        $ids = CrmComplaint::query()->where('updated_by', self::TAG)->pluck('id_complaint');
        QcComplaintReview::query()->whereIn('complaint_id', $ids)->delete();
        CrmComplaint::query()->whereKey($ids)->get()->each->delete();
        CrmActivity::query()->where('updated_by', self::TAG)->delete();
        CustCustomerContact::query()->where('updated_by', self::TAG)->delete();
    }
};
