<?php

/* REMARK
 * Tujuan       : Setup > Localization (teks UI untuk halaman login / lupa password): satu baris per kunci terjemahan, satu kolom per bahasa; isi sel langsung lalu Save. Kunci baru tidak bisa ditambah dari sini, kolom bahasa baru muncul otomatis dari layar Languages.
 * Sumber hulu  : sys_translations (tm2sys: lang_code, trans_key, trans_value) dan sys_languages; pindahan layar v1 pages::setup.localization.
 * Input        : sel yang berubah (trans_key + kolom lang_<kode bahasa>).
 * Output       : sys_translations: updateOrCreate per (lang_code, trans_key); nilai sel kosong ikut tersimpan seperti v1.
 * Dipakai oleh : layar v2 localization (rute v2.screen; item menu SET_LOCALIZATION tetap nonaktif seperti v1), SysTranslation::forLang (login / forgot-password).
 * Status       : Aktif (T9 pindahan v1, 2026-10-20).
 */

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysLanguage;
use App\Models\Sys\SysTranslation;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LocalizationLookup extends DbLookup
{
    protected int $limit = 5000;

    public function slug(): string
    {
        return 'localization';
    }

    public function title(): string
    {
        return 'Localization';
    }

    public function module(): string
    {
        return 'SETUP';
    }

    public function moduleLabel(): string
    {
        return 'Setup';
    }

    public function group(): string
    {
        return 'Language and Localization';
    }

    public function icon(): string
    {
        return 'fa-solid fa-language';
    }

    public function guide(): ?string
    {
        return 'Add a new language in the Language screen: its column appears here automatically, empty, ready to fill.';
    }

    protected function addRows(): bool
    {
        return false;
    }

    /** @return array<string, string> kode => nama, default dulu lalu urut kode (sama dengan v1) */
    private function languages(): array
    {
        return SysLanguage::query()->orderByDesc('is_default')->orderBy('code')->get()->mapWithKeys(fn ($l) => [$l->code => $l->name.' ('.$l->code.')'])->all();
    }

    protected function query(): Builder
    {
        return SysTranslation::query()->orderBy('trans_key');
    }

    public function columns(): array
    {
        $cols = [['key' => 'trans_key', 'label' => 'Key', 'readonly' => true, 'width' => '200px']];
        foreach ($this->languages() as $code => $label) {
            $cols[] = ['key' => 'lang_'.$code, 'label' => $label, 'width' => '190px'];  // awalan: kode 'id' bentrok dengan kunci baris
        }

        return $cols;
    }

    /** Satu baris per kunci, satu kolom per bahasa (isi kosong bila belum diterjemahkan). */
    public function rows(): array
    {
        $codes = array_keys($this->languages());
        $flat = SysTranslation::query()->get(['lang_code', 'trans_key', 'trans_value'])->groupBy('trans_key');

        // id = nomor urut (sheet menganggap id bukan angka = baris baru); kunci sebenarnya ada di kolom trans_key.
        $n = 0;

        return $flat->sortKeys()->map(function ($items, $key) use ($codes, &$n) {
            $row = ['id' => ++$n, 'trans_key' => $key];
            foreach ($codes as $code) {
                $row['lang_'.$code] = (string) ($items->firstWhere('lang_code', $code)?->trans_value ?? '');
            }

            return $row;
        })->values()->all();
    }

    protected function row(Model $m): array
    {
        return [];
    }

    /** Simpan sel yang berubah; kembalian = isi terbaru. Seperti v1: nilai apa pun (juga kosong) ditulis, hanya kode bahasa yang dikenal. */
    public function save(array $changed): array
    {
        $codes = array_keys($this->languages());
        SysTranslation::query()->getConnection()->transaction(function () use ($changed, $codes) {
            foreach ($changed as $in) {
                $key = (string) ($in['trans_key'] ?? '');
                if ($key === '') {
                    continue;
                }
                foreach ($codes as $code) {
                    if (array_key_exists('lang_'.$code, $in) && $in['lang_'.$code] !== null) {
                        SysTranslation::updateOrCreate(['lang_code' => $code, 'trans_key' => $key], ['trans_value' => (string) $in['lang_'.$code]]);
                    }
                }
            }
        });

        return $this->rows();
    }
}
