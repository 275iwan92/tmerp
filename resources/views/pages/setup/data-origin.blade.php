<?php

use App\Services\Data\DataOriginService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Setting Data Dummy & Legacy (BACKLOG §18.7, user 2026-09-17): admin
// menentukan data DUMMY / LEGACY ditampilkan atau disembunyikan, mengatur
// aturan klasifikasi, dan me-reset data dummy ke keadaan semula (snapshot)
// setelah UAT. Menyembunyikan != menghapus. Route dijaga middleware admin.
new #[Title('Dummy & Legacy Data — TELASIH MAKMUR ERP')] class extends Component
{
    public bool $showDummy = true;

    public bool $showLegacy = true;

    public bool $newAsDummy = false;

    public string $flash = '';

    public string $dbFilter = '';

    public ?array $classifyPreview = null;

    public ?array $resetPreview = null;

    public bool $removeNew = false;

    // Form aturan
    public ?int $ruleId = null;

    public bool $ruleForm = false;

    public string $match_type = 'CREATED_BY';

    public string $db_name = '';

    public string $table_name = '';

    public string $column_name = '';

    public string $match_value = '';

    public string $origin = 'DUMMY';

    public $priority = 100;

    public string $note = '';

    public const TYPES = [
        'CREATED_BY' => 'Pembuat (created_by) cocok pola',
        'COLUMN_LIKE' => 'Nilai kolom cocok pola',
        'COLUMN_FILLED' => 'Kolom terisi',
    ];

    public function mount(): void
    {
        $svc = app(DataOriginService::class);
        $hidden = $svc->hiddenOrigins();
        $this->showDummy = ! in_array('DUMMY', $hidden, true);
        $this->showLegacy = ! in_array('LEGACY', $hidden, true);
        $this->newAsDummy = $svc->newRowsAsDummy();
    }

    private function svc(): DataOriginService
    {
        return app(DataOriginService::class);
    }

    #[Computed]
    public function counts(): array
    {
        return $this->svc()->counts();
    }

    #[Computed]
    public function totals(): array
    {
        $t = ['LIVE' => 0, 'DUMMY' => 0, 'LEGACY' => 0];
        foreach ($this->counts as $c) {
            foreach ($t as $k => $v) {
                $t[$k] += $c[$k];
            }
        }

        return $t;
    }

    #[Computed]
    public function marked(): array
    {
        return collect($this->counts)
            ->filter(fn ($c, $k) => ($c['DUMMY'] || $c['LEGACY']) && ($this->dbFilter === '' || str_starts_with($k, $this->dbFilter.'.')))
            ->sortByDesc(fn ($c) => $c['DUMMY'] + $c['LEGACY'])
            ->all();
    }

    #[Computed]
    public function rules()
    {
        return DB::connection('tm2sys')->table('sys_data_origin_rule')->orderBy('priority')->orderBy('id_rule')->get();
    }

    #[Computed]
    public function snapshot(): array
    {
        return $this->svc()->snapshotInfo();
    }

    public function saveSettings(): void
    {
        foreach (['DATA_SHOW_DUMMY' => $this->showDummy, 'DATA_SHOW_LEGACY' => $this->showLegacy, 'DATA_NEW_AS_DUMMY' => $this->newAsDummy] as $key => $val) {
            DB::connection('tm2sys')->table('sys_constant')->where('constant_key', $key)->update([
                'constant_number' => $val ? 1 : 0, 'updated_by' => Auth::user()->login, 'updated_at' => now(),
            ]);
        }

        $this->flash = 'Setting tersimpan — berlaku untuk halaman yang dibuka sesudah ini.';
    }

    // ---------- aturan ----------

    public function openRule(?int $id = null): void
    {
        $r = $id ? DB::connection('tm2sys')->table('sys_data_origin_rule')->where('id_rule', $id)->first() : null;
        $this->ruleId = $id;
        $this->match_type = $r->match_type ?? 'CREATED_BY';
        $this->db_name = $r->db_name ?? '';
        $this->table_name = $r->table_name ?? '';
        $this->column_name = $r->column_name ?? '';
        $this->match_value = $r->match_value ?? '';
        $this->origin = $r->origin ?? 'DUMMY';
        $this->priority = $r->priority ?? ((int) $this->rules->max('priority') + 10);
        $this->note = $r->note ?? '';
        $this->resetErrorBag();
        $this->ruleForm = true;
    }

    public function saveRule(): void
    {
        $data = $this->validate([
            'match_type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'db_name' => ['nullable', 'in:'.implode(',', DataOriginService::DATABASES)],
            'table_name' => ['nullable', 'regex:/^\w+$/', 'max:64'],
            'column_name' => ['nullable', 'required_unless:match_type,CREATED_BY', 'regex:/^\w+$/', 'max:64'],
            'match_value' => ['nullable', 'required_unless:match_type,COLUMN_FILLED', 'string', 'max:100'],
            'origin' => ['required', 'in:DUMMY,LEGACY'],
            'priority' => ['required', 'integer', 'min:0', 'max:9999'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $blank = fn ($v) => ($v = trim((string) $v)) === '' ? null : $v;
        $payload = [
            'match_type' => $data['match_type'],
            'db_name' => $blank($data['db_name']),
            'table_name' => $blank($data['table_name']),
            'column_name' => $data['match_type'] === 'CREATED_BY' ? null : $blank($data['column_name']),
            'match_value' => $data['match_type'] === 'COLUMN_FILLED' ? null : $blank($data['match_value']),
            'origin' => $data['origin'],
            'priority' => (int) $data['priority'],
            'note' => $blank($data['note']),
            'updated_at' => now(),
            'updated_by' => Auth::user()->login,
        ];

        $t = DB::connection('tm2sys')->table('sys_data_origin_rule');
        $this->ruleId
            ? $t->where('id_rule', $this->ruleId)->update($payload)
            : $t->insert($payload + ['activate' => 1, 'created_at' => now(), 'created_by' => Auth::user()->login]);

        $this->ruleForm = false;
        $this->classifyPreview = null;
        unset($this->rules);
    }

    public function toggleRule(int $id): void
    {
        DB::connection('tm2sys')->table('sys_data_origin_rule')->where('id_rule', $id)->update([
            'activate' => DB::raw('1 - activate'), 'updated_at' => now(), 'updated_by' => Auth::user()->login,
        ]);
        $this->classifyPreview = null;
        unset($this->rules);
    }

    public function previewClassify(): void
    {
        $this->classifyPreview = $this->svc()->classify(true);
    }

    public function applyClassify(): void
    {
        $done = $this->svc()->classify(false);
        $n = array_sum(array_map(fn ($c) => $c['DUMMY'] + $c['LEGACY'], $done));
        $this->classifyPreview = null;
        $this->flash = "Klasifikasi diterapkan: {$n} baris ditandai. Ambil snapshot ulang bila data DUMMY bertambah.";
        unset($this->counts, $this->totals, $this->marked);
    }

    // ---------- snapshot & reset ----------

    public function takeSnapshot(): void
    {
        $n = $this->svc()->snapshot(Auth::user()->login);
        $this->resetPreview = null;
        $this->flash = "Snapshot tersimpan: {$n} baris DUMMY menjadi keadaan semula untuk reset.";
        unset($this->snapshot);
    }

    public function previewReset(): void
    {
        $this->resetPreview = $this->svc()->reset(true, $this->removeNew);
    }

    public function applyReset(): void
    {
        $r = $this->svc()->reset(false, $this->removeNew);
        $this->resetPreview = null;
        $this->flash = "Reset selesai: {$r['restored']} baris dikembalikan, {$r['changed']} baris dipulihkan isinya"
            .($this->removeNew ? ", {$r['new']} baris dummy baru dihapus." : '.');
        Cache::forget('data_origin.tables');
        unset($this->counts, $this->totals, $this->marked);
    }
};
?>

<div>
    <div class="max-w-[1200px] mx-auto my-5 px-4 flex flex-col gap-4">

        @if ($flash)
            <div class="px-4 py-2.5 rounded-md bg-[#eaf7ee] border border-[#bfe6cc] text-[#1f6b3c] text-[12.5px]">{{ $flash }}</div>
        @endif

        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="active" label="Baris LIVE" :value="number_format($this->totals['LIVE'], 0, ',', '.')" />
            <x-grid.stat-box tone="warn" label="Baris DUMMY" :value="number_format($this->totals['DUMMY'], 0, ',', '.')" />
            <x-grid.stat-box tone="total" label="Baris LEGACY" :value="number_format($this->totals['LEGACY'], 0, ',', '.')" />
        </x-grid.stat-row>

        {{-- 1. Setting tampilan --}}
        <div class="bg-white border border-[#d9dfe8] rounded-[10px] p-4">
            <div class="text-sm font-bold text-navy mb-1">Tampilan Data</div>
            <div class="text-[12px] text-muted mb-3">Menyembunyikan tidak menghapus data. Berlaku di semua layar yang membaca lewat model aplikasi.</div>
            <div class="flex flex-col gap-2">
                <x-form.checkbox label="Tampilkan data DUMMY (demo / UAT)" wire:model="showDummy" />
                <x-form.checkbox label="Tampilkan data LEGACY (migrasi sistem lama)" wire:model.live="showLegacy" />
                @unless ($showLegacy)
                    <div class="text-[12px] px-3 py-2 rounded bg-[#fff4e0] border border-[#f1d39a] text-[#8a5a00]">
                        ⚠ Menyembunyikan LEGACY akan menyembunyikan {{ number_format($this->totals['LEGACY'], 0, ',', '.') }} baris — termasuk sebagian besar
                        Master Bahan, Pelanggan, Supplier, Produk, dan karyawan hasil impor. Pastikan data kerja sudah tidak bergantung pada data itu.
                    </div>
                @endunless
                <x-form.checkbox label="Mode UAT: data baru otomatis ditandai DUMMY (ikut di-reset)" wire:model="newAsDummy" />
            </div>
            <div class="mt-3">
                <button type="button" wire:click="saveSettings" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
            </div>
        </div>

        {{-- 2. Snapshot & reset --}}
        <div class="bg-white border border-[#d9dfe8] rounded-[10px] p-4">
            <div class="text-sm font-bold text-navy mb-1">Reset Data Dummy</div>
            <div class="text-[12px] text-muted mb-3">
                Snapshot = keadaan semula data DUMMY. Reset mengembalikan baris dummy yang terhapus atau berubah saat UAT ke isi snapshot.
                Snapshot terakhir: <b>{{ number_format($this->snapshot['rows'], 0, ',', '.') }}</b> baris
                @if ($this->snapshot['at']) · {{ $this->snapshot['at'] }} oleh {{ $this->snapshot['by'] }} @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="takeSnapshot" wire:confirm="Ganti snapshot dengan keadaan data DUMMY saat ini?"
                    class="px-3.5 py-1.5 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">📸 Ambil Snapshot Ulang</button>
                <button type="button" wire:click="previewReset" class="px-3.5 py-1.5 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">🔍 Pratinjau Reset</button>
                <label class="flex items-center gap-1.5 text-[12px] ml-2">
                    <input type="checkbox" wire:model.live="removeNew"> Hapus juga data DUMMY yang dibuat sesudah snapshot
                </label>
            </div>
            @if ($resetPreview)
                <div class="mt-3 text-[12px]">
                    Akan dikembalikan: <b>{{ $resetPreview['restored'] }}</b> baris hilang/berubah flag,
                    <b>{{ $resetPreview['changed'] }}</b> baris berubah isi,
                    data dummy baru: <b>{{ $resetPreview['new'] }}</b>{{ $removeNew ? ' (akan dihapus)' : ' (dibiarkan)' }}.
                    @if ($resetPreview['tables'])
                        <table class="w-full mt-2 border-collapse text-[12px]">
                            <tr class="text-left text-[11px] text-muted uppercase"><th class="py-1">Tabel</th><th>Dikembalikan</th><th>Berubah</th><th>Baru</th></tr>
                            @foreach ($resetPreview['tables'] as $tbl => $r)
                                <tr class="border-t border-[#eef1f5]"><td class="py-1 font-mono">{{ $tbl }}</td><td>{{ $r['restored'] }}</td><td>{{ $r['changed'] }}</td><td>{{ $r['new'] }}</td></tr>
                            @endforeach
                        </table>
                        <button type="button" wire:click="applyReset" wire:confirm="Reset data DUMMY ke keadaan snapshot sekarang?"
                            class="mt-3 px-4 py-2 rounded text-xs font-semibold bg-[#b45309] text-white cursor-pointer">↺ Reset Sekarang</button>
                    @else
                        <div class="mt-2 text-[#1f6b3c]">Data dummy sudah sama dengan snapshot — tidak ada yang perlu di-reset.</div>
                    @endif
                </div>
            @endif
        </div>

        {{-- 3. Aturan klasifikasi --}}
        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="text-sm font-bold text-navy">Aturan Klasifikasi</div>
                <div class="text-[11.5px] text-muted">Hanya menaikkan LIVE → DUMMY/LEGACY. Anak dari baris DUMMY (relasi) ikut DUMMY.</div>
                <x-slot:newRecord>
                    <div class="flex gap-1.5">
                        <button type="button" wire:click="previewClassify" class="px-3 py-1.5 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">🔍 Pratinjau</button>
                        <button type="button" wire:click="openRule" class="px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                    </div>
                </x-slot:newRecord>
            </x-grid.toolbar>

            @if ($ruleForm)
                <form wire:submit="saveRule" class="p-4 bg-[#fafbfd] border-b border-[#eef1f5] grid grid-cols-1 md:grid-cols-4 gap-3">
                    <x-form.select :selected="$match_type" :liveSync="true" label="Jenis *" name="match_type" wire:model.live="match_type" :options="self::TYPES" />
                    <x-form.select :selected="$db_name" :liveSync="true" label="Database" name="db_name" wire:model="db_name" placeholder="Semua database"
                        :options="array_combine(DataOriginService::DATABASES, DataOriginService::DATABASES)" />
                    <x-form.input label="Tabel" name="table_name" wire:model="table_name" placeholder="kosong = semua tabel" />
                    @if ($match_type !== 'CREATED_BY')
                        <x-form.input label="Kolom *" name="column_name" wire:model="column_name" placeholder="mis. inquiry_no" />
                    @endif
                    @if ($match_type !== 'COLUMN_FILLED')
                        <x-form.input label="Pola (LIKE, % = bebas) *" name="match_value" wire:model="match_value" placeholder="mis. IQ2690%" />
                    @endif
                    <x-form.select label="Tandai sebagai *" name="origin" wire:model="origin" :options="['DUMMY' => 'DUMMY', 'LEGACY' => 'LEGACY']" />
                    <x-form.input label="Prioritas" name="priority" type="number" wire:model="priority" />
                    <x-form.input label="Catatan" name="note" wire:model="note" />
                    <div class="md:col-span-4 flex gap-2">
                        <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                        <button type="button" wire:click="$set('ruleForm', false)" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Batal</button>
                    </div>
                </form>
            @endif

            @if ($classifyPreview !== null)
                <div class="p-4 border-b border-[#eef1f5] text-[12px]">
                    @if ($classifyPreview)
                        Akan ditandai:
                        @foreach ($classifyPreview as $tbl => $c)
                            <span class="inline-block mr-3"><span class="font-mono">{{ $tbl }}</span> · D {{ $c['DUMMY'] }} · L {{ $c['LEGACY'] }}</span>
                        @endforeach
                        <div class="mt-2">
                            <button type="button" wire:click="applyClassify" wire:confirm="Terapkan klasifikasi ini?"
                                class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">Terapkan</button>
                        </div>
                    @else
                        <span class="text-[#1f6b3c]">Tidak ada baris LIVE yang cocok dengan aturan — semua sudah terklasifikasi.</span>
                    @endif
                </div>
            @endif

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr class="[&>th]:text-left [&>th]:px-3 [&>th]:py-2 [&>th]:bg-[#fafbfd] [&>th]:text-[#667] [&>th]:text-[11px] [&>th]:uppercase [&>th]:border-b [&>th]:border-[#eef1f5]">
                            <th class="w-[60px]">Prio</th><th>Jenis</th><th>Lingkup</th><th>Pola</th><th class="w-[90px]">Tandai</th><th>Catatan</th><th class="w-[80px]">Status</th><th class="w-[90px] !text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->rules as $r)
                            <tr wire:key="rule-{{ $r->id_rule }}" wire:dblclick="openRule({{ $r->id_rule }})"
                                class="hover:bg-[#f7faff] cursor-pointer [&>td]:px-3 [&>td]:py-2 [&>td]:border-b [&>td]:border-[#eef1f5] {{ $r->activate ? '' : 'opacity-50' }}">
                                <td class="font-mono">{{ $r->priority }}</td>
                                <td>{{ self::TYPES[$r->match_type] ?? $r->match_type }}</td>
                                <td class="font-mono text-[11.5px]">{{ $r->db_name ?: '*' }}.{{ $r->table_name ?: '*' }}{{ $r->column_name ? '.'.$r->column_name : '' }}</td>
                                <td class="font-mono text-[11.5px]">{{ $r->match_value ?? '(terisi)' }}</td>
                                <td><span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $r->origin === 'DUMMY' ? 'bg-[#fff4e0] text-[#b45309]' : 'bg-[#e8eefb] text-[#2c3e69]' }}">{{ $r->origin }}</span></td>
                                <td class="text-[11.5px] text-muted">{{ $r->note }}</td>
                                <td><span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $r->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $r->activate ? 'AKTIF' : 'NONAKTIF' }}</span></td>
                                <td class="text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="✎" title="Edit" action="openRule({{ $r->id_rule }})" />
                                    <x-grid.action-icon icon="⏻" :title="$r->activate ? 'Nonaktifkan' : 'Aktifkan'" action="toggleRule({{ $r->id_rule }})"
                                        :variant="$r->activate ? 'danger' : 'default'" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>

        {{-- 4. Ringkasan per tabel --}}
        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="text-sm font-bold text-navy">Tabel yang Memuat Data DUMMY / LEGACY</div>
                <select wire:model.live="dbFilter" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    <option value="">Semua database</option>
                    @foreach (DataOriginService::DATABASES as $d)
                        <option value="{{ $d }}">{{ $d }}</option>
                    @endforeach
                </select>
            </x-grid.toolbar>
            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr class="[&>th]:text-left [&>th]:px-3 [&>th]:py-2 [&>th]:bg-[#fafbfd] [&>th]:text-[#667] [&>th]:text-[11px] [&>th]:uppercase [&>th]:border-b [&>th]:border-[#eef1f5]">
                            <th>Tabel</th><th class="!text-right">LIVE</th><th class="!text-right">DUMMY</th><th class="!text-right">LEGACY</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->marked as $tbl => $c)
                            <tr class="[&>td]:px-3 [&>td]:py-1.5 [&>td]:border-b [&>td]:border-[#eef1f5]">
                                <td class="font-mono text-[11.5px]">{{ $tbl }}</td>
                                <td class="text-right">{{ number_format($c['LIVE'], 0, ',', '.') }}</td>
                                <td class="text-right {{ $c['DUMMY'] ? 'font-bold text-[#b45309]' : 'text-muted' }}">{{ number_format($c['DUMMY'], 0, ',', '.') }}</td>
                                <td class="text-right {{ $c['LEGACY'] ? 'font-bold text-[#2c3e69]' : 'text-muted' }}">{{ number_format($c['LEGACY'], 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="4" message="Tidak ada data DUMMY / LEGACY." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>
</div>
