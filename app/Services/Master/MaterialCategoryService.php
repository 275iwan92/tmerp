<?php

namespace App\Services\Master;

use App\Models\Acc\FinAccount;
use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Material Category = PEMBENTUK KODE ITEM (user, docs/globaluserrules.md B.16; rinci docs/md_for_systems/matcategory_delta.md §5).
// Rantai Category 1 -> 5 (dulu doc_rmprefix1..5, sekarang mat_category); kode item = prefix1 + prefix2 + ... + prefix5.
// Semua aturan yang menjaga rantai itu tetap benar ada di sini -- layar hanya memanggil.
class MaterialCategoryService
{
    public const LEVELS = 5;

    public const STOCK_CLASSES = ['RAW', 'INDIRECT', 'SERVICE'];

    /** @var Collection<int, MatCategory>|null semua baris, kunci = id_category */
    private ?Collection $all = null;

    // Panjang prefix per tingkat = ukuran kolom prefix1..5 di doc_rmprefix: tingkat 1-3 satu karakter, tingkat 4-5 sampai tiga.
    public static function prefixLength(int $level): int
    {
        return $level <= 3 ? 1 : 3;
    }

    /** @return Collection<int, MatCategory> */
    public function all(): Collection
    {
        return $this->all ??= MatCategory::query()->get()->keyBy('id_category');
    }

    public function forget(): void
    {
        $this->all = null;
    }

    /** @return list<MatCategory> rantai dari Category 1 sampai baris ini */
    public function chain(MatCategory|int|null $node): array
    {
        $node = is_int($node) ? $this->all()->get($node) : $node;
        $chain = [];
        // Batas langkah = jumlah tingkat: baris yang induknya berputar tidak membuat loop tanpa akhir.
        for ($i = 0; $node && $i < self::LEVELS; $i++) {
            array_unshift($chain, $node);
            $node = $node->parent_id ? $this->all()->get($node->parent_id) : null;
        }

        return $chain;
    }

    // Rantai utuh = jumlah mata rantai sama dengan level, dan level naik 1, 2, 3, ... dari atas.
    public function complete(MatCategory|int|null $node): bool
    {
        $chain = $this->chain($node);
        foreach ($chain as $i => $n) {
            if ((int) $n->level !== $i + 1) {
                return false;
            }
        }

        return $chain !== [] && $chain[0]->parent_id === null;
    }

    // Kode item sampai tingkat baris ini: gabungan prefix sepanjang rantai.
    public function code(MatCategory|int|null $node): string
    {
        return implode('', array_map(fn (MatCategory $n) => (string) $n->prefix_char, $this->chain($node)));
    }

    /** @return list<string> */
    public function names(MatCategory|int|null $node): array
    {
        return array_map(fn (MatCategory $n) => (string) $n->category_name, $this->chain($node));
    }

    /** @return Collection<int, MatCategory> anak langsung (parent null = Category 1), urut nama */
    public function children(?int $parentId): Collection
    {
        return $this->all()
            ->filter(fn (MatCategory $n) => $parentId === null ? ((int) $n->level === 1 && $n->parent_id === null) : (int) $n->parent_id === $parentId)
            ->sortBy(fn (MatCategory $n) => mb_strtolower($n->category_name).' '.$n->prefix_char, SORT_NATURAL)->values();
    }

    // Bahan untuk objek DevLib x-dl::code-chain: [id, induk (0 = puncak), level, nama, prefix, aktif]. Baris yang rantainya
    // putus tidak ikut -- tidak boleh bisa dipilih untuk membentuk kode.
    public function nodes(): array
    {
        return $this->all()->filter(fn (MatCategory $n) => $this->complete($n))
            ->sortBy(fn (MatCategory $n) => mb_strtolower($n->category_name).' '.$n->prefix_char, SORT_NATURAL)
            ->map(fn (MatCategory $n) => [(int) $n->id_category, (int) ($n->parent_id ?? 0), (int) $n->level, (string) $n->category_name, (string) $n->prefix_char, $n->activate ? 1 : 0])
            ->values()->all();
    }

    // Item bahan baku yang memakai rantai ini. mat_raw.category1..5 menyimpan NAMA, jadi dicocokkan lewat nama seluruh rantai.
    public function usage(MatCategory $node): int
    {
        return $this->complete($node) ? $this->materials($this->names($node))->count() : 0;
    }

