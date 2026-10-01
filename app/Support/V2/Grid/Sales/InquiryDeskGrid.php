<?php

namespace App\Support\V2\Grid\Sales;

use App\Models\Master\CustCustomer;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use App\Support\V2\Data\Sales;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Satu tabel iqp_inquiry, satu grid per meja x jenis (user 2026-10-02, rulebook A.20): tiap bagian melihat baris yang sama
// dengan saringan (jenis AND stat_confirm_*) berbeda. Turunan hanya menyebut jenis + meja; kolom, saringan, dan baris anak
// (daftar part) sama untuk semua. Isi = App\Support\V2\Data\Sales::desk().
abstract class InquiryDeskGrid extends DbGrid
{
    protected const TYPE_LABELS = ['NEW' => 'New', 'REVISE' => 'Revise', 'RECALC' => 'Recalc'];

    abstract protected function type(): string;

    // SALES | PRODEV | PROCOST
    abstract protected function desk(): string;

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function moduleLabel(): string
    {
        return 'Sales';
    }

    public function group(): string
    {
        return ['SALES' => 'Inquiry Product', 'PRODEV' => 'Pro-Dev', 'PROCOST' => 'Pro-Cost'][$this->desk()];
    }

    public function search(): string
    {
        return 'Search inquiry no., product, customer...';
    }

    protected function query(): Builder
    {
        return IqpInquiry::query();
    }

    protected function models(): array
    {
        return [IqpInquiry::class, IqpPart::class, CustCustomer::class];
    }

    protected function row(Model $m): array
    {
        return [];
    }

    // Alamat buka satu baris (tiap meja membuka layarnya sendiri).
    abstract protected function rowUrl(array $r): string;

    public function rows(): array
    {
        $done = $this->desk() === 'PRODEV' ? 'stat_confirm_prodev' : 'stat_confirm_procost';

        return array_map(fn ($r) => $r + [
            'open_url' => $this->rowUrl($r),
            'todo' => $this->desk() === 'SALES' ? null : ($r[$done] ? 'Done' : 'To Do'),
            'received' => $this->desk() === 'PROCOST' && $this->type() !== 'RECALC' ? $r['prodev_at'] : $r['sales_at'],
        ], Sales::desk($this->type(), $this->desk()));
    }

    public function openUrl(): ?string
    {
        return '{open_url}';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    public function columns(): array
    {
        $doc = ['key' => 'inquiry_no', 'label' => 'Inquiry No. / Customer', 'type' => 'doc', 'party' => 'customer'];
        $product = ['key' => 'product_draft_name', 'label' => 'Product'];
        $qty = ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'];
        $pic = ['key' => 'pic_sales', 'label' => 'Sales PIC'];
        $todo = ['key' => 'todo', 'label' => 'Work', 'type' => 'badge', 'tones' => ['To Do' => 'warn', 'Done' => 'success']];

        return match ($this->desk()) {
            'PRODEV' => [$doc, ['key' => 'received', 'label' => 'Confirmed by Sales', 'type' => 'date'], $product, ['key' => 'part_type', 'label' => 'Product Type'], $qty, $pic,
                $todo, ['key' => 'prodev_at', 'label' => 'Confirmed by Pro-Dev', 'type' => 'date']],
            'PROCOST' => [$doc, ['key' => 'received', 'label' => 'Received', 'type' => 'date'], $product, $qty,
                ['key' => 'costing', 'label' => 'Costing', 'type' => 'badge', 'tones' => ['NOT STARTED' => 'neutral', 'DRAFT' => 'warn', 'CALCULATED' => 'info', 'PENDING_APPROVAL' => 'info', 'PUBLISHED' => 'success']],
                $todo, ['key' => 'procost_at', 'label' => 'Published', 'type' => 'date'], $pic + ['visible' => false]],
            default => [$doc, ['key' => 'inquiry_date', 'label' => 'Date', 'type' => 'date'], $product, ['key' => 'part_type', 'label' => 'Product Type'], $qty,
                ['key' => 'stage', 'label' => 'Stage', 'type' => 'badge', 'tones' => Sales::STAGE_TONES],
                ['key' => 'deadline_quotation', 'label' => 'Quotation Deadline', 'type' => 'date'], $pic + ['visible' => false]],
        };
    }

    public function detail(): array
    {
        return Sales::PART_DETAIL;
    }

    // Bawaan = To Do: yang harus dikerjakan meja ini. Done = yang sudah lewat dari meja ini.
    public function stats(): array
    {
        if ($this->desk() !== 'SALES') {
            return [['label' => 'All'], ['label' => 'To Do', 'where' => ['todo' => 'To Do'], 'tone' => 'warn', 'default' => true], ['label' => 'Done', 'where' => ['todo' => 'Done'], 'tone' => 'success']];
        }

        return array_values(array_filter([
            ['label' => 'All'],
            ['label' => 'Draft', 'where' => ['stage' => 'Draft'], 'tone' => 'neutral'],
            $this->type() === 'RECALC' ? null : ['label' => 'Pro-Dev', 'where' => ['stage' => 'Pro-Dev'], 'tone' => 'violet'],
            ['label' => 'Pro-Cost', 'where' => ['stage' => 'Pro-Cost'], 'tone' => 'warn'],
            ['label' => 'Ready to Quote', 'where' => ['stage' => 'Ready to Quote'], 'tone' => 'success'],
        ]));
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'pic_sales' => 'Sales PIC'];
    }
}
