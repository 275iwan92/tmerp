<?php

namespace App\Support;

// Tooltip "nanti tersambung ke mana" untuk tiap isian/kolom mockup, dicek ke Data Dictionary:
//   Src::of('tm2sdi.iqp_inquiry.inquiry_no')  -> "tm2sdi (Sales) › iqp_inquiry › inquiry_no · varchar(20) — IQ[YY]..."
//   Src::of('tm2sdi.iqp_inquiry.customer_id', 'tm2mst.cust_customer.customer_name') -> dua sumber dipisah " + "
// Kolom yang BELUM ada di database diberi tanda ⚠ dan dicatat (Src::missing()) supaya tidak lolos diam-diam.
final class Src
{
    public const DB = ['tm2mst' => 'Master', 'tm2sdi' => 'Sales', 'tm2pur' => 'Procurement', 'tm2inv' => 'Inventory', 'tm2prd' => 'Production',
        'tm2acc' => 'Finance', 'tm2hrm' => 'HRM', 'tm2hrr' => 'Recruitment', 'tm2pyr' => 'Payroll', 'tm2sys' => 'Sistem', 'tm2old' => 'Legacy', 'tm2crp' => 'Portal'];

    private static array $missing = [];

    public static function of(string ...$refs): string
    {
        return implode(' + ', array_map(self::one(...), $refs));
    }

    // Nilai hitungan/tampilan saja (tidak disimpan): sebutkan asalnya.
    public static function calc(string $how, string ...$refs): string
    {
        return 'dihitung: '.$how.($refs ? ' ← '.self::of(...$refs) : '');
    }

    public static function missing(): array
    {
        return array_keys(self::$missing);
    }

    private static function one(string $ref): string
    {
        [$db, $table, $col] = array_pad(explode('.', $ref, 3), 3, null);
        $head = $db.' ('.(self::DB[$db] ?? '?').') › '.$table.($col ? ' › '.$col : '');
        $info = Schema::find($ref);
        if (! $info) {
            self::$missing[$ref] = true;

            return '⚠ BELUM ADA DI DB: '.$head;
        }
        if ($col === null) {
            return $head;
        }

        return $head.' · '.$info['type'].($info['fk'] ? ' → '.$info['fk'] : '').($info['comment'] ? ' — '.$info['comment'] : '');
    }
}
