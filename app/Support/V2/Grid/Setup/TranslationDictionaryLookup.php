<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysDictionary;
use App\Models\Sys\SysDictionaryLang;
use App\Support\Localization\Dictionary;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Setup > Translation Dictionary (LOCKED user 2026-10-01): database & formula English, bahasa lain hanya tampilan/cetak.
// Satu baris = satu teks English (context + source_en); satu kolom per bahasa aktif (sys_languages, selain English).
// Teks baru yang dipakai layar tercatat otomatis (Dictionary::remember) -- penerjemah cukup mengisi sel yang kosong.
// Tempel dari Excel (salin kolom English | Indonesia) langsung ke sel; kosongkan sel = terjemahan dihapus (tampil English).
class TranslationDictionaryLookup extends DbLookup
{
    public function slug(): string
    {
        return 'translation-dictionary';
    }

    public function title(): string
    {
        return 'Translation Dictionary';
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
        return 'Settings';
    }

    public function icon(): string
    {
        return 'fa-solid fa-language';
    }

    public function search(): string
    {
        return 'Search English, translation or context...';
    }

    public function guide(): ?string
    {
        return 'Write English in the code; translate here. Empty cell = shown in English. context "ui" = screen & print labels; '
            .'"date.month_short" = month names; "<db>.<table>.<column>" = lookup values stored in English. '
            .'Search "missing" (or "missing: id") to list rows still waiting for a translation. Changing the English text keeps its translations.';
    }

    // Bahasa terjemahan = bahasa aktif selain English (bahasa dasar database).
    private function langs(): array
    {
        return DB::connection('tm2sys')->table('sys_languages')->where('code', '<>', 'en')
            ->when(DB::connection('tm2sys')->getSchemaBuilder()->hasColumn('sys_languages', 'activate'), fn ($q) => $q->where('activate', 1)->orderBy('sort_order'))
            ->pluck('name', 'code')->all();
    }

    protected function query(): Builder
    {
        return SysDictionary::query()->with('langs')->orderBy('context')->orderBy('source_en');
    }

    public function columns(): array
    {
        $cols = [
            ['key' => 'context', 'label' => 'Context', 'required' => true, 'width' => '150px'],
            ['key' => 'source_en', 'label' => 'English (source)', 'required' => true, 'width' => '280px'],
            ['key' => 'missing', 'label' => 'Untranslated', 'readonly' => true, 'width' => '120px'],
        ];
        foreach ($this->langs() as $code => $name) {
            $cols[] = ['key' => 'lang_'.$code, 'label' => $name, 'width' => '280px'];
        }

        return [...$cols, ['key' => 'note', 'label' => 'Note', 'width' => '200px'], ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px']];
    }

    protected function row(Model $m): array
    {
        $row = ['context' => $m->context, 'source_en' => $m->source_en, 'missing' => ''];
        $missing = [];
        foreach (array_keys($this->langs()) as $code) {
            $row['lang_'.$code] = $m->langs->firstWhere('lang_code', $code)?->text ?? '';
            if ($row['lang_'.$code] === '') {
                $missing[] = $code;
            }
        }
        // Dicari lewat kotak cari: ketik "missing" (semua yang belum lengkap) atau "missing: id".
        $row['missing'] = $missing ? 'missing: '.implode(', ', $missing) : '';

        return $row + ['note' => $m->note, 'activate' => (bool) $m->activate];
    }

    // Semua atau tidak sama sekali; kembalian = isi terbaru. Terjemahan kosong = baris bahasa dihapus.
    public function save(array $changed): array
    {
        $login = auth()->user()?->login;
        $langs = array_keys($this->langs());

        DB::connection('tm2sys')->transaction(function () use ($changed, $login, $langs) {
            foreach ($changed as $i => $in) {
                $id = is_numeric($in['id'] ?? null) ? (int) $in['id'] : null;
                $ctx = trim((string) ($in['context'] ?? '')) ?: 'ui';
                $src = trim((string) ($in['source_en'] ?? ''));
                if ($src === '') {
                    throw ValidationException::withMessages(['row' => 'Row '.($i + 1).': English (source) is required.']);
                }
                $twin = SysDictionary::where('context', $ctx)->where('source_en', $src)->when($id, fn ($q) => $q->whereKeyNot($id))->first();
                if ($twin && ! $id) {
                    throw ValidationException::withMessages(['row' => 'Row '.($i + 1).": \"$src\" already exists in context \"$ctx\"."]);
                }
                $d = $id ? SysDictionary::findOrFail($id) : new SysDictionary(['created_by' => $login]);
                // English sumber diganti ke teks yang sudah ada: dua baris digabung, terjemahan yang belum ada di sana ikut pindah.
                if ($twin && $d->context === $ctx) {
                    Dictionary::rename($d->source_en, $src, $ctx);
                    $d = $twin->fresh();
                } elseif ($twin) {
                    throw ValidationException::withMessages(['row' => 'Row '.($i + 1).": \"$src\" already exists in context \"$ctx\"."]);
                }
                $d->fill(['context' => $ctx, 'source_en' => $src, 'note' => ($in['note'] ?? '') === '' ? null : $in['note'],
                    'activate' => array_key_exists('activate', $in) ? filter_var($in['activate'], FILTER_VALIDATE_BOOLEAN) : true, 'updated_by' => $login])->save();
                foreach ($langs as $code) {
                    if (! array_key_exists('lang_'.$code, $in)) {
                        continue;
                    }
                    $text = trim((string) $in['lang_'.$code]);
                    $q = SysDictionaryLang::where('id_dictionary', $d->id_dictionary)->where('lang_code', $code);
                    if ($text === '') {
                        $q->delete();
                    } elseif ($row = $q->first()) {
                        $row->update(['text' => $text, 'updated_by' => $login]);
                    } else {
                        SysDictionaryLang::create(['id_dictionary' => $d->id_dictionary, 'lang_code' => $code, 'text' => $text, 'created_by' => $login]);
                    }
                }
            }
        });
        Dictionary::flush();

        return $this->rows();
    }
}
