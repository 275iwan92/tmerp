<?php

namespace App\Services\Migration;

use App\Models\Legacy\MstVendor;
use App\Models\Legacy\MstVendorBank;
use App\Models\Legacy\MstVendorCustomerCategory;
use App\Models\Legacy\MstVendorSupplierCategory;
use App\Models\Master\CustCategory;
use App\Models\Master\CustCustomer;
use App\Models\Master\CustCustomerBank;
use App\Models\Master\GeoCity;
use App\Models\Master\SupCategory;
use App\Models\Master\SupSupplier;
use App\Models\Master\SupSupplierBank;

// Engine migrasi tm2old.mst_vendor* -> tm2mst (cust_customer/sup_supplier +
// turunannya). Dipicu manual dari tab "Migrasi Data Legacy" (Setup, admin-only).
// Idempotent: aman diklik berkali-kali — matching by legacy_vendor_code /
// legacy_vendor_bank_id, bukan insert buta. Tidak ada baris yang di-skip diam-diam;
// semua kegagalan & kota yang tak cocok masuk laporan supaya admin bisa tindak lanjut.
class VendorMigrationService
{
    public function run(string $actor): array
    {
        // ~1.160 baris vendor + turunannya bisa melebihi memory_limit default
        // PHP-FPM (128M) saat dijalankan lewat web (beda dari CLI artisan yang
        // kadang punya limit lebih longgar) — batch admin sekali-jalan ini
        // wajar butuh lebih. TIDAK dikembalikan ke limit semula setelah
        // selesai — ini akan gagal (PHP menolak menurunkan memory_limit di
        // bawah pemakaian saat ini) dan proses PHP-FPM/worker berikutnya toh
        // memuat ulang php.ini bawaannya sendiri.
        ini_set('memory_limit', '512M');
        set_time_limit(120);

        $report = [
            'categories' => ['cust' => 0, 'sup' => 0],
            'customers' => ['created' => 0, 'updated' => 0],
            'suppliers' => ['created' => 0, 'updated' => 0],
            'banks' => ['cust' => 0, 'sup' => 0, 'skipped' => 0],
            'city' => ['matched' => 0, 'unmatched' => []],
            'paymentTerm' => ['matched' => 0, 'unmatched' => []],
            'errors' => [],
        ];

        $this->migrateCategories($report, $actor);
        $cityMap = $this->buildCityMap();
        $this->migrateVendors($report, $actor, $cityMap);
        $this->migrateBanks($report, $actor);

        ksort($report['city']['unmatched']);
        ksort($report['paymentTerm']['unmatched']);

        return $report;
    }

    // Legacy mst_vendor.payment_code (teks bebas) -> fin_payment_term.payment_term_code.
    // Sama persis dengan pemetaan satu-kali di migration
    // 2026_08_18_000011_create_fin_payment_term_table.php — kalau daftar
    // master term berubah, sesuaikan juga di sini.
    protected function paymentTermMap(): array
    {
        return [
            'BAYAR KONTAN' => 'CASH',
            'BAYAR DI TEMPAT' => 'COD',
            'BAYAR DIMUKA' => 'ADVANCE',
            'BAYAR 7 HARI' => 'NET7',
            'BAYAR 14 HARI' => 'NET14',
            'BAYAR 21 HARI' => 'NET21',
            'BAYAR 30 HARI' => 'NET30',
            'BAYAR 45 HARI' => 'NET45',
            'BAYAR 60 HARI' => 'NET60',
            'BAYAR 90 HARI' => 'NET90',
            'TIDAK BAYAR' => 'NONE',
        ];
    }

    // payment_term sekarang FK ke fin_payment_term (bukan teks bebas lagi) —
    // teks legacy yang tak dikenal TIDAK dipaksakan (akan melanggar FK),
    // dicatat di laporan supaya admin tahu & bisa pilih manual di form.
    protected function mapPaymentTerm(?string $legacyText, array &$report): ?string
    {
        $key = mb_strtoupper(trim((string) $legacyText));
        if ($key === '') {
            return null;
        }

        $code = $this->paymentTermMap()[$key] ?? null;

        if ($code !== null) {
            $report['paymentTerm']['matched']++;
        } else {
            $report['paymentTerm']['unmatched'][$key] = ($report['paymentTerm']['unmatched'][$key] ?? 0) + 1;
        }

        return $code;
    }