    private function materials(array $names)
    {
        $q = MatRaw::query();
        foreach (array_values($names) as $i => $name) {
            $q->where('category'.($i + 1), $name);
        }

        return $q;
    }

    public function save(?MatCategory $node, array $in, ?string $login): MatCategory
    {
        $new = $node === null;
        $level = $new ? (int) ($in['level'] ?? 0) : (int) $node->level;
        $parentId = $new ? ((int) ($in['parent_id'] ?? 0) ?: null) : $node->parent_id;
        $name = trim((string) ($in['category_name'] ?? ''));
        $prefix = mb_strtoupper(trim((string) ($in['prefix_char'] ?? '')));
        $fail = fn (string $field, string $message) => throw ValidationException::withMessages([$field => $message]);

        if ($level < 1 || $level > self::LEVELS) {
            $fail('level', t('The category level must be 1 to 5.'));
        }

        // --- Posisi di rantai: hanya ditentukan saat dibuat, lalu terkunci.
        if ($new) {
            $parent = $parentId ? $this->all()->get($parentId) : null;
            if ($level === 1 && $parentId) {
                $fail('parent_id', t('Category 1 is the top of the chain and has no parent.'));
            }
            if ($level > 1) {
                if (! $parent) {
                    $fail('parent_id', tf('Pick Category :n first.', ['n' => $level - 1]));
                }
                if ((int) $parent->level !== $level - 1 || ! $this->complete($parent)) {
                    $fail('parent_id', tf('Category :n must sit directly under a Category :p with a complete chain.', ['n' => $level, 'p' => $level - 1]));
                }
                if (! $parent->activate) {
                    $fail('parent_id', tf(':name is inactive; activate it before adding entries below it.', ['name' => $parent->category_name]));
                }
            }
        }

        $data = validator(['category_name' => $name, 'prefix_char' => $prefix] + $in, [
            'category_name' => ['required', 'string', 'max:100'],
            'prefix_char' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:2000'],
            'stock_class' => ['nullable', Rule::in(self::STOCK_CLASSES)],
            'default_coa_account_id' => ['nullable', 'integer'],
            'default_stock_asset_account_id' => ['nullable', 'integer'],
            'default_gr_ir_account_id' => ['nullable', 'integer'],
        ], [], ['category_name' => t('Name'), 'prefix_char' => t('Prefix'), 'note' => t('Note'), 'stock_class' => t('Stock Class')])->validate();

        // --- Prefix: bagian dari kode item.
        $max = self::prefixLength($level);
        if (! preg_match('/^[A-Z0-9]{1,'.$max.'}$/', $prefix)) {
            $fail('prefix_char', $max === 1
                ? tf('Prefix :n takes exactly 1 character (a letter or a digit).', ['n' => $level])
                : tf('Prefix :n takes 1 to 3 characters (letters or digits).', ['n' => $level]));
        }
        $siblings = $this->all()->filter(fn (MatCategory $n) => (int) $n->level === $level && (int) ($n->parent_id ?? 0) === (int) ($parentId ?? 0) && (! $node || $n->id_category !== $node->id_category));
        if ($clash = $siblings->first(fn (MatCategory $n) => mb_strtoupper((string) $n->prefix_char) === $prefix)) {
            $fail('prefix_char', tf('Prefix :p is already used by :name here — the item code would clash.', ['p' => $prefix, 'name' => $clash->category_name]));
        }
        // Prefix 4 dan 5 panjangnya tidak tetap, jadi dua rantai berbeda bisa menghasilkan kode yang sama (1+23 = 12+3).
        $code = $this->code($parentId).$prefix;
        if ($same = $this->all()->first(fn (MatCategory $n) => (int) $n->level === $level && (! $node || $n->id_category !== $node->id_category) && $this->complete($n) && $this->code($n) === $code)) {
            $fail('prefix_char', tf('Item code :code is already produced by :path.', ['code' => $code, 'path' => implode(' › ', $this->names($same))]));
        }

        $renamed = ! $new && $name !== (string) $node->category_name;
        $recoded = ! $new && $prefix !== mb_strtoupper((string) $node->prefix_char);
        $used = $new ? 0 : $this->usage($node);
        $below = $new ? 0 : $this->children($node->id_category)->count();
        if ($recoded && ($used > 0 || $below > 0)) {
            $fail('prefix_char', tf('The prefix is locked: :below entries below it and :used materials already build their item code from it.', ['below' => $below, 'used' => $used]));
        }

        // --- Nama: item menyimpan NAMA kategori, jadi dua saudara bernama sama membuat rantai item tidak pasti.
        $twin = fn (string $of) => $siblings->first(fn (MatCategory $n) => mb_strtolower(trim((string) $n->category_name)) === mb_strtolower($of));
        if (($new || $renamed) && ($t = $twin($name))) {
            $fail('category_name', tf(':name already exists here (prefix :p).', ['name' => $t->category_name, 'p' => $t->prefix_char]));
        }

        foreach (['default_coa_account_id', 'default_stock_asset_account_id', 'default_gr_ir_account_id'] as $field) {
            $id = $level === 1 ? ((int) ($data[$field] ?? 0) ?: null) : null;
            if ($id && ! FinAccount::query()->whereKey($id)->where('is_postable', true)->exists()) {
                $fail($field, t('Pick a postable account.'));
            }
            $data[$field] = $id;
        }

        $oldNames = $new ? [] : $this->names($node);
        $hadTwin = ! $new && $twin((string) $node->category_name) !== null;
        $oldCode = $new ? '' : $this->code($node);

        $saved = DB::connection('tm2mst')->transaction(function () use ($node, $new, $level, $parentId, $name, $prefix, $data, $in, $login, $renamed, $used, $oldNames, $hadTwin, $oldCode) {
            $node ??= new MatCategory(['level' => $level, 'parent_id' => $parentId, 'activate' => true, 'created_by' => $login]);
            $node->fill([
                'category_name' => $name,
                'prefix_char' => $prefix,
                'note' => ($data['note'] ?? null) ?: null,
                // Kelas stok dan akun bawaan hanya milik Category 1 (dulu kolom doc_rmprefix1).
                'stock_class' => $level === 1 ? (($data['stock_class'] ?? null) ?: 'RAW') : null,
                'default_coa_account_id' => $data['default_coa_account_id'],
                'default_stock_asset_account_id' => $data['default_stock_asset_account_id'],
                'default_gr_ir_account_id' => $data['default_gr_ir_account_id'],
                'updated_by' => $login,
            ]);
            if ($new && array_key_exists('activate', $in)) {
                $node->activate = (bool) $in['activate'];
            }
            $node->save();

            // Ganti nama: item yang memakai rantai ini ikut, supaya sambungan item -> kategori tidak putus. Bila ada saudara
            // bernama sama (data lama), hanya item yang kodenya memang berasal dari rantai ini yang diubah.
            if ($renamed && $used > 0) {
                $q = $this->materials($oldNames);
                if ($hadTwin) {
                    $q->where('material_code', 'like', addcslashes($oldCode, '%_\\').'%');
                }
                $q->update(['category'.$level => $name, 'update_by' => $login]);
            }

            return $node;
        });

        $this->forget();

        return $saved;
    }

