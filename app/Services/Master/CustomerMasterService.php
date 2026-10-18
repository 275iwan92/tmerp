<?php

/*
 * REMARK
 * Tujuan       : Master Customer dari layar v2 md-customer-form: simpan customer (identitas, spek cetak, penjualan dan keuangan, alamat + PIC, governance), aktif / nonaktif, Block / Unblock, kontak dan cabang tambahan.
 * Sumber hulu  : Aturan dipindah apa adanya dari halaman v1 pages::master.customer (diarsipkan di tmerp_unused/master_customer_v1_2026-10-18.blade.php): kode unik, batas panjang, daftar nilai (tipe, status persetujuan, profil pajak, rating, tier, KYC), satu default per cabang / kontak, Unblock hanya admin atau akses modul Finance dengan alasan.
 * Input        : Isian form (header), area dari pencarian wilayah (LEVEL:id), isian dialog kontak / cabang / unblock, user.
 * Output       : tm2mst.cust_customer, cust_customer_contact, cust_customer_branch berubah; ValidationException bila aturan dilanggar.
 * Dipakai oleh : routes/v2_master.php (v2.md.customer.*), layar md-customer-form, tes CustomerMasterServiceTest.
 * Status       : Aktif. Saldo piutang (current_balance) hanya ditampilkan. Customer baru: kontak dan cabang tambahan baru bisa ditambah sesudah Save pertama.
 */

namespace App\Services\Master;

