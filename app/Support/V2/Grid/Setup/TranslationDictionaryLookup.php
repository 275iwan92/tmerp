<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysDictionary;
use App\Models\Sys\SysDictionaryGroup;
use App\Models\Sys\SysDictionaryLang;
use App\Support\Localization\Dictionary;
use App\Support\V2\Grid\DbLookup;
use Devlib\Lang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Setup > Translation Dictionary (LOCKED user 2026-10-01): database & formula English, bahasa lain hanya tampilan/cetak.
// Dua panel ala ScriptCase (user 2026-10-03): pohon kelompok pesan di kiri (DevLib messages / Project messages / Lookup values
// per kolom), grid di kanan = satu baris per teks English, satu kolom per bahasa aktif (sys_languages). Teks baru yang dipakai
// layar tercatat otomatis beserta kelompok (objek yang menampilkannya) dan layar pertama ("Used on"). Kata bawaan DevLib
// (origin devlib) tampil di cabangnya sendiri dan boleh ditimpa -- kamus proyek menang. Kosongkan sel = terjemahan dihapus.
class TranslationDictionaryLookup extends DbLookup
{
    // Semua baris kamus ("All messages") ikut satu lembar; pencarian ada di lembar itu.
    protected int $limit = 30000;

    private ?array $groups = null;