    public function setActive(MatCategory $node, bool $on, ?string $login): void
    {
        if ($on && $node->parent_id && ! $this->all()->get($node->parent_id)?->activate) {
            throw ValidationException::withMessages(['activate' => tf(':name sits under an inactive category; activate the one above it first.', ['name' => $node->category_name])]);
        }
        $node->update(['activate' => $on, 'updated_by' => $login]);
        $this->forget();
    }

    // Pemeriksaan rantai seluruh tabel (dipakai test dan peringatan di layar): [id => keterangan].
    public function problems(): array
    {
        $out = [];
        $codes = [];
        foreach ($this->all() as $n) {
            $id = (int) $n->id_category;
            $level = (int) $n->level;
            $prefix = (string) $n->prefix_char;
            if ($level > 1 && ! $n->parent_id) {
                $out[$id] = 'no parent';
            } elseif (! $this->complete($n)) {
                $out[$id] = 'broken chain';
            } elseif (! preg_match('/^[A-Za-z0-9]{1,'.self::prefixLength($level).'}$/', $prefix)) {
                $out[$id] = 'prefix not valid for its level';
            } else {
                $codes[$level.'|'.mb_strtoupper($this->code($n))][] = $id;
            }
        }
        foreach ($codes as $ids) {
            if (count($ids) > 1) {
                foreach ($ids as $id) {
                    $out[$id] = 'item code clashes with '.implode(', ', array_diff($ids, [$id]));
                }
            }
        }

        return $out;
    }
}
