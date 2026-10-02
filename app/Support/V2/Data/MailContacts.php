<?php

namespace App\Support\V2\Data;

use App\Models\Master\CustCustomer;
use App\Models\Master\CustCustomerContact;
use App\Models\Master\SupSupplier;
use App\Models\Master\SupSupplierContact;
use App\Models\Sys\SecUser;
use Illuminate\Database\Eloquent\Builder;

// Pencarian alamat e-mail untuk dialog Email x-dl::doc-share (backlog09 §18): kontak customer, kontak supplier dan user.
// Hanya baris aktif yang punya e-mail. Nama perusahaan ikut dicari supaya "ketik nama customer" menampilkan semua kontaknya.
class MailContacts
{
    public const PER_SOURCE = 6;

    public const LIMIT = 20;

    /** @return list<array{name: string, email: string, group: string}> */
    public static function search(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
        $hasMail = fn (Builder $b) => $b->whereNotNull('email')->where('email', '<>', '');
        $rows = [];

        $customers = $hasMail(CustCustomer::query())->where('activate', 1)
            ->where(fn ($w) => $w->where('customer_name', 'like', $like)->orWhere('email', 'like', $like))
            ->orderBy('customer_name')->limit(self::PER_SOURCE)->get(['id_customer', 'customer_name', 'email']);
        foreach ($customers as $c) {
            $rows[] = ['name' => $c->customer_name, 'email' => $c->email, 'group' => t('Customer')];
        }

        // Kontak: cocok di nama kontak / e-mail, atau milik customer yang namanya cocok (dua query, bukan join lintas-model).
        $customerIds = CustCustomer::query()->where('customer_name', 'like', $like)->limit(50)->pluck('id_customer');
        $contacts = $hasMail(CustCustomerContact::query())->where('activate', 1)
            ->where(fn ($w) => $w->where('contact_name', 'like', $like)->orWhere('email', 'like', $like)->orWhereIn('customer_id', $customerIds))
            ->orderBy('contact_name')->limit(self::PER_SOURCE)->get(['customer_id', 'contact_name', 'email']);
        $names = CustCustomer::query()->whereIn('id_customer', $contacts->pluck('customer_id')->unique())->pluck('customer_name', 'id_customer');
        foreach ($contacts as $c) {
            $rows[] = ['name' => $c->contact_name, 'email' => $c->email, 'group' => (string) ($names[$c->customer_id] ?? t('Customer'))];
        }

        $suppliers = $hasMail(SupSupplier::query())->where('activate', 1)
            ->where(fn ($w) => $w->where('supplier_name', 'like', $like)->orWhere('email', 'like', $like))
            ->orderBy('supplier_name')->limit(self::PER_SOURCE)->get(['id_supplier', 'supplier_name', 'email']);
        foreach ($suppliers as $s) {
            $rows[] = ['name' => $s->supplier_name, 'email' => $s->email, 'group' => t('Supplier')];
        }

        $supplierIds = SupSupplier::query()->where('supplier_name', 'like', $like)->limit(50)->pluck('id_supplier');
        $contacts = $hasMail(SupSupplierContact::query())->where('activate', 1)
            ->where(fn ($w) => $w->where('contact_name', 'like', $like)->orWhere('email', 'like', $like)->orWhereIn('supplier_id', $supplierIds))
            ->orderBy('contact_name')->limit(self::PER_SOURCE)->get(['supplier_id', 'contact_name', 'email']);
        $names = SupSupplier::query()->whereIn('id_supplier', $contacts->pluck('supplier_id')->unique())->pluck('supplier_name', 'id_supplier');
        foreach ($contacts as $c) {
            $rows[] = ['name' => $c->contact_name, 'email' => $c->email, 'group' => (string) ($names[$c->supplier_id] ?? t('Supplier'))];
        }

        $users = $hasMail(SecUser::query())->where('activate', 1)
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('login', 'like', $like)->orWhere('email', 'like', $like))
            ->orderBy('name')->limit(self::PER_SOURCE)->get(['login', 'name', 'email']);
        foreach ($users as $u) {
            $rows[] = ['name' => $u->name ?: $u->login, 'email' => $u->email, 'group' => t('User')];
        }

        // Satu alamat sekali saja (yang pertama ditemukan menang), dibatasi supaya daftar tetap ringkas.
        $seen = [];
        $out = [];
        foreach ($rows as $r) {
            $key = mb_strtolower(trim((string) $r['email']));
            if ($key === '' || isset($seen[$key]) || ! filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['name' => (string) $r['name'], 'email' => trim((string) $r['email']), 'group' => $r['group']];
            if (count($out) >= self::LIMIT) {
                break;
            }
        }

        return $out;
    }
}
