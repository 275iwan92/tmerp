<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysDocSeries;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;

// Document Number Series (tm2sys.sys_doc_series): format nomor dokumen sebagai data. Nomor terbit saat Confirm lewat
// App\Support\Docs\DocSeries; perubahan format berlaku untuk nomor berikutnya saja. Kode seri dirujuk program (INV_TM,
// INV_LJM, DP_TM, DP_LJM), jadi tidak bisa diganti setelah tersimpan.
class DocSeriesLookup extends DbLookup
{
    public function slug(): string
    {
        return 'setup-doc-series';
    }

    public function title(): string
    {
        return 'Document Number Series';
    }

    public function icon(): string
    {
        return 'fa-solid fa-list-ol';
    }

    public function module(): string
    {
        return 'SETUP';
    }

    public function moduleLabel(): string
    {
        return 'Setup';
    }

    public function guide(): ?string
    {
        // Paling banyak 255 huruf (batas kamus terjemahan).
        return 'Number format of Customer Invoice and Down Payment Invoice. Tokens: {SEQ} running number, {SEQ:5} padded to 5 digits, {ROMAN} Roman month, {MM} month, '
            .'{YY} / {YYYY} year. The number is issued on Confirm and never reused.';
    }

    protected function query(): Builder
    {
        return SysDocSeries::query()->orderBy('series_code');
    }

    public function columns(): array
    {
        return [
            ['key' => 'series_code', 'label' => 'Code', 'required' => true, 'unique' => true, 'lockSaved' => true, 'case' => 'uppercase', 'width' => '140px'],
            ['key' => 'series_name', 'label' => 'Series Name', 'required' => true, 'width' => '380px'],
            ['key' => 'number_format', 'label' => 'Number Format', 'required' => true, 'width' => '260px'],
            ['key' => 'reset_period', 'label' => 'Restart', 'type' => 'select', 'options' => SysDocSeries::RESETS, 'required' => true, 'width' => '150px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function prepare(array $in): array
    {
        if (isset($in['series_code']) && is_string($in['series_code'])) {
            $in['series_code'] = strtoupper(trim($in['series_code']));
        }

        return $in;
    }

    protected function rules(): array
    {
        return ['series_code' => ['string', 'max:30', 'regex:/^[A-Z0-9_]+$/'], 'series_name' => ['string', 'max:100'],
            'number_format' => ['string', 'max:60', 'regex:/\{SEQ(:\d+)?\}/'], 'reset_period' => ['in:'.implode(',', array_keys(SysDocSeries::RESETS))]];
    }

    // Format harus memuat tanda periode yang cocok dengan Restart, kalau tidak nomor yang sama akan terbit dua kali.
    protected function check(array $in, ?int $id): ?string
    {
        $format = (string) ($in['number_format'] ?? '');
        $year = str_contains($format, '{YY}') || str_contains($format, '{YYYY}');
        $month = str_contains($format, '{ROMAN}') || str_contains($format, '{MM}');

        return match ($in['reset_period'] ?? '') {
            'MONTH' => $year && $month ? null : 'A format that restarts every month must contain the month ({ROMAN} or {MM}) and the year ({YY} or {YYYY}).',
            'YEAR' => $year ? null : 'A format that restarts every year must contain the year ({YY} or {YYYY}).',
            default => null,
        };
    }
}
