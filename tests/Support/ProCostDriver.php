<?php

namespace Tests\Support;

use App\Models\Sdi\IqpCosting;
use App\Services\Costing\CostingEntryService as Entry;
use PHPUnit\Framework\Assert;

// Pengemudi tes untuk aturan ProCost Mode Integrasi (CostingEntryService), meniru cara tes lama memanggil komponen v1
// pages::inquiry.costing (call('updatePart', ...), assertSet('engineError', ''), ...). Menyimpan galat engine, pemberitahuan
// pemulihan isian, dan pesan Book seperti properti komponen v1.
final class ProCostDriver
{
    public string $engineError = '';

    public array $notice = [];

    public string $bookMsg = '';

    public IqpCosting $costing;

    private function __construct(public int $inquiryId)
    {
        $this->costing = Entry::open($inquiryId);
    }

    public static function open(int $inquiryId): self
    {
        return new self($inquiryId);
    }

    public function call(string $method, mixed ...$a): self
    {
        $c = $this->costing->refresh();
        match ($method) {
            'addPart' => Entry::addPart($c, ...($a ?: ['DC'])),
            'addPartFromInquiry' => $this->engineError = Entry::addPartFromInquiry($c, ...$a),
            'updatePart' => $this->notice = Entry::updatePart($c, ...$a) ?? [],
            'addCoating' => Entry::addCoating($c, ...$a),
            'updateCoating' => Entry::updateCoating($c, ...$a),
            'updateTier' => $this->notice = Entry::updateTier($c, ...$a, ...(count($a) === 3 ? [$c->parts()->first()?->part_type_code] : [])) ?? [],
            'updateHeader' => Entry::updateHeader($c, ...$a),
            'hitung' => $this->engineError = Entry::hitung($c),
            'acceptZero' => $this->acceptZero(),
            'book' => $this->bookMsg = Entry::book($c),
            'releaseBook' => Entry::releaseBook($c),
            default => Assert::fail("Unknown driver method $method"),
        };

        return $this;
    }

    private function acceptZero(): void
    {
        Entry::acceptZero($this->costing->refresh(), $this->notice);
        $this->notice = [];
    }

    public function assertSet(string $prop, mixed $value): self
    {
        Assert::assertSame($value, str_contains($prop, '.') ? data_get($this->{explode('.', $prop)[0]}, explode('.', $prop, 2)[1]) : $this->{$prop});

        return $this;
    }

    public function get(string $prop): mixed
    {
        return $this->{$prop};
    }
}