use App\Models\Master\CustCategory;
use App\Models\Master\CustCustomer;
use App\Models\Master\CustCustomerBranch;
use App\Models\Master\CustCustomerContact;
use App\Models\Master\FinPaymentTerm;
use App\Models\Master\OrgDivision;
use App\Models\Sys\SecUser;
use App\Support\Finance\TaxRate;
use App\Support\V2\Lookups;
use Devlib\Locale;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CustomerMasterService
{
    public const TYPES = ['local' => 'Local', 'import' => 'Import'];

    public const KYC = ['PENDING' => 'Pending verification', 'APPROVED' => 'Verified', 'REJECTED' => 'Rejected'];

    /** @var array<int, string> */
    private const FLAGS = ['is_prospect', 'credit_check_enabled', 'tax_validated'];

    public function save(?CustCustomer $c, array $in, SecUser $user): CustCustomer
    {
        foreach (self::FLAGS as $f) {
            $in[$f] = ! empty($in[$f]);
        }
        $in['credit_limit'] = Locale::input($in['credit_limit'] ?? null) ?? ($in['credit_limit'] ?? null);
        $area = $this->area($in['default_area'] ?? null);
        if (array_key_exists('ship_postal_code', $in)) {
            $in['postal_code'] = $in['ship_postal_code'];
        }

        $d = Validator::make($in, [
            'customer_code' => ['required', 'string', 'max:50', 'unique:tm2mst.cust_customer,customer_code'.($c ? ','.$c->id_customer.',id_customer' : '')],
            'customer_name' => ['required', 'string', 'max:200'],
            'trade_name' => ['nullable', 'string', 'max:200'],
            'parent_group' => ['nullable', 'string', 'max:200'],
            'industry_category' => ['nullable', 'string', 'max:150'],
            'tax_code' => ['nullable', 'string', 'max:50'],
            'customer_type' => ['required', 'in:local,import'],
            'category' => ['nullable', 'string', 'exists:tm2mst.cust_category,category_code'],
            'is_prospect' => ['boolean'],
            'approval_status' => ['required', 'in:'.implode(',', array_keys(CustCustomer::APPROVAL_STATUSES))],
            'note' => ['nullable', 'string'],
            'primary_product' => ['nullable', 'string', 'max:150'],
            'secondary_product' => ['nullable', 'string', 'max:150'],
            'default_substrate' => ['nullable', 'string', 'max:150'],
            'color_standard' => ['nullable', 'string', 'max:150'],
            'proofing_method' => ['nullable', 'string', 'max:150'],
            'artwork_approval' => ['nullable', 'string', 'max:150'],
            'retained_sample' => ['nullable', 'string', 'max:150'],
            'barcode_verification' => ['nullable', 'string', 'max:150'],
            'finishing_preference' => ['nullable', 'string', 'max:200'],
            'die_owner' => ['nullable', 'string', 'max:150'],
            'packing_instruction' => ['nullable', 'string', 'max:200'],
            'quality_notes' => ['nullable', 'string'],
            'sales_division_id' => ['nullable', 'integer', 'exists:tm2mst.org_division,division_id'],
            'price_list_code' => ['nullable', 'string', 'max:50'],
            'tax_profile' => ['required', 'in:PPN_11,EXEMPT'],
            'payment_term' => ['nullable', 'string', 'exists:tm2mst.fin_payment_term,payment_term_code'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'credit_check_enabled' => ['boolean'],
            'credit_rating' => ['required', 'in:A,B,C'],
            'customer_tier' => ['required', 'integer', 'in:1,2,3'],
            'default_address' => ['nullable', 'string'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'mr_ms' => ['nullable', 'string', 'max:5'],
            'contact_name' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:150'],
            'tax_validated' => ['boolean'],
            'kyc_status' => ['required', 'in:'.implode(',', array_keys(self::KYC))],
        ], [], ['customer_code' => 'Customer code', 'customer_name' => 'Customer name', 'customer_type' => 'Type', 'credit_limit' => 'Credit limit'])->validate();

        $data = array_map(fn ($v) => $v === '' ? null : $v, $d) + ['default_area_id' => $area['id'] ?? null, 'default_area_level' => $area['level'] ?? null, 'updated_by' => $user->login];
        // Kolom wajib teks tetap string kosong seperti v1 (bukan NULL) untuk isian yang tidak diisi.
        foreach (['trade_name', 'parent_group', 'industry_category', 'tax_code', 'note', 'primary_product', 'secondary_product', 'default_substrate', 'color_standard', 'proofing_method', 'artwork_approval',
            'retained_sample', 'barcode_verification', 'finishing_preference', 'die_owner', 'packing_instruction', 'quality_notes', 'price_list_code', 'mr_ms', 'contact_name', 'default_address', 'postal_code',
            'email', 'phone', 'website'] as $text) {
            $data[$text] ??= '';
        }
        $data['credit_limit'] ??= 0;

        if ($c) {
            $c->update($data);

            return $c;
        }

        return CustCustomer::create($data + ['activate' => true, 'is_blacklisted' => false, 'created_by' => $user->login]);
    }

    public function setActive(CustCustomer $c, bool $active, SecUser $user): void
    {
        $c->update(['activate' => $active, 'updated_by' => $user->login]);
    }

    /** Blokir = aksi langsung. */
    public function block(CustCustomer $c, SecUser $user): void
    {
        $c->update(['is_blacklisted' => true, 'approval_status' => 'BLOCKED', 'updated_by' => $user->login]);
    }

    public function canUnblock(SecUser $user): bool
    {
        return (bool) ($user->is_admin || $user->canAccessModule('FINACC'));
    }

    /** Buka blokir: admin atau akses modul Finance, alasan wajib; alasan dicatat di catatan customer. */
    public function unblock(CustCustomer $c, string $reason, SecUser $user): void
    {
        if (! $this->canUnblock($user)) {
            throw ValidationException::withMessages(['unblock_reason' => 'You are not authorised to unblock: it requires access to the Finance & Accounting module or Admin.']);
        }
        $d = Validator::make(['unblock_reason' => $reason], ['unblock_reason' => ['required', 'string', 'max:255']], [], ['unblock_reason' => 'Unblock reason'])->validate();
        $stamp = '[Unblock '.now()->format('d/m/Y H:i').' by '.$user->login.'] '.$d['unblock_reason'];
        $c->update(['is_blacklisted' => false, 'approval_status' => 'APPROVED', 'note' => trim(($c->note ? $c->note."\n" : '').$stamp), 'updated_by' => $user->login]);
    }

    public function saveBranch(CustCustomer $c, ?int $branchId, array $in, SecUser $user): CustCustomerBranch
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
        $payload = ['customer_id' => $c->id_customer, 'branch_name' => (string) ($d['branch_name'] ?? ''), 'address' => (string) ($d['address'] ?? ''), 'area_id' => $area['id'] ?? null,
            'area_level' => $area['level'] ?? null, 'postal_code' => (string) ($d['ship_postal_code'] ?? ''), 'phone' => (string) ($d['phone'] ?? ''), 'is_default' => $d['is_default'], 'updated_by' => $user->login];

        if ($branchId) {
            $b = CustCustomerBranch::where('customer_id', $c->id_customer)->findOrFail($branchId);
            $b->update($payload);
        } else {
            $b = CustCustomerBranch::create($payload + ['created_by' => $user->login]);
        }
        if ($d['is_default']) {
            CustCustomerBranch::where('customer_id', $c->id_customer)->whereKeyNot($b->getKey())->update(['is_default' => false]);
        }

        return $b;
    }

    public function deleteBranch(CustCustomer $c, int $branchId): void
    {
        CustCustomerBranch::where('customer_id', $c->id_customer)->whereKey($branchId)->delete();
    }

    public function setDefaultBranch(CustCustomer $c, int $branchId): void
    {
        $b = CustCustomerBranch::where('customer_id', $c->id_customer)->findOrFail($branchId);
        CustCustomerBranch::where('customer_id', $c->id_customer)->update(['is_default' => false]);
        $b->update(['is_default' => true]);
    }

    public function saveContact(CustCustomer $c, ?int $contactId, array $in, SecUser $user): CustCustomerContact
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
        $payload = ['customer_id' => $c->id_customer, 'contact_name' => $d['contact_name'], 'position' => (string) ($d['position'] ?? ''), 'phone' => (string) ($d['phone'] ?? ''),
            'email' => (string) ($d['email'] ?? ''), 'is_default' => $d['is_default'], 'note' => (string) ($d['note'] ?? ''), 'updated_by' => $user->login];

        if ($contactId) {
            $k = CustCustomerContact::where('customer_id', $c->id_customer)->findOrFail($contactId);
            $k->update($payload);
        } else {
            $k = CustCustomerContact::create($payload + ['created_by' => $user->login]);
        }
        if ($d['is_default']) {
            CustCustomerContact::where('customer_id', $c->id_customer)->whereKeyNot($k->getKey())->update(['is_default' => false]);
        }

        return $k;
    }

    public function deleteContact(CustCustomer $c, int $contactId): void
    {
        CustCustomerContact::where('customer_id', $c->id_customer)->whereKey($contactId)->delete();
    }

    public function setDefaultContact(CustCustomer $c, int $contactId): void
    {
        $k = CustCustomerContact::where('customer_id', $c->id_customer)->findOrFail($contactId);
        CustCustomerContact::where('customer_id', $c->id_customer)->update(['is_default' => false]);
        $k->update(['is_default' => true]);
    }

    /** Nilai pencarian wilayah "LEVEL:id" -> id dan level (null bila kosong / tidak sah). */
    public function area(?string $value): ?array
    {
        return $value ? Lookups::geoFind($value) : null;
    }

    /** Nilai pencarian wilayah dari customer / cabang yang tersimpan. */
    public function areaValue(?int $id, ?string $level): string
    {
        return $id && $level ? $level.':'.$id : '';
    }

    /** @return array<string, string> */
    public function typeOptions(): array
    {
        return self::TYPES;
    }

    /** @return array<string, string> kode => label: PPN dari Tax Rate */
    public function taxProfileOptions(): array
    {
        return ['PPN_11' => 'PPN '.Locale::number(TaxRate::ppn(), 0).'% - Domestic Taxable', 'EXEMPT' => 'VAT Exempt (Bonded Zone)'];
    }

    /** @return array<string, string> */
    public function categoryOptions(): array
    {
        return CustCategory::where('activate', true)->orderBy('category_code')->pluck('category_code', 'category_code')->all();
    }

    /** @return array<int, string> */
    public function divisionOptions(): array
    {
        return OrgDivision::where('activate', true)->orderBy('division_name')->pluck('division_name', 'division_id')->all();
    }

    /** @return array<string, string> */
    public function paymentTermOptions(): array
    {
        return FinPaymentTerm::where('activate', true)->orderBy('sort_order')->pluck('term_label', 'payment_term_code')->all();
    }

    /** @return array<string, string> */
    public function approvalOptions(): array
    {
        return CustCustomer::APPROVAL_STATUSES;
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(CustCustomer $c): array
    {
        $rows = [['at' => $c->created_at?->toDateTimeString(), 'by' => $c->created_by, 'action' => 'Created', 'note' => '']];
        if ($c->updated_at && $c->updated_at != $c->created_at) {
            $rows[] = ['at' => $c->updated_at->toDateTimeString(), 'by' => $c->updated_by, 'action' => 'Last modified', 'note' => ''];
        }

        return array_reverse($rows);
    }
}