    private ?array $devlibDefaults = null;

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
        return 'Language and Localization';
    }

    public function icon(): string
    {
        return 'fa-solid fa-language';
    }

    public function search(): string
    {
        return 'Search English, translation or screen...';
    }

    public function guide(): ?string
    {
        return 'Write English in the code; translate here. Pick a group on the left; an empty (amber) cell is shown in English. '
            .'DevLib messages are the built-in words of the screen objects: a translation typed here replaces the default.';
    }

    public function screen(): array
    {
        return ['view' => 'v2.screens.translation-dictionary'] + parent::screen();
    }

    // Cabang yang dipilih di pohon kiri (?g=): all | empty | devlib:<group> | app:<group> | ctx:<context>.
    public function node(): string
    {
        $g = (string) request('g', 'all');

        return preg_match('/^(all|empty|devlib:[a-z0-9_]+|app:[a-z0-9_]+|ctx:[A-Za-z0-9_.]+)$/', $g) ? $g : 'all';
    }

    public function nodeTitle(): string
    {
        [$kind, $arg] = array_pad(explode(':', $this->node(), 2), 2, null);

        return match ($kind) {
            'empty' => t('Untranslated'),
            'devlib' => t('DevLib messages').' › '.t($this->groupOptions()[$arg] ?? $arg),
            'app' => t('Project messages').' › '.t($this->groupOptions()[$arg] ?? $arg),
            'ctx' => t('Lookup values').' › '.$arg,
            default => t('All messages'),
        };
    }

    // Bahasa terjemahan = bahasa aktif selain English (bahasa dasar database).
    private function langs(): array
    {
        return DB::connection('tm2sys')->table('sys_languages')->where('code', '<>', 'en')->where('activate', 1)->orderBy('sort_order')->pluck('name', 'code')->all();
    }

    // code => label kelompok aktif, urut sort_order.
    private function groupOptions(): array
    {
        return $this->groups ??= SysDictionaryGroup::query()->where('activate', 1)->orderBy('sort_order')->orderBy('code')->pluck('label', 'code')->all();
    }

    // Terjemahan bawaan DevLib (Devlib\Lang::DICT): English => [lang => teks], untuk kolom "Used on" baris origin devlib.
    private function devlibDefault(string $en, string $lang): ?string
    {
        if ($this->devlibDefaults === null) {
            $this->devlibDefaults = [];
            foreach (Lang::all() as $row) {
                $row = array_is_list($row) ? ['id' => $row[0], 'en' => $row[1]] : $row;
                if (isset($row['en'])) {
                    $this->devlibDefaults[$row['en']] ??= $row;
                }
            }
        }
        $text = $this->devlibDefaults[$en][$lang] ?? null;

        return $text !== null && $text !== $en ? $text : null;
    }

    // Baris yang belum punya terjemahan di salah satu bahasa aktif.
    private function untranslated(Builder $q): Builder
    {
        $langs = array_keys($this->langs());
        if (! $langs) {
            return $q->whereRaw('1 = 0');
        }
        foreach ($langs as $code) {
            $q->orWhereDoesntHave('langs', fn ($l) => $l->where('lang_code', $code)->where('text', '<>', ''));
        }

        return $q;
    }

    protected function query(): Builder
    {
        $q = SysDictionary::query()->with('langs')->orderBy('context')->orderBy('group_code')->orderBy('source_en');
        [$kind, $arg] = array_pad(explode(':', $this->node(), 2), 2, null);

        return match ($kind) {
            'empty' => $q->where(fn ($w) => $this->untranslated($w)),
            'devlib' => $q->where('context', 'ui')->where('origin', SysDictionary::ORIGIN_DEVLIB)->where('group_code', $arg),
            'app' => $q->whereIn('context', ['ui', 'help'])->where('origin', SysDictionary::ORIGIN_APP)->where('group_code', $arg),
            'ctx' => $q->where('context', $arg),
            default => $q,
        };
    }

    // Pohon kiri (x-dl::side-menu): jumlah per cabang dari satu query gabungan.
    public function tree(): array
    {
        $rows = SysDictionary::query()->selectRaw('context, origin, group_code, COUNT(*) AS n')->groupBy('context', 'origin', 'group_code')->get();
        $total = (int) $rows->sum('n');
        $empty = SysDictionary::query()->where(fn ($w) => $this->untranslated($w))->count();
        $url = fn (string $g) => route('v2.screen', $this->slug()).'?g='.$g;
        $leaf = fn (string $key, string $label, int $n, ?string $icon = null, ?string $tone = null) => ['key' => $key, 'label' => $label, 'url' => $url($key), 'badge' => (string) $n] + ($icon ? ['icon' => $icon] : []) + ($tone ? ['tone' => $tone] : []);
        $icons = SysDictionaryGroup::query()->pluck('icon', 'code')->all();

        $items = [$leaf('all', t('All messages'), $total, 'fa-layer-group'), $leaf('empty', t('Untranslated'), $empty, 'fa-triangle-exclamation', $empty ? 'warn' : 'accent')];
        foreach ([SysDictionary::ORIGIN_DEVLIB => t('DevLib messages'), SysDictionary::ORIGIN_APP => t('Project messages')] as $origin => $caption) {
            $items[] = ['caption' => $caption];
            foreach ($this->groupOptions() as $code => $label) {
                $n = (int) $rows->where('origin', $origin)->where('group_code', $code)->whereIn('context', ['ui', 'help'])->sum('n');
                if ($n > 0) {
                    $items[] = $leaf("$origin:$code", t($label), $n, $icons[$code] ?? 'fa-folder');
                }
            }
        }
        $items[] = ['caption' => t('Lookup values (per column)')];
        foreach ($rows->whereNotIn('context', ['ui', 'help'])->groupBy('context') as $ctx => $list) {
            $items[] = $leaf("ctx:$ctx", $ctx === 'date.month_short' ? t('Month names') : preg_replace('/^tm2[a-z]+\./', '', $ctx), (int) $list->sum('n'), $ctx === 'date.month_short' ? 'fa-calendar' : 'fa-database');
        }

        return $items;
    }

    public function columns(): array
    {
        [$kind] = explode(':', $this->node(), 2);
        $cols = [];
        if ($kind !== 'ctx') {
            $cols[] = ['key' => 'group_code', 'label' => 'Group', 'type' => 'select', 'options' => $this->groupOptions(), 'required' => true, 'width' => '150px'];
        }
        if ($kind === 'all' || $kind === 'empty' || $kind === 'ctx') {
            $cols[] = ['key' => 'context', 'label' => 'Context', 'required' => true, 'width' => '150px', 'lockSaved' => true];
        }
        $cols[] = ['key' => 'source_en', 'label' => 'English (source)', 'required' => true, 'width' => '280px', 'lockSaved' => true];
        $cols[] = ['key' => 'first_seen', 'label' => 'Used on', 'readonly' => true, 'width' => '170px'];
        foreach ($this->langs() as $code => $name) {
            $cols[] = ['key' => 'lang_'.$code, 'label' => $name, 'width' => '280px', 'emptyWarn' => true];
        }

        return [...$cols, ['key' => 'note', 'label' => 'Note', 'width' => '200px'], ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px']];
    }

    protected function row(Model $m): array
    {
        $row = ['group_code' => $m->group_code, 'context' => $m->context, 'source_en' => $m->source_en, 'first_seen' => $m->first_seen ?? ''];
        if ($m->origin === SysDictionary::ORIGIN_DEVLIB) {
            $defaults = array_filter(array_map(fn ($code) => $this->devlibDefault($m->source_en, $code), array_keys($this->langs())));
            $row['first_seen'] = t('DevLib object').($defaults ? ' · '.implode(' / ', $defaults) : '');
        }
        foreach (array_keys($this->langs()) as $code) {
            $row['lang_'.$code] = $m->langs->firstWhere('lang_code', $code)?->text ?? '';
        }

        return $row + ['note' => $m->note, 'activate' => (bool) $m->activate];
    }

    // Semua atau tidak sama sekali; kembalian = isi terbaru. Terjemahan kosong = baris bahasa dihapus.
    public function save(array $changed): array
    {
        $login = auth()->user()?->login;
        $langs = array_keys($this->langs());
        $groups = $this->groupOptions();
        [$kind, $arg] = array_pad(explode(':', $this->node(), 2), 2, null);

        DB::connection('tm2sys')->transaction(function () use ($changed, $login, $langs, $groups, $kind, $arg) {
            foreach ($changed as $i => $in) {
                $id = is_numeric($in['id'] ?? null) ? (int) $in['id'] : null;
                $d = $id ? SysDictionary::findOrFail($id) : new SysDictionary(['created_by' => $login, 'origin' => SysDictionary::ORIGIN_APP]);
                $ctx = $id ? $d->context : (trim((string) ($in['context'] ?? '')) ?: ($kind === 'ctx' ? $arg : 'ui'));
                $src = $id ? $d->source_en : trim((string) ($in['source_en'] ?? ''));
                if ($src === '') {
                    throw ValidationException::withMessages(['row' => tf('Row :n: English (source) is required.', ['n' => $i + 1])]);
                }
                if (! $id && SysDictionary::where('context', $ctx)->where('source_en', $src)->exists()) {
                    throw ValidationException::withMessages(['row' => tf('Row :n: ":src" already exists in context ":ctx".', ['n' => $i + 1, 'src' => $src, 'ctx' => $ctx])]);
                }
                $group = array_key_exists('group_code', $in) ? (string) $in['group_code'] : ($id ? $d->group_code : ($kind === 'app' || $kind === 'devlib' ? $arg : Dictionary::defaultGroup($ctx)));
                if (! isset($groups[$group])) {
                    throw ValidationException::withMessages(['row' => tf('Row :n: group ":g" is unknown. Add it in Setup > Message Groups first.', ['n' => $i + 1, 'g' => $group])]);
                }
                $d->fill(['context' => $ctx, 'source_en' => $src, 'group_code' => $group, 'note' => ($in['note'] ?? '') === '' ? null : $in['note'],
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