    protected function migrateCategories(array &$report, string $actor): void
    {
        foreach (MstVendorCustomerCategory::all() as $c) {
            $code = trim((string) $c->customer_category);
            if ($code === '') {
                continue;
            }
            CustCategory::updateOrCreate(
                ['category_code' => $code],
                ['description' => trim((string) $c->description) ?: null, 'updated_by' => $actor, 'created_by' => $actor]
            );
            $report['categories']['cust']++;
        }

        foreach (MstVendorSupplierCategory::all() as $c) {
            $code = trim((string) $c->supplier_category);
            if ($code === '') {
                continue;
            }
            SupCategory::updateOrCreate(
                ['category_code' => $code],
                ['description' => trim((string) $c->description) ?: null, 'updated_by' => $actor, 'created_by' => $actor]
            );
            $report['categories']['sup']++;
        }
    }

    // key => id_city, key = city_name di-uppercase+trim. Nama duplikat (mis.
    // "Malang" kota vs kabupaten) diambil id_city pertama — best-effort, sesuai
    // keputusan Jo. Ambiguitas tidak diblokir, admin bisa koreksi manual nanti.
    protected function buildCityMap(): array
    {
        $map = [];
        foreach (GeoCity::query()->get(['id_city', 'city_name']) as $c) {
            $key = mb_strtoupper(trim((string) $c->city_name));
            if ($key !== '' && ! isset($map[$key])) {
                $map[$key] = $c->id_city;
            }
        }

        return $map;
    }

