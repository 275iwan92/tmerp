<?php

/*
 * REMARK
 * Tujuan       : Master Supplier dari layar v2 md-supplier-form: simpan supplier (identitas, alamat, PIC, termin dan pajak), aktif / nonaktif, Block / Unblock, Delete (atau nonaktif bila masih dipakai), kontak dan cabang tambahan.
 * Sumber hulu  : Aturan dipindah apa adanya dari halaman v1 pages::master.supplier (diarsipkan di tmerp_unused/master_supplier_v1_2026-10-18.blade.php): kode unik, batas panjang, tarif pajak 0-100, satu default per cabang / kontak; Delete memakai SupplierGuardService yang diperluas.
 * Input        : Isian form (header), area dari pencarian wilayah (LEVEL:id), isian dialog kontak / cabang, user.
 * Output       : tm2mst.sup_supplier, sup_supplier_contact, sup_supplier_branch berubah; ValidationException bila aturan dilanggar.
 * Dipakai oleh : routes/v2_master.php (v2.md.supplier.*), layar md-supplier-form, tes SupplierMasterServiceTest.
 * Status       : Aktif. Delete permanen hanya bila tidak ada yang menunjuk supplier; kalau ada, supplier dinonaktifkan dan alasannya dikembalikan.
 */

namespace App\Services\Master;

