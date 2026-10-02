<?php

use App\Models\Master\MatCategory;
use App\Models\Master\ProdMarket;
use App\Models\Master\ProdType;
use App\Models\Master\ProdTypeParam;
use App\Models\Master\ProdTypeSize;
use App\Models\Master\ProdTypeSizeVal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

// Detail satu Product Market (officer ProDev): Tipe Produk, nilai awal Inquiry, dan Ukuran Pasaran
// milik market ini (prod_type_size.market_id). Dipakai Inquiry New sebagai "Kategori Produk".
new #[Title('Product Market — TELASIH MAKMUR ERP')] class extends Component
{
    use WithFileUploads;

    // Dimensi yang disimpan per ukuran pasaran; parameter teknis (bleed, overlap, flap) tetap default Tipe Produk.
    private const SIZE_KEYS = ['product_length', 'product_width', 'product_height', 'top_length', 'top_width', 'lid_height', 'diameter'];

    public ?int $recordId = null;

    // '' = record ini Kategori Produk (induk); terisi = Detail Kategori di bawah kategori itu.
    public string $parent_id = '';

    public string $market_code = '';

    public string $market_label = '';

    public string $type_code = '';

    // Rantai kategori bahan baku (mat_category level 1..5) per bahan: [level => id_category|null].
    // Level 1 dikunci: #1 selalu Paper, #2 selalu Sheet/SF (user 2026-09-26).
    public array $picks = ['paper' => [], 'sheet' => []];

    private const CHAIN_ROOT = ['paper' => 'Paper', 'sheet' => 'Sheet'];

    private const LEVEL_LABEL = [1 => 'Kategori', 2 => 'Jenis', 3 => 'Gramatur/Spec', 4 => 'Ukuran (opsional)', 5 => 'Merek (opsional)'];

    public string $default_color = '';

    public string $default_tiers = '';

    public string $notes = '';

    // tanpa tipe: kotak angka bisa kosong sesaat saat diketik ulang
    public $sort_order = 999;

    public bool $activate = true;

    public $photo = null;

    public ?string $photo_path = null;

    // [['id' => ?int, 'label' => '', 'dims' => [param_key => value]]]
    public array $sizes = [];

    public function mount(?int $market = null): void
    {
        foreach (self::CHAIN_ROOT as $chain => $root) {
            $this->picks[$chain] = [1 => MatCategory::roots()->where('category_name', $root)->value('id_category'), 2 => null, 3 => null, 4 => null, 5 => null];
        }

        if (! $market) {
            $this->sort_order = $this->autoSortOrder();

            return;
        }

        $r = ProdMarket::with('sizes.values')->findOrFail($market);
        $this->recordId = $r->id_market;
        $this->fill($r->only(['market_code', 'market_label', 'photo_path']));
        $this->type_code = (string) $r->type_code;
        $this->parent_id = (string) $r->parent_id;
        foreach (['paper' => $r->paperCategory, 'sheet' => $r->sheetCategory] as $chain => $cat) {
            foreach ($cat?->ancestorChain() ?? [] as $node) {
                $this->picks[$chain][$node->level] = $node->id_category;
            }
        }
        $this->default_color = (string) $r->default_color;
        $this->default_tiers = (string) $r->default_tiers;
        $this->notes = (string) $r->notes;
        $this->sort_order = (int) $r->sort_order;
        $this->activate = (bool) $r->activate;
        $this->sizes = $r->sizes->map(fn ($s) => ['id' => $s->id_type_size, 'label' => $s->size_label,
            'dims' => $s->values->mapWithKeys(fn ($v) => [$v->param_key => $v->value_num !== null ? rtrim(rtrim((string) $v->value_num, '0'), '.') : ''])->all()])->all();
    }

    // Urutan tampil otomatis = urutan terakhir + 10 (record baru); officer tetap boleh mengubahnya.
    private function autoSortOrder(): int
    {
        return (int) ProdMarket::where('sort_order', '<', 999)->max('sort_order') + 10;
    }

    #[Computed]
    public function sortState(): ?string
    {
        return $this->recordId ? null : ((int) $this->sort_order === $this->autoSortOrder() ? 'auto' : 'override');
    }

    public function resetAuto(string $field): void
    {
        if ($field === 'sort_order' && ! $this->recordId) {
            $this->sort_order = $this->autoSortOrder();
        }
    }

    // Pilihan induk: Kategori Produk lain (bukan diri sendiri).
    #[Computed]
    public function groups(): array
    {
        return ProdMarket::whereNull('parent_id')->where('activate', true)->when($this->recordId, fn ($q) => $q->whereKeyNot($this->recordId))
            ->orderBy('sort_order')->orderBy('market_label')->pluck('market_label', 'id_market')->all();
    }

    public function isDetail(): bool
    {
        return $this->parent_id !== '';
    }

    #[Computed]
    public function types(): array
    {
        return ProdType::where('activate', true)->orderBy('type_name')->pluck('type_name', 'type_code')->all();
    }

    #[Computed]
    public function dimParams()
    {
        return ProdTypeParam::where('type_code', $this->type_code)->where('activate', true)
            ->whereIn('param_key', self::SIZE_KEYS)->orderBy('seq')->get(['param_key', 'param_label', 'unit']);
    }

    // [chain => [level => [id => label]]] -- anak dari pilihan level sebelumnya (dropdown bersambung).
    #[Computed]
    public function pickOptions(): array
    {
        $out = [];
        foreach ($this->picks as $chain => $p) {
            for ($lv = 2; $lv <= 5; $lv++) {
                $out[$chain][$lv] = $p[$lv - 1] ? MatCategory::where('parent_id', $p[$lv - 1])->where('activate', true)->orderBy('category_name')
                    ->get()->mapWithKeys(fn ($o) => [$o->id_category => $o->category_name.' ('.$o->prefix_char.')'])->all() : [];
            }
        }

        return $out;
    }

    public function levelLabel(int $level): string
    {
        return self::LEVEL_LABEL[$level] ?? 'Level '.$level;
    }

    public function setPick(string $chain, int $level, ?string $value): void
    {
        if (! isset($this->picks[$chain]) || $level < 2 || $level > 5) {
            return;
        }
        $this->picks[$chain][$level] = $value === '' || $value === null ? null : (int) $value;
        for ($lv = $level + 1; $lv <= 5; $lv++) {
            $this->picks[$chain][$lv] = null;
        }
        unset($this->pickOptions);
    }

    // Kategori terdalam yang dipilih. #1 Paper boleh berhenti di level 1 (kertas apa saja);
    // #2 Sheet baru dianggap diisi bila minimal jenisnya (level 2) dipilih -- produk tanpa sheet = kosong.
    private function deepest(string $chain): ?int
    {
        $ids = array_filter($this->picks[$chain]);
        $min = $chain === 'sheet' ? 2 : 1;

        return $ids && max(array_keys($ids)) >= $min ? $ids[max(array_keys($ids))] : null;
    }

    public function updatedTypeCode(): void
    {
        unset($this->dimParams);
    }

    public function addSize(): void
    {
        $this->sizes[] = ['id' => null, 'label' => '', 'dims' => []];
    }

    public function removeSize(int $i): void
    {
        unset($this->sizes[$i]);
        $this->sizes = array_values($this->sizes);
    }

    public function save(): void
    {
        $this->market_code = Str::upper(Str::slug($this->market_code ?: $this->market_label, '_'));

        $this->validate([
            'market_code' => ['required', 'max:40', Rule::unique(ProdMarket::class, 'market_code')->ignore($this->recordId, 'id_market')],
            'market_label' => 'required|max:100',
            'parent_id' => 'nullable|exists:tm2mst.prod_market,id_market',
            'type_code' => ($this->isDetail() ? 'required' : 'nullable').'|exists:tm2mst.prod_type,type_code',
            'default_color' => ['nullable', 'regex:/^\d\/\d$/'],
            'default_tiers' => ['nullable', 'regex:/^\s*\d[\d.]*(\s*,\s*\d[\d.]*)*\s*$/'],
            'notes' => 'nullable|max:500',
            'sort_order' => 'required|integer|min:0|max:65535',
            'photo' => 'nullable|image|max:4096',
            'sizes.*.label' => 'required|max:100',
            'sizes.*.dims.*' => 'nullable|numeric|min:0',
        ], [], ['sizes.*.label' => 'nama ukuran', 'default_color' => 'warna (mis. 4/0)', 'default_tiers' => 'jenjang qty (mis. 1000,3000,5000)']);

        $login = Auth::user()->login;

        DB::connection('tm2mst')->transaction(function () use ($login) {
            $data = [
                // Kategori (induk) hanya pengelompok: tanpa Tipe Produk, bahan, warna, jenjang qty, ukuran.
                'parent_id' => $this->isDetail() ? (int) $this->parent_id : null,
                'market_code' => $this->market_code, 'market_label' => trim($this->market_label), 'type_code' => $this->isDetail() ? $this->type_code : null,
                'paper_category_id' => $this->isDetail() ? $this->deepest('paper') : null, 'sheet_category_id' => $this->isDetail() ? $this->deepest('sheet') : null,
                'default_color' => $this->isDetail() ? ($this->default_color ?: null) : null,
                'default_tiers' => $this->isDetail() && $this->default_tiers ? preg_replace('/\s+/', '', $this->default_tiers) : null,
                'notes' => $this->notes ?: null, 'sort_order' => (int) $this->sort_order, 'activate' => $this->activate, 'updated_by' => $login,
            ];
            $market = $this->recordId ? tap(ProdMarket::findOrFail($this->recordId))->update($data) : ProdMarket::create($data + ['created_by' => $login]);
            $this->recordId = $market->id_market;

            if ($this->photo) {
                $old = $market->photo_path;
                $market->update(['photo_path' => $this->photo->store("product-market/{$market->id_market}", 'public')]);
                if ($old) {
                    Storage::disk('public')->delete($old);
                }
                $this->photo_path = $market->photo_path;
                $this->photo = null;
            }

            if (! $this->isDetail()) {
                $this->sizes = [];
            }
            $this->saveSizes($market, $login);
        });

        session()->flash('saved', t('Data saved.'));
        $this->redirect(route('lookup.product-market.edit', ['market' => $this->recordId]));
    }

    // Ukuran pasaran market ini ikut Tipe Produk market (type_code). Baris yang dihapus di layar dihapus juga.
    private function saveSizes(ProdMarket $market, string $login): void
    {
        $keep = [];

        foreach ($this->sizes as $i => $row) {
            $size = $row['id'] ? ProdTypeSize::where('market_id', $market->id_market)->findOrFail($row['id']) : new ProdTypeSize(['market_id' => $market->id_market, 'created_by' => $login]);
            $size->fill(['type_code' => $market->type_code, 'size_label' => trim($row['label']), 'sort_order' => ($i + 1) * 10, 'activate' => true, 'updated_by' => $login,
                'size_code' => rtrim(Str::upper(Str::limit(Str::slug($market->market_code.' '.$row['label'], '_'), 40, '')), '_')])->save();
            $keep[] = $size->id_type_size;

            $size->values()->delete();
            foreach ($this->dimParams as $p) {
                $v = $row['dims'][$p->param_key] ?? '';
                if ($v !== '' && $v !== null) {
                    ProdTypeSizeVal::create(['type_size_id' => $size->id_type_size, 'param_key' => $p->param_key, 'value_num' => $v]);
                }
            }
        }

        $gone = ProdTypeSize::where('market_id', $market->id_market)->whereNotIn('id_type_size', $keep)->pluck('id_type_size');
        ProdTypeSizeVal::whereIn('type_size_id', $gone)->delete();
        ProdTypeSize::whereIn('id_type_size', $gone)->delete();
    }
};
?>
<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">🏷️ Product Market</b>
        <span class="text-xs text-white/75">{{ $recordId ? $market_label : 'NEW' }}</span>
        <div class="ml-auto flex items-center gap-2">
            <x-ui.button preset="save" wire:click="save" type="button" />
            <x-back-button :fallback="route('lookup.product-market')" title="{{ t('Back to List') }}" />
        </div>
    </header>

    <div class="max-w-[1000px] mx-auto my-5 px-4">
        <x-alert.banner type="success" :message="session('saved')" />
        @if ($errors->any())
            <x-alert.banner type="danger" :message="$errors->first()" />
        @endif

        <div class="bg-white border border-field-border rounded-md p-4 mb-4">
            <h3 class="text-xs text-navy uppercase font-bold mb-3">1. Identitas</h3>
            <div class="flex gap-4 items-start">
                <div class="shrink-0 text-center">
                    @if ($photo)
                        <img src="{{ $photo->temporaryUrl() }}" alt="" class="w-28 h-28 object-cover rounded border border-field-border">
                    @elseif ($photo_path)
                        <img src="{{ asset('storage/'.$photo_path) }}" alt="" class="w-28 h-28 object-cover rounded border border-field-border">
                    @else
                        <span class="w-28 h-28 rounded border border-dashed border-field-border bg-accent-soft text-accent text-xs flex items-center justify-center">Belum ada foto</span>
                    @endif
                    <label class="block mt-1.5 text-[11px] text-accent font-semibold cursor-pointer">Ganti foto<input type="file" wire:model="photo" accept="image/*" class="hidden"></label>
                </div>
                <div class="flex-1 grid two">
                    <x-form.select label="Kategori Produk (induk)" wire:change="$set('parent_id', $event.target.value)" name="parent_id" :selected="$parent_id"
                        placeholder="— kosong = ini Kategori Produk —" :options="$this->groups" hint="Kosong = record ini jadi Kategori (mis. Dus Makanan). Isi = Detail Kategori (mis. PPL)." />
                    <x-form.input label="Nama (bahasa sales)" wire:model="market_label" name="market_label" placeholder="mis. Dus Makanan / Paper Lunch Box (PPL)" />
                    <x-form.input label="Kode" wire:model="market_code" name="market_code" placeholder="otomatis dari nama bila kosong" />
                    <div class="relative">
                        <x-form.input label="Urutan tampil" type="number" wire:model.live.debounce.400ms="sort_order" name="sort_order" hint="Otomatis setelah urutan terakhir; boleh diubah." />
                        <x-form.auto-chip field="sort_order" :state="$this->sortState" class="absolute top-0 right-0" />
                    </div>
                    <div class="flex items-end pb-1"><x-form.checkbox wire:model="activate" label="Aktif (muncul di Inquiry New)" /></div>
                </div>
            </div>
        </div>

        @if (! $this->isDetail())
            <x-alert.banner type="info" message="Ini Kategori Produk (pengelompok). Tipe Produk, bahan, dan ukuran diisi di Detail Kategori — buat record baru lalu pilih kategori ini sebagai induk." />
        @else
        <div class="bg-white border border-field-border rounded-md p-4 mb-4">
            <h3 class="text-xs text-navy uppercase font-bold mb-3">2. Nilai awal di Inquiry New</h3>
            <div class="grid two">
                <x-form.select label="Tipe Produk (dieline)" wire:change="$set('type_code', $event.target.value)" name="type_code" :selected="$type_code" :options="$this->types" />
                <x-form.select label="Warna cetak" wire:change="$set('default_color', $event.target.value)" name="default_color" :selected="$default_color"
                    :options="['1/0' => '1/0 (1 warna, 1 sisi)', '2/0' => '2/0', '4/0' => '4/0 (full color 1 sisi)', '4/1' => '4/1', '4/4' => '4/4 (full color 2 sisi)']" />
                <x-form.input label="Jenjang qty (dipisah koma)" :case-toggle="false" wire:model="default_tiers" name="default_tiers" placeholder="mis. 1000,3000,5000" />
            </div>
            @foreach (['paper' => '#1 Paper', 'sheet' => '#2 Sheet / SF'] as $chain => $title)
                <div class="border border-field-border rounded-md p-3 mt-3" wire:key="chain-{{ $chain }}">
                    <p class="text-[10px] font-bold text-accent uppercase mb-2">Bahan {{ $title }} <span class="normal-case font-normal text-muted">— hirarki 5 kategori bahan baku</span></p>
                    <div class="[display:grid] grid-cols-5 gap-2">
                        <x-form.input label="Kategori" readonly value="{{ $chain === 'paper' ? 'Paper (P)' : 'Sheet (S)' }}" class="bg-field-soft" />
                        @foreach ([2, 3, 4, 5] as $lv)
                            <x-form.select wire:key="pick-{{ $chain }}-{{ $lv }}" wire:change="setPick('{{ $chain }}', {{ $lv }}, $event.target.value)"
                                :label="$this->levelLabel($lv)" :disabled="! $picks[$chain][$lv - 1] || empty($this->pickOptions[$chain][$lv])" class="disabled:bg-field-soft"
                                :placeholder="$lv >= 3 ? '— semua —' : null" :selected="$picks[$chain][$lv]" :options="$this->pickOptions[$chain][$lv]" />
                        @endforeach
                    </div>
                </div>
            @endforeach
            <x-form.textarea label="Catatan untuk sales" wire:model="notes" rows="2" placeholder="mis. untuk makanan berminyak pakai kraft lapis PE" />
        </div>

        <div class="bg-white border border-field-border rounded-md mb-4 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 bg-field-soft border-b border-field-border">
                <h3 class="text-xs text-navy uppercase font-bold">3. Ukuran Pasaran</h3>
                <button type="button" wire:click="addSize" @disabled(! $type_code) class="text-[11px] font-bold text-accent bg-accent-soft border border-dashed border-accent rounded px-2.5 py-1 cursor-pointer disabled:opacity-40">＋ Tambah Ukuran</button>
            </div>
            @if (! $type_code)
                <p class="px-4 py-4 text-xs text-muted">Pilih Tipe Produk dulu — kolom dimensi mengikuti Tipe Produk.</p>
            @else
                <x-ui.table size="sm">
                    <thead>
                        <tr>
                            <th>Nama ukuran</th>
                            @foreach ($this->dimParams as $p)
                                <th class="text-right">{{ $p->param_label }} ({{ $p->unit ?: 'mm' }})</th>
                            @endforeach
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sizes as $i => $row)
                            <tr wire:key="size-{{ $i }}-{{ $row['id'] ?? 'n' }}">
                                <td><x-form.input wire:model="sizes.{{ $i }}.label" placeholder="mis. R8 (18 x 18 x 6,7 cm)" /></td>
                                @foreach ($this->dimParams as $p)
                                    <td><x-form.input type="number" step="0.1" wire:model="sizes.{{ $i }}.dims.{{ $p->param_key }}" class="text-right" /></td>
                                @endforeach
                                <td class="text-center"><x-grid.action-icon icon="fa-xmark" title="Hapus ukuran" action="removeSize({{ $i }})" variant="danger" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $this->dimParams->count() + 2 }}" class="text-center text-muted py-4">Belum ada ukuran. Klik “＋ Tambah Ukuran”.</td></tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            @endif
        </div>
        @endif
    </div>
</div>
