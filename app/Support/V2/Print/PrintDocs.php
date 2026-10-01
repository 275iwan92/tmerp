<?php

namespace App\Support\V2\Print;

use App\Models\Master\OrgCompany;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysConstant;
use App\Support\Branding\Brand;
use App\Support\V2\Data\Concerns\FlowDocument;
use Devlib\PrintDoc\Document;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

// Daftar cetakan dokumen transaksi v2 (user 2026-10-01: semua entry transaksional punya cetakan A5 landscape / A4 portrait).
// Satu jenis = satu kelas turunan Devlib\PrintDoc\Document yang membaca dokumennya dari database (load) -- tata letak
// seluruhnya milik objek DevLib. Halaman: route v2.print (resources/views/v2/print-doc.blade.php).
final class PrintDocs
{
    use FlowDocument;

    // jenis => [kelas, slug layar asal (tombol Back + hak akses modulnya), kode konstanta penanda tangan]
    public const MAP = [
        'inquiry' => [InquiryPrint::class, 'inquiry-new', 'INQ'],
        'quotation' => [QuotationPrint::class, 'quotation', 'QUO'],
        'customer-order' => [CustomerOrderPrint::class, 'customer-order', 'CO'],
        'cpdel' => [CpdelPrint::class, 'cpdel', 'CPDEL'],
        'delivery-order' => [DeliveryOrderPrint::class, 'delivery-order', 'DO'],
        'delivery-note' => [DeliveryNotePrint::class, 'delivery-notes', 'DN'],
        'proof-of-delivery' => [ProofOfDeliveryPrint::class, 'proof-of-delivery', 'POD'],
        'purchase-requisition' => [PurchaseRequisitionPrint::class, 'purchase-requisition', 'PR'],
        'purchase-quotation' => [PurchaseQuotationPrint::class, 'purchase-quotation', 'PQ'],
        'purchase-order' => [PurchaseOrderPrint::class, 'purchase-order', 'PO'],
        'goods-receipt' => [GoodsReceiptPrint::class, 'purchase-receive', 'SPB'],
        'material-booking' => [MaterialBookingPrint::class, 'material-booking', 'MB'],
        'maintenance-request' => [MaintenanceRequestPrint::class, 'maintenance-request', 'MR'],
        'maintenance-wo' => [MaintenanceWoPrint::class, 'maintenance-wo', 'MWO'],
        'petty-cash' => [PettyCashPrint::class, 'petty-cash', 'PC'],
    ];

    // Dokumen yang tombol Back-nya kembali ke daftar (id cetak bukan id form layarnya).
    public const BACK_TO_LIST = ['cpdel', 'petty-cash'];

    private static bool $booted = false;

    private static array $people = [];

    public static function url(string $type, int|string|null $id, array $query = []): string
    {
        return route('v2.print', ['type' => $type, 'id' => $id] + $query);
    }

    // $opt: prices (bool, hanya dokumen yang punya dua format), printedBy (nama; bawaan = user yang login).
    public static function make(string $type, int $id, array $opt = []): Document
    {
        abort_unless(isset(self::MAP[$type]), 404);
        self::boot();
        $class = self::MAP[$type][0];

        return $class::load($id, $opt + ['printedBy' => self::me()]);
    }

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        Document::labels(Labels::ID);
        Document::companyUsing(fn (bool $ppn) => self::company($ppn));
        Document::signersUsing(fn (string $type) => self::signers($type));
    }

    // Kop: dokumen Non PPN = Telasih Makmur tanpa NPWP; dokumen PPN = badan hukum + NPWP (user 2026-09-23 / 2026-10-01).
    // Alamat sama untuk keduanya (tm2mst.org_company), diisi di Settings > Identification.
    public static function company(bool $ppn): array
    {
        $c = OrgCompany::where('company_code', 'TM')->first();
        $name = $ppn ? Brand::companyLegalName() : Brand::companyName();
        $words = preg_split('/[\s.]+/', trim(preg_replace('/^(CV|PT)\.?\s*/i', '', $name)), -1, PREG_SPLIT_NO_EMPTY);
        $contact = implode(' · ', array_filter([$c?->phone ? Document::t('Phone').' '.$c->phone : null, $c?->email]));

        return ['name' => $name, 'initials' => mb_strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), $words))),
            'lines' => array_values(array_filter([$ppn ? null : Brand::tagline(), $c?->address, $contact])), 'npwp' => $ppn ? $c?->npwp_number : null];
    }

    // Penanda tangan per dokumen = konstanta (user 2026-10-01): SIGN_<KODE>_APPROVER / SIGN_<KODE>_ACKNOWLEDGER; kosong = garis kosong.
    public static function signers(string $type): array
    {
        $code = self::MAP[$type][2] ?? null;

        return $code ? ['approve' => SysConstant::text("SIGN_{$code}_APPROVER"), 'know' => SysConstant::text("SIGN_{$code}_ACKNOWLEDGER")] : [];
    }

    // Pembuat = selalu user yang login (nama orangnya, bukan login) -- juga nama di kaki dokumen.
    public static function me(): ?string
    {
        $u = Auth::user();

        return $u ? (trim((string) $u->name) ?: $u->login) : null;
    }

    public static function person(?string $login): ?string
    {
        if (! $login) {
            return null;
        }

        return self::$people[$login] ??= (trim((string) SecUser::where('login', $login)->value('name')) ?: $login);
    }

    // Bila dokumen disetujui lewat sistem: nama penyetuju terakhir yang sebenarnya (mengalahkan konstanta) + "Level n / N".
    // tmerp.test (tm2mst) belum punya skema Setting Approval v2 (flow_config_step.activate, migrasi fitur lain yang masih
    // pending di sana): cetakan tetap jalan, penanda tangan jatuh ke konstanta.
    public static function approval(string $module, int $docId, float $amount): array
    {
        try {
            $state = self::approvalState($module, $docId, $amount);
        } catch (QueryException) {
            return ['by' => null, 'level' => null];
        }
        $done = collect($state['steps'])->where('status', 'done');

        return ['by' => self::person($done->last()['by'] ?? null),
            'level' => $state['levels'] && $state['submitted_by'] ? $done->count().' / '.$state['levels'] : null];
    }

    // Baris alamat blok kanan (maksimal 3 baris): tiap bagian dilipat di batas kata supaya alamat panjang tidak terpotong.
    public static function address(?string ...$parts): array
    {
        $lines = [];
        foreach ($parts as $p) {
            $p = trim((string) preg_replace('/\s+/', ' ', (string) $p));
            if ($p !== '') {
                array_push($lines, ...explode(PHP_EOL, wordwrap($p, 40, PHP_EOL, true)));
            }
        }

        return array_slice($lines, 0, 3);
    }
}
