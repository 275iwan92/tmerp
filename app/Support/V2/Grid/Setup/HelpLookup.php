<?php

namespace App\Support\V2\Grid\Setup;

use App\Services\Help\HelpCenter;
use App\Support\Localization\Dictionary;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// Induk layar input Help (Help Topics, Help Lines). Teks help tersimpan English; di sebelahnya satu kolom per bahasa aktif
// yang membaca dan menulis kamus konteks 'help' -- penulis help tidak perlu pindah ke Translation Dictionary.
abstract class HelpLookup extends DbLookup
{
    // Kiriman baris yang sedang disimpan (prepare() -> saved() berurutan per baris di DbLookup::save).
    private array $current = [];

    // Kolom tabel yang berisi teks English yang diterjemahkan.
    abstract protected function textColumn(): string;

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
        return 'Help and Support';
    }

    // Bahasa terjemahan = bahasa aktif selain English (bahasa dasar database).
    protected function langs(): array
    {
        return DB::connection('tm2sys')->table('sys_languages')->where('code', '<>', 'en')->where('activate', 1)->orderBy('sort_order')->pluck('name', 'code')->all();
    }

    protected function langColumns(string $width = '420px'): array
    {
        return collect($this->langs())->map(fn ($name, $code) => ['key' => 'lang_'.$code, 'label' => $name, 'width' => $width])->values()->all();
    }

    protected function row(Model $m): array
    {
        $row = parent::row($m);
        foreach (array_keys($this->langs()) as $code) {
            $row['lang_'.$code] = Dictionary::context(HelpCenter::CONTEXT, $code)[$m->getAttribute($this->textColumn())] ?? '';
        }

        return $row;
    }

    protected function prepare(array $in): array
    {
        return $this->current = $in;
    }

    protected function saved(Model $row): void
    {
        $db = DB::connection('tm2sys');
        $en = trim((string) $row->getAttribute($this->textColumn()));
        foreach (array_keys($this->langs()) as $code) {
            if ($en === '' || ! array_key_exists('lang_'.$code, $this->current)) {
                continue;
            }
            $db->table('sys_dictionary')->insertOrIgnore(['context' => HelpCenter::CONTEXT, 'source_en' => $en, 'created_at' => now(), 'created_by' => auth()->user()?->login]);
            $id = $db->table('sys_dictionary')->where('context', HelpCenter::CONTEXT)->where('source_en', $en)->value('id_dictionary');
            $text = trim((string) $this->current['lang_'.$code]);
            if ($text === '') {
                $db->table('sys_dictionary_lang')->where('id_dictionary', $id)->where('lang_code', $code)->delete();
            } else {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $id, 'lang_code' => $code],
                    ['text' => $text, 'updated_at' => now(), 'updated_by' => auth()->user()?->login]);
            }
        }
    }

    public function save(array $changed): array
    {
        parent::save($changed);
        Dictionary::flush();

        return $this->rows();
    }
}
