<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu Sales (user 2026-10-02): "Sales adalah big group, small group-nya Inquiry & Quotation, Order dan CRM -- dijadikan
// satu grup besar, tetapi tetap punya grup kecil yang modular yang bisa dilepas-pasang".
//   Sales (modul) > Inquiry & Quotation (baris PREORDER, kini grup di dalam Sales) | Order | Delivery | CRM
// Tiap grup kecil punya saklar `activate` sendiri: mati = menu + layarnya tertutup (AppMenu::open), data tetap.
// CRM = grup baru (Customer 360, Activities & Follow-up, Complaint & Return + pintasan master / laporan);
// jawaban mutu atas komplain = Production > Quality > Complaint Review (QA).
// HANYA database dummy ddt* (menunggu user menilai pratinjaunya); tm2sys menyusul setelah disetujui.
return new class extends Migration
{
    // kode => [label, ikon, slug v2, jenis objek, hak akses]
    private const CRM_ITEMS = [
        'CRM_CUST360' => ['Customer 360', 'fa-solid fa-address-card', 'crm-customer', 'report', null],
        'CRM_ACTIVITY' => ['Activities & Follow-up', 'fa-solid fa-phone-volume', 'crm-activity', 'form', null],
        'CRM_COMPLAINT' => ['Complaint & Return', 'fa-solid fa-triangle-exclamation', 'crm-complaint', 'form', null],
    ];

    // Pintasan (menu = data): baris baru ber-target sama dengan baris asalnya, izin tetap modul pemilik.
    private const SHORTCUTS = [
        'CRM_MDGRP' => ['Master Data', 'fa-solid fa-database', 800, ['CRM_MD_CUST' => ['MD_CUST', 'MDATA'], 'CRM_MD_SALES' => ['MD_SALES_PERSON', 'MDATA']]],
        'CRM_RPTGRP' => ['Report', 'fa-solid fa-chart-column', 900, ['CRM_RPT_WINRATE' => ['SALES_WINRATE', null]]],
    ];

    private function sys()
    {
        return DB::connection('tm2sys');
    }

    private function isDummy(): bool
    {
        return str_starts_with($this->sys()->getDatabaseName(), 'ddt');
    }

    private function row(array $v): array
    {
        return $v + ['activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now()];
    }

    public function up(): void
    {
        $menu = fn () => $this->sys()->table('sys_app_menu');
        $sales = $menu()->where('code', 'SALES')->where('menu_type', 'module')->first();
        if (! $this->isDummy() || ! $sales || $menu()->where('code', 'SALES_CRMGRP')->exists()) {
            return;
        }

        // 1. Inquiry & Quotation kembali ke dalam Sales sebagai grup kecil (isi dan saklarnya tidak berubah).
        $menu()->where('code', 'PREORDER')->update(['menu_type' => 'group', 'parent_id' => $sales->id, 'sort_order' => 10, 'short_label' => null]);
        // 2. Order + Delivery: urutan dan ikon grup.
        $menu()->where('code', 'SALES_ORDGRP')->update(['label' => 'Order', 'icon' => 'fa-solid fa-bag-shopping', 'sort_order' => 20]);
        $menu()->where('code', 'SALES_DLVGRP')->update(['sort_order' => 30]);

        // 3. CRM.
        $crm = $menu()->insertGetId($this->row(['parent_id' => $sales->id, 'code' => 'SALES_CRMGRP', 'label' => 'CRM', 'icon' => 'fa-solid fa-handshake',
            'menu_type' => 'group', 'sort_order' => 40, 'doc_slug' => 'sales']));
        $n = 10;
        foreach (self::CRM_ITEMS as $code => [$label, $icon, $slug, $type, $object]) {
            $menu()->insert($this->row(['parent_id' => $crm, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item', 'object_type' => $type,
                'target' => 'v2:'.$slug, 'sort_order' => $n, 'required_object' => $object]));
            $n += 10;
        }
        foreach (self::SHORTCUTS as $code => [$label, $icon, $sort, $items]) {
            $grp = $menu()->insertGetId($this->row(['parent_id' => $crm, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'group', 'sort_order' => $sort]));
            $n = 10;
            foreach ($items as $new => [$origin, $object]) {
                if ($o = $menu()->where('code', $origin)->first()) {
                    $menu()->insert($this->row(['parent_id' => $grp, 'code' => $new, 'label' => $o->label, 'trans_key' => $o->trans_key, 'icon' => $o->icon, 'menu_type' => 'item',
                        'object_type' => $o->object_type, 'target' => $o->target, 'sort_order' => $n, 'required_object' => $object]));
                    $n += 10;
                }
            }
        }

        // 4. QA: jawaban mutu atas komplain customer, di menu Production.
        if (($prod = $menu()->where('code', 'PROD')->where('menu_type', 'module')->first()) && ! $menu()->where('code', 'PROD_QC')->exists()) {
            $qc = $menu()->insertGetId($this->row(['parent_id' => $prod->id, 'code' => 'PROD_QC', 'label' => 'Quality', 'icon' => 'fa-solid fa-award', 'menu_type' => 'group', 'sort_order' => 50]));
            $menu()->insert($this->row(['parent_id' => $qc, 'code' => 'PROD_QC_COMPLAINT', 'label' => 'Complaint Review (QA)', 'icon' => 'fa-solid fa-magnifying-glass-chart',
                'menu_type' => 'item', 'object_type' => 'form', 'target' => 'v2:qc-complaint-review', 'sort_order' => 10]));
        }
    }

    public function down(): void
    {
        if (! $this->isDummy()) {
            return;
        }
        $menu = fn () => $this->sys()->table('sys_app_menu');
        $codes = array_merge(array_keys(self::CRM_ITEMS), array_keys(self::SHORTCUTS), ...array_map(fn ($g) => array_keys($g[3]), array_values(self::SHORTCUTS)));
        $menu()->whereIn('code', array_merge($codes, ['SALES_CRMGRP', 'PROD_QC_COMPLAINT', 'PROD_QC']))->delete();
        $menu()->where('code', 'SALES_ORDGRP')->update(['label' => 'Sales Order', 'icon' => 'fa-solid fa-clipboard-list', 'sort_order' => 30]);
        $menu()->where('code', 'SALES_DLVGRP')->update(['sort_order' => 40]);
        $menu()->where('code', 'PREORDER')->update(['menu_type' => 'module', 'parent_id' => null, 'sort_order' => 9, 'short_label' => 'INQ']);
    }
};