    protected function migrateVendors(array &$report, string $actor, array $cityMap): void
    {
        MstVendor::query()->orderBy('vendor_code')->chunk(200, function ($chunk) use (&$report, $actor, $cityMap) {
            foreach ($chunk as $v) {
                try {
                    $areaId = null;
                    $areaLevel = null;
                    $cityKey = mb_strtoupper(trim((string) $v->city_name));

                    if ($cityKey !== '') {
                        if (isset($cityMap[$cityKey])) {
                            $areaId = $cityMap[$cityKey];
                            $areaLevel = 'CITY';
                            $report['city']['matched']++;
                        } else {
                            $report['city']['unmatched'][$cityKey] = ($report['city']['unmatched'][$cityKey] ?? 0) + 1;
                        }
                    }

                    // Hanya kirim default_area_id/level kalau match ketemu run
                    // ini — kalau TIDAK match, jangan timpa nilai yang sudah ada
                    // (baik dari run sebelumnya maupun koreksi manual admin,
                    // mis. kasus JAKARTA/JOGJA yang gagal auto-match).
                    $areaFields = $areaId !== null ? ['default_area_id' => $areaId, 'default_area_level' => $areaLevel] : [];

                    // payment_term sekarang FK ke fin_payment_term — sama
                    // prinsipnya dengan area: cuma kirim kalau teks legacy
                    // berhasil dipetakan ke kode master, jangan timpa pilihan
                    // yang sudah ada (manual admin atau run sebelumnya) dengan
                    // NULL cuma karena teksnya tak dikenal.
                    $paymentTermCode = $this->mapPaymentTerm($v->payment_code, $report);
                    $paymentTermFields = $paymentTermCode !== null ? ['payment_term' => $paymentTermCode] : [];

                    $common = [
                        'nickname' => $this->blank($v->nickname),
                        'alias_code' => $this->blank($v->alias_code),
                        'contact_name' => $this->blank($v->vendor_contact),
                        'default_address' => $this->blank($v->address),
                        'email' => $this->blank($v->email),
                        'phone' => $this->blank($v->phone_1),
                        'phone_2' => $this->blank($v->phone_2),
                        'fax' => $this->blank($v->fax),
                        'website' => $this->blank($v->website),
                        'tax_code' => $this->blank($v->tax_reg_no),
                        'tax_purchase_value' => $v->tax_purchase_value,
                        'tax_service_value' => $v->tax_service_value,
                        'is_blacklisted' => ((int) $v->flag_blacklist) === 1,
                        'note' => $this->blank($v->note),
                        'updated_by' => $actor,
                    ];

                    if ((int) $v->as_customer === 1) {
                        $existing = CustCustomer::where('legacy_vendor_code', $v->vendor_code)->first();
                        $data = array_merge($common, [
                            'customer_name' => $v->vendor_name,
                            'category' => $this->blank($v->customer_category),
                        ]);

                        if ($existing) {
                            $existing->update(array_merge($data, $areaFields, $paymentTermFields));
                            $report['customers']['updated']++;
                        } else {
                            CustCustomer::create(array_merge($data, $areaFields, $paymentTermFields, [
                                'customer_code' => $v->vendor_code,
                                'legacy_vendor_code' => $v->vendor_code,
                                'is_active' => true,
                                'created_by' => $actor,
                            ]));
                            $report['customers']['created']++;
                        }
                    }

                    if ((int) $v->as_supplier === 1) {
                        $existing = SupSupplier::where('legacy_vendor_code', $v->vendor_code)->first();
                        $data = array_merge($common, [
                            'supplier_name' => $v->vendor_name,
                            'category' => $this->blank($v->supplier_category),
                            'is_subcon' => ((int) $v->supplier_subcon) === 1,
                        ]);

                        if ($existing) {
                            $existing->update(array_merge($data, $areaFields, $paymentTermFields));
                            $report['suppliers']['updated']++;
                        } else {
                            SupSupplier::create(array_merge($data, $areaFields, $paymentTermFields, [
                                'supplier_code' => $v->vendor_code,
                                'legacy_vendor_code' => $v->vendor_code,
                                'is_active' => true,
                                'created_by' => $actor,
                            ]));
                            $report['suppliers']['created']++;
                        }
                    }
                } catch (\Throwable $e) {
                    $report['errors'][] = "{$v->vendor_code} ({$v->vendor_name}): {$e->getMessage()}";
                }
            }
        });
    }

    protected function migrateBanks(array &$report, string $actor): void
    {
        MstVendorBank::query()->orderBy('vendor_bank_id')->chunk(100, function ($chunk) use (&$report, $actor) {
            foreach ($chunk as $b) {
                try {
                    $cust = CustCustomer::where('legacy_vendor_code', $b->vendor_code)->first();
                    $sup = SupSupplier::where('legacy_vendor_code', $b->vendor_code)->first();

                    if (! $cust && ! $sup) {
                        $report['banks']['skipped']++;

                        continue;
                    }

                    $data = [
                        'bank_code' => $this->blank($b->bank_code),
                        'bank_account_no' => $this->blank($b->bank_account_no),
                        'bank_account_name' => $this->blank($b->bank_account_name),
                        'is_active' => true,
                        'updated_by' => $actor,
                    ];

                    if ($cust) {
                        CustCustomerBank::updateOrCreate(
                            ['legacy_vendor_bank_id' => $b->vendor_bank_id],
                            array_merge($data, ['customer_id' => $cust->id_customer, 'created_by' => $actor])
                        );
                        $report['banks']['cust']++;
                    }

                    if ($sup) {
                        SupSupplierBank::updateOrCreate(
                            ['legacy_vendor_bank_id' => $b->vendor_bank_id],
                            array_merge($data, ['supplier_id' => $sup->id_supplier, 'created_by' => $actor])
                        );
                        $report['banks']['sup']++;
                    }
                } catch (\Throwable $e) {
                    $report['errors'][] = "bank #{$b->vendor_bank_id} ({$b->vendor_code}): {$e->getMessage()}";
                }
            }
        });
    }

    protected function blank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
