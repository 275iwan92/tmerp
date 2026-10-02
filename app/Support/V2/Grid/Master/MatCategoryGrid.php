<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\MatCategory;
use App\Services\Master\MaterialCategoryService;
use Devlib\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Material Category = PEMBENTUK KODE ITEM (docs/globaluserrules.md B.16). Layar = dropdown saling terkait Category 1 -> 5
// (resources/views/v2/screens/md-matcat.blade.php); grid ini daftar di bawahnya: tanpa pilihan = seluruh rantai, sesudah
// memilih = isi tingkat berikutnya. Tiap baris membawa kode item yang dihasilkan rantainya. Cetakan = seluruh rantai.
class MatCategoryGrid extends MasterDataGrid
{
    private ?MaterialCategoryService $service = null;

    /** @var array<int, int>|null jumlah anak per induk */
    private ?array $below = null;

    /** @var array<int, string>|null */
    private ?array $problems = null;

    private bool $everything = false;

    public function slug(): string
    {
        return 'md-matcat';
    }

    public function title(): string
    {
        return t('Master Raw Material Category');
    }

    public function icon(): string
    {
        return 'fa-solid fa-sitemap';
    }

    public function search(): string
    {
        return 'Search category, item code...';
    }

    public function service(): MaterialCategoryService
    {
        return $this->service ??= app(MaterialCategoryService::class);
    }

    // Baris yang sedang dipilih di dropdown (?id=) atau induk entri baru (?parent=): grid menampilkan isi di bawahnya.
    public function under(): ?MatCategory
    {
        if ($this->everything) {
            return null;
        }
        $id = (int) (request()->query('id') ?: request()->query('parent'));
        $node = $id ? $this->service()->all()->get($id) : null;

        // Tingkat terakhir tidak punya isi di bawahnya: daftar menampilkan saudaranya (isi induknya).
        return $node && (int) $node->level >= MaterialCategoryService::LEVELS && $node->parent_id ? $this->service()->all()->get($node->parent_id) : $node;
    }

    protected function query(): Builder
    {
        $under = $this->under();

        return MatCategory::query()->when($under, fn (Builder $q) => $q->where('parent_id', $under->id_category));
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Category', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'level', 'label' => 'Level', 'type' => 'number'],
            ['key' => 'root', 'label' => 'Category 1'],
            ['key' => 'under', 'label' => 'Under'],
            ['key' => 'prefix', 'label' => 'Prefix'],
            ['key' => 'below', 'label' => 'Entries Below', 'type' => 'number'],
            ['key' => 'class', 'label' => 'Stock Class', 'type' => 'badge', 'tones' => ['RAW' => 'info', 'INDIRECT' => 'warn', 'SERVICE' => 'success']],
        ];
    }

    protected function row(Model $m): array
    {
        $svc = $this->service();
        $this->below ??= $svc->all()->whereNotNull('parent_id')->countBy('parent_id')->all();
        $this->problems ??= $svc->problems();
        $names = $svc->names($m);
        $broken = $this->problems[$m->id_category] ?? null;

        return [
            'name' => $m->category_name, 'code' => $broken ? '' : $svc->code($m), 'level' => (int) $m->level,
            'root' => $broken ? null : $names[0], 'under' => $broken ? t('Broken chain').' — '.t($broken) : (implode(' › ', array_slice($names, 0, -1)) ?: null),
            'prefix' => $m->prefix_char, 'below' => (int) ($this->below[$m->id_category] ?? 0), 'class' => $m->stock_class,
            'tone' => $broken ? 'danger' : null,
            'sort' => $this->sortKey($m),
        ];
    }

    // Urutan baca = urutan rantai, bukan urutan id: tiap mata rantai = nama + prefix, sehingga induk selalu tepat di atas isinya.
    private function sortKey(Model $m): string
    {
        return implode("\x01", array_map(fn (MatCategory $n) => mb_strtolower(trim($n->category_name))."\x02".$n->prefix_char, $this->service()->chain($m)));
    }

    public function rows(): array
    {
        $rows = parent::rows();
        usort($rows, fn ($a, $b) => strnatcmp($a['sort'], $b['sort']));

        return array_map(fn ($r) => array_diff_key($r, ['sort' => 1]), $rows);
    }

    public function rowTone(): ?string
    {
        return 'tone';
    }

    public function filters(): array
    {
        return ['level' => 'Level', 'root' => 'Category 1', 'class' => 'Stock Class'];
    }

    public function toggle(int $id): bool
    {
        $node = MatCategory::query()->findOrFail($id);
        $this->service()->setActive($node, ! $node->activate, auth()->user()?->login);

        return (bool) $node->fresh()->activate;
    }

    public function openUrl(): ?string
    {
        return $this->url().'?id={id}';
    }

    // + New Record = entri baru di bawah baris yang sedang dipilih (tanpa pilihan = Category 1 baru).
    public function newUrl(): ?string
    {
        $under = $this->under();
        if ($under && ((int) $under->level >= MaterialCategoryService::LEVELS || ! $this->service()->complete($under))) {
            return null;
        }

        return $this->url().'?new='.($under ? (int) $under->level + 1 : 1).($under ? '&parent='.$under->id_category : '');
    }

    public function printable(): bool
    {
        return true;
    }

    public function printSignatures(): array
    {
        return [];
    }

    // Cetakan = SELURUH rantai dalam tata letak tabel asal (doc_rmprefix): Category 1..5 berdampingan + kode item per baris.
    public function printData(): array
    {
        $this->everything = true;
        $svc = $this->service();
        $rows = [];
        foreach ($svc->all() as $m) {
            if (! $svc->complete($m)) {
                continue;
            }
            $names = $svc->names($m);
            $rows[] = ['code' => $svc->code($m), 'level' => (int) $m->level, 'prefix' => $m->prefix_char, 'status' => $m->activate ? 'Active' : 'Inactive',
                'sort' => $this->sortKey($m)]
                + array_combine(['c1', 'c2', 'c3', 'c4', 'c5'], array_pad($names, MaterialCategoryService::LEVELS, ''));
        }
        usort($rows, fn ($a, $b) => strnatcmp($a['sort'], $b['sort']));
        $columns = [['key' => 'code', 'label' => 'Item Code', 'type' => 'text']];
        foreach (range(1, MaterialCategoryService::LEVELS) as $i) {
            $columns[] = ['key' => 'c'.$i, 'label' => 'Category '.$i, 'type' => 'text'];
        }
        $columns[] = ['key' => 'prefix', 'label' => 'Prefix', 'type' => 'text'];
        $columns[] = ['key' => 'level', 'label' => 'Level', 'type' => 'number'];
        $columns[] = ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Active' => 'success', 'Inactive' => 'neutral']];

        return ['columns' => $columns, 'rows' => array_map(fn ($r) => array_diff_key($r, ['sort' => 1]), $rows),
            'criteria' => ['As Of' => Locale::date(now()), 'Rows' => Locale::number(count($rows))]];
    }

    public function screen(): array
    {
        return ['view' => 'v2.screens.md-matcat'] + parent::screen();
    }
}
