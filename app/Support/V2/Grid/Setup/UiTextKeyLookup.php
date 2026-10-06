<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysTranslation;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Setup > Language and Localization > UI Text Keys (v2, pengganti layar v1 pages::setup.localization). Kamus kunci v1
// (tm2sys.sys_translations, dibaca App\Support\Localization\Translator dan halaman login / register) masih dipakai layar v1
// yang tersisa dan label menu ber-trans_key, jadi tetap harus bisa diisi (CLAUDE.md §7.12). Satu baris = satu trans_key,
// satu kolom per bahasa; sel kosong = terjemahan bahasa itu dihapus. Teks layar v2 tidak di sini: Translation Dictionary.
class UiTextKeyLookup extends DbLookup
{
    private ?Collection $values = null;

    public function slug(): string
    {
        return 'ui-text-keys';
    }

    public function title(): string
    {
        return 'UI Text Keys';
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
        return 'fa-solid fa-key';
    }

    public function search(): string
    {
        return 'Search key or text...';
    }

    public function guide(): ?string
    {
        return 'Keyed texts of the older screens, the login page and menu labels with a translation key. One column per language; '
            .'an empty cell removes that translation. Texts of the v2 screens are translated in the Translation Dictionary.';
    }

    private function langs(): array
    {
        return DB::connection('tm2sys')->table('sys_languages')->orderByDesc('is_default')->orderBy('sort_order')->orderBy('code')->pluck('name', 'code')->all();
    }

    // Satu baris per kunci: baris sys_translations dengan id terkecil mewakili kuncinya.
    protected function query(): Builder
    {
        return SysTranslation::query()->whereIn('id', SysTranslation::query()->selectRaw('MIN(id)')->groupBy('trans_key'))->orderBy('trans_key');
    }

    protected function prefetch(Collection $items): void
    {
        $this->values = SysTranslation::query()->whereIn('trans_key', $items->pluck('trans_key'))->get()->groupBy('trans_key');
    }

    public function columns(): array
    {
        $cols = [['key' => 'trans_key', 'label' => 'Key', 'required' => true, 'lockSaved' => true, 'width' => '260px']];
        foreach ($this->langs() as $code => $name) {
            $cols[] = ['key' => 'lang_'.$code, 'label' => $name, 'width' => '300px', 'emptyWarn' => true];
        }

        return $cols;
    }

    protected function row(Model $m): array
    {
        $row = ['trans_key' => $m->trans_key];
        foreach (array_keys($this->langs()) as $code) {
            $row['lang_'.$code] = $this->values?->get($m->trans_key)?->firstWhere('lang_code', $code)?->trans_value ?? '';
        }

        return $row;
    }

    // Semua atau tidak sama sekali; kembalian = isi terbaru.
    public function save(array $changed): array
    {
        $langs = array_keys($this->langs());

        DB::connection('tm2sys')->transaction(function () use ($changed, $langs) {
            foreach ($changed as $i => $in) {
                $id = is_numeric($in['id'] ?? null) ? (int) $in['id'] : null;
                $key = $id ? SysTranslation::findOrFail($id)->trans_key : trim((string) ($in['trans_key'] ?? ''));
                if ($key === '' || mb_strlen($key) > 100) {
                    throw ValidationException::withMessages(['row' => tf('Row :n: Key is required (max. 100 characters).', ['n' => $i + 1])]);
                }
                if (! $id && SysTranslation::where('trans_key', $key)->exists()) {
                    throw ValidationException::withMessages(['row' => tf('Row :n: key ":key" already exists.', ['n' => $i + 1, 'key' => $key])]);
                }
                $filled = 0;
                foreach ($langs as $code) {
                    if (! array_key_exists('lang_'.$code, $in)) {
                        $filled += SysTranslation::where('trans_key', $key)->where('lang_code', $code)->exists() ? 1 : 0;

                        continue;
                    }
                    $text = trim((string) $in['lang_'.$code]);
                    if (mb_strlen($text) > 500) {
                        throw ValidationException::withMessages(['row' => tf('Row :n: a text is longer than 500 characters.', ['n' => $i + 1])]);
                    }
                    if ($text === '') {
                        SysTranslation::where('trans_key', $key)->where('lang_code', $code)->delete();
                    } else {
                        SysTranslation::updateOrCreate(['lang_code' => $code, 'trans_key' => $key], ['trans_value' => $text]);
                        $filled++;
                    }
                }
                if ($filled === 0) {
                    throw ValidationException::withMessages(['row' => tf('Row :n: fill in at least one language.', ['n' => $i + 1])]);
                }
            }
        });

        return $this->rows();
    }
}