use App\Models\Master\FinPaymentTerm;
use App\Models\Master\SupCategory;
use App\Models\Master\SupSupplier;
use App\Models\Master\SupSupplierBranch;
use App\Models\Master\SupSupplierContact;
use App\Models\Sys\SecUser;
use App\Support\V2\Lookups;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class SupplierMasterService
{
    public function __construct(private SupplierGuardService $guard) {}

    public function save(?SupSupplier $s, array $in, SecUser $user): SupSupplier
    {
        $in['is_subcon'] = ! empty($in['is_subcon']);
        foreach (['tax_purchase_value', 'tax_service_value'] as $k) {
            $in[$k] = Locale::input($in[$k] ?? null) ?? ($in[$k] ?? null);
        }
        $area = $this->area($in['default_area'] ?? null);
        if (array_key_exists('ship_postal_code', $in)) {
            $in['postal_code'] = $in['ship_postal_code'];
        }

        $d = Validator::make($in, [
            'supplier_code' => ['required', 'string', 'max:50', 'unique:tm2mst.sup_supplier,supplier_code'.($s ? ','.$s->id_supplier.',id_supplier' : '')],
            'supplier_name' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'exists:tm2mst.sup_category,category_code'],
            'is_subcon' => ['boolean'],
            'contact_name' => ['nullable', 'string', 'max:200'],
            'default_address' => ['nullable', 'string'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'phone_2' => ['nullable', 'string', 'max:50'],
            'fax' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'string', 'max:150'],
            'payment_term' => ['nullable', 'string', 'exists:tm2mst.fin_payment_term,payment_term_code'],
            'tax_code' => ['nullable', 'string', 'max:25'],
            'tax_purchase_value' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_service_value' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'note' => ['nullable', 'string'],
        ], [], ['supplier_code' => 'Supplier code', 'supplier_name' => 'Supplier name', 'tax_purchase_value' => 'Purchase tax rate', 'tax_service_value' => 'Service tax rate'])->validate();

        $data = array_map(fn ($v) => $v === '' ? null : $v, $d) + ['default_area_id' => $area['id'] ?? null, 'default_area_level' => $area['level'] ?? null, 'updated_by' => $user->login];
        // Kolom teks yang tidak diisi tetap string kosong seperti v1 (bukan NULL).
        foreach (['contact_name', 'default_address', 'postal_code', 'email', 'phone', 'phone_2', 'fax', 'website', 'tax_code', 'note'] as $text) {
            $data[$text] ??= '';
        }
        $data['tax_purchase_value'] ??= 0;
        $data['tax_service_value'] ??= 0;

        if ($s) {
            $s->update($data);

            return $s;
        }

        return SupSupplier::create($data + ['activate' => true, 'is_blacklisted' => false, 'created_by' => $user->login]);
    }

    public function setActive(SupSupplier $s, bool $active, SecUser $user): void
    {
        $s->update(['activate' => $active, 'updated_by' => $user->login]);
    }

    public function toggleBlock(SupSupplier $s, SecUser $user): bool
    {
        $s->update(['is_blacklisted' => ! $s->is_blacklisted, 'updated_by' => $user->login]);

        return (bool) $s->is_blacklisted;
    }

    /**
     * Delete permanen bila tidak ada yang menunjuk supplier; kalau ada, nonaktifkan.
     *
     * @return list<string> alasan yang menahan penghapusan (kosong = sudah dihapus)
     */
    public function delete(SupSupplier $s, SecUser $user): array
    {
        $reasons = $this->guard->blockingReasons($s->id_supplier);
        if ($reasons !== []) {
            $s->update(['activate' => false, 'updated_by' => $user->login]);

            return $reasons;
        }
        DB::connection('tm2mst')->transaction(function () use ($s) {
            SupSupplierBranch::where('supplier_id', $s->id_supplier)->delete();
            SupSupplierContact::where('supplier_id', $s->id_supplier)->delete();
            $s->delete();
        });

        return [];
    }

    public function saveBranch(SupSupplier $s, ?int $branchId, array $in, SecUser $user): SupSupplierBranch
    {
        $in['is_default'] = ! empty($in['is_default']);
        $area = $this->area($in['branch_area'] ?? null);
        $d = Validator::make($in, [
            'branch_name' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'ship_postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_default' => ['boolean'],
        ], [], ['branch_name' => 'Branch name'])->validate();
        $payload = ['supplier_id' => $s->id_supplier, 'branch_name' => (string) ($d['branch_name'] ?? ''), 'address' => (string) ($d['address'] ?? ''), 'area_id' => $area['id'] ?? null,
            'area_level' => $area['level'] ?? null, 'postal_code' => (string) ($d['ship_postal_code'] ?? ''), 'phone' => (string) ($d['phone'] ?? ''), 'is_default' => $d['is_default'], 'updated_by' => $user->login];

        if ($branchId) {
            $b = SupSupplierBranch::where('supplier_id', $s->id_supplier)->findOrFail($branchId);
            $b->update($payload);
        } else {
            $b = SupSupplierBranch::create($payload + ['created_by' => $user->login]);
        }
        if ($d['is_default']) {
            SupSupplierBranch::where('supplier_id', $s->id_supplier)->whereKeyNot($b->getKey())->update(['is_default' => false]);
        }

        return $b;
    }

    public function deleteBranch(SupSupplier $s, int $branchId): void
    {
        SupSupplierBranch::where('supplier_id', $s->id_supplier)->whereKey($branchId)->delete();
    }

    public function setDefaultBranch(SupSupplier $s, int $branchId): void
    {
        $b = SupSupplierBranch::where('supplier_id', $s->id_supplier)->findOrFail($branchId);
        SupSupplierBranch::where('supplier_id', $s->id_supplier)->update(['is_default' => false]);
        $b->update(['is_default' => true]);
    }

    public function saveContact(SupSupplier $s, ?int $contactId, array $in, SecUser $user): SupSupplierContact
    {
        $in['is_default'] = ! empty($in['is_default']);
        $d = Validator::make($in, [
            'contact_name' => ['required', 'string', 'max:200'],
            'position' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'is_default' => ['boolean'],
            'note' => ['nullable', 'string'],
        ], [], ['contact_name' => 'Contact name'])->validate();
        $payload = ['supplier_id' => $s->id_supplier, 'contact_name' => $d['contact_name'], 'position' => (string) ($d['position'] ?? ''), 'phone' => (string) ($d['phone'] ?? ''),
            'email' => (string) ($d['email'] ?? ''), 'is_default' => $d['is_default'], 'note' => (string) ($d['note'] ?? ''), 'updated_by' => $user->login];

        if ($contactId) {
            $k = SupSupplierContact::where('supplier_id', $s->id_supplier)->findOrFail($contactId);
            $k->update($payload);
        } else {
            $k = SupSupplierContact::create($payload + ['created_by' => $user->login]);
        }
        if ($d['is_default']) {
            SupSupplierContact::where('supplier_id', $s->id_supplier)->whereKeyNot($k->getKey())->update(['is_default' => false]);
        }

        return $k;
    }

    public function deleteContact(SupSupplier $s, int $contactId): void
    {
        SupSupplierContact::where('supplier_id', $s->id_supplier)->whereKey($contactId)->delete();
    }

    public function setDefaultContact(SupSupplier $s, int $contactId): void
    {
        $k = SupSupplierContact::where('supplier_id', $s->id_supplier)->findOrFail($contactId);
        SupSupplierContact::where('supplier_id', $s->id_supplier)->update(['is_default' => false]);
        $k->update(['is_default' => true]);
    }

    /** Nilai pencarian wilayah "LEVEL:id" -> id dan level (null bila kosong / tidak sah). */
    public function area(?string $value): ?array
    {
        return $value ? Lookups::geoFind($value) : null;
    }

    public function areaValue(?int $id, ?string $level): string
    {
        return $id && $level ? $level.':'.$id : '';
    }

    /** @return array<string, string> */
    public function categoryOptions(): array
    {
        return SupCategory::where('activate', true)->orderBy('category_code')->pluck('category_code', 'category_code')->all();
    }

    /** @return array<string, string> */
    public function paymentTermOptions(): array
    {
        return FinPaymentTerm::where('activate', true)->orderBy('sort_order')->pluck('term_label', 'payment_term_code')->all();
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(SupSupplier $s): array
    {
        $rows = [['at' => $s->created_at?->toDateTimeString(), 'by' => $s->created_by, 'action' => 'Created', 'note' => '']];
        if ($s->updated_at && $s->updated_at != $s->created_at) {
            $rows[] = ['at' => $s->updated_at->toDateTimeString(), 'by' => $s->updated_by, 'action' => 'Last modified', 'note' => ''];
        }

        return array_reverse($rows);
    }
}
