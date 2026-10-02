<?php

namespace App\Support\V2\Grid;

use App\Support\V2\Src;
use Carbon\CarbonImmutable;
use Devlib\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

// Grid mockup.test yang datanya SELALU dari database (user 2026-09-30: "pakai object oriented, semua grid ambil
// datanya dari database"). Satu turunan = satu layar grid; turunan hanya mendeklarasikan query, kolom, dan pemetaan
// baris. Tampilan & perilaku = DevLib x-dl::grid keluarga yang disepakati (Grid Master / Grid Transactional / Report).
// Buka/Edit/New sementara ke form v1 lewat ?open={id} / ?new=1 (App\Livewire\Concerns\OpensFromQuery) sampai
// Single Form v2 disetujui user.
abstract class DbGrid
{
    public const MASTER = 'master';

    public const TRANSACTIONAL = 'transactional';

    public const REPORT = 'report';

    protected int $limit = 5000;

    abstract public function slug(): string;

    abstract public function title(): string;

    // Kode modul sec_module (HRM, SALES, ...) = hak akses layar.
    abstract public function module(): string;

    abstract public function family(): string;

    abstract protected function query(): Builder;

    abstract public function columns(): array;

    abstract protected function row(Model $m): array;

    // Model sumber data (tooltip Src "tersambung ke mana"); koneksi & tabel dibaca dari modelnya, bukan ditulis ulang.
    protected function models(): array
    {
        return [get_class($this->query()->getModel())];
    }

    public function icon(): string
    {
        return $this->family() === self::MASTER ? 'fa-solid fa-address-book' : 'fa-solid fa-table-list';
    }

    public function moduleLabel(): string
    {
        return $this->module();
    }

    public function group(): string
    {
        return match ($this->family()) {
            self::MASTER => 'Master',
            self::REPORT => 'Report',
            default => t('Transaction'),
        };
    }

    public function search(): string
    {
        return 'Search...';
    }

    public function filters(): array
    {
        return [];
    }

    // Kolom rahasia untuk user yang sedang login (mis. harga bagi produksi/purchasing): kolom DAN nilainya dibuang
    // di server, bukan sekadar disembunyikan di layar.
    protected function restrictedKeys(): array
    {
        return [];
    }

    // Panduan singkat di atas grid (mis. cara memakai tombol Kolom di laporan). null = tanpa panduan.
    public function guide(): ?string
    {
        return null;
    }

    // Grid Report: pilihan Grup (kolom => label) + grup awal (varian Group By, subtotal kolom 'sum').
    public function groups(): array
    {
        return [];
    }

    public function groupBy(): ?string
    {
        return null;
    }

    // Grid Transactional (user 2026-10-02): baris yang dibuka = tabel anak (paling banyak 7 kolom) yang SELALU memuat nama
    // item / product. Bentuk = prop detail x-dl::grid: ['key' => kolom array di row(), 'title', 'columns' => [...]]. [] = tanpa Expand.
    public function detail(): array
    {
        return [];
    }

    // Baris berwarna (rowTone x-dl::grid): nama kolom di row() yang membawa tone (danger, tangerine, ...). null = baris biasa.
    public function rowTone(): ?string
    {
        return null;
    }

    // Tombol di baris (kolom type button, mis. Print): aksi => alamat berpola {id}, dibuka di tab baru oleh layar grid generik.
    public function actionUrls(): array
    {
        return [];
    }

    // Kolom tombol Print + alamatnya, untuk grid yang dokumennya punya cetakan (route v2.print).
    protected function printColumn(): array
    {
        return ['key' => 'print', 'label' => 'Print', 'type' => 'button', 'text' => 'Print', 'action' => 'print', 'sortable' => false];
    }

    protected function printUrl(string $type): string
    {
        return str_replace('987654321', '{id}', route('v2.print', ['type' => $type, 'id' => 987654321]));
    }

    // Laporan berperiode (mis. Stock Card): [tanggal awal, tanggal akhir] bawaan; null = laporan tanpa periode. Layar grid
    // generik menampilkan pilihan tanggal di atas grid dan memuat ulang dengan ?from=&to= (tanggal ISO).
    /** @return array{0: CarbonImmutable, 1: CarbonImmutable}|null */
    protected function defaultPeriod(): ?array
    {
        return null;
    }

    /** @var array{0: CarbonImmutable, 1: CarbonImmutable}|null periode yang dipasang pemanggil (test, cetakan) */
    private ?array $periodSet = null;

    public function between(CarbonImmutable $from, CarbonImmutable $to): static
    {
        $this->periodSet = $from->lessThanOrEqualTo($to) ? [$from->startOfDay(), $to->startOfDay()] : [$to->startOfDay(), $from->startOfDay()];

        return $this;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable}|null periode yang berlaku: pilihan user (?from=&to=) atau bawaan laporan */
    public function period(): ?array
    {
        $default = $this->defaultPeriod();
        if (! $default) {
            return null;
        }
        if ($this->periodSet) {
            return $this->periodSet;
        }
        $pick = function (string $key, CarbonImmutable $fallback): CarbonImmutable {
            $value = (string) request()->query($key);

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false ? CarbonImmutable::parse($value)->startOfDay() : $fallback->startOfDay();
        };
        [$from, $to] = [$pick('from', $default[0]), $pick('to', $default[1])];

        return $from->lessThanOrEqualTo($to) ? [$from, $to] : [$to, $from];
    }

    // Saringan teks di bilah periode (kunci query => label), mis. ['item' => 'Item']: dipakai laporan yang perlu dipersempit di
    // server (cetakan kartu satu item). Nilainya dibaca lewat param().
    public function params(): array
    {
        return [];
    }

    /** @var array<string, string> nilai saringan yang dipasang pemanggil (test) */
    private array $paramSet = [];

    public function with(array $params): static
    {
        $this->paramSet = array_map(fn ($v) => trim((string) $v), $params);

        return $this;
    }

    public function param(string $key): string
    {
        return $this->paramSet[$key] ?? (isset($this->params()[$key]) ? trim((string) request()->query($key)) : '');
    }

    // Cetakan laporan (x-dl::report, route v2.report.print): isi yang sama dengan grid di layar. false = tanpa tombol Print.
    public function printable(): bool
    {
        return false;
    }

    // Ringkasan di atas cetakan: [['label', 'sum' => kolom | 'value' => angka, 'signed' => bool]].
    public function printSummary(): array
    {
        return [];
    }

    // Tanda tangan di bawah cetakan: [[judul, jabatan]].
    public function printSignatures(): array
    {
        return [['Prepared By', 'Warehouse'], ['Checked By', 'Head of Warehouse']];
    }

    // Isi cetakan: kolom grid yang tampil (identity = nama + kode di baris kedua, tanggal sudah diformat), baris, kriteria.
    /** @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, criteria: array<string, string>} */
    public function printData(): array
    {
        $dates = [];
        $columns = [];
        foreach ($this->columns() as $c) {
            if (in_array($c['key'], $this->restrictedKeys(), true) || ($c['visible'] ?? true) === false) {
                continue;
            }
            $type = $c['type'] ?? 'text';
            if ($type === 'date') {
                $dates[] = $c['key'];
            }
            $columns[] = array_filter(['key' => $c['key'], 'label' => $c['label'], 'type' => in_array($type, ['number', 'money', 'badge'], true) ? $type : 'text',
                'sub' => $type === 'identity' ? ($c['code'] ?? null) : null, 'sum' => $c['sum'] ?? null, 'tones' => $c['tones'] ?? null], fn ($v) => $v !== null);
        }
        $rows = array_map(function (array $r) use ($dates) {
            foreach ($dates as $key) {
                $r[$key] = empty($r[$key]) ? '' : Locale::date($r[$key]);
            }

            return $r;
        }, $this->rows());
        $period = $this->period();
        $criteria = $period ? ['Period' => Locale::date($period[0]).' — '.Locale::date($period[1])] : ['As Of' => Locale::date(now())];
        foreach ($this->params() as $key => $label) {
            if ($this->param($key) !== '') {
                $criteria[$label] = $this->param($key);
            }
        }

        return ['columns' => $columns, 'rows' => array_values($rows), 'criteria' => $criteria + ['Rows' => Locale::number(count($rows))]];
    }

    public function printUrlOf(): ?string
    {
        if (! $this->printable()) {
            return null;
        }
        $period = $this->period();

        $params = array_filter(array_map(fn (string $key) => $this->param($key), array_combine(array_keys($this->params()), array_keys($this->params()))), fn ($v) => $v !== '');

        return route('v2.report.print', ['slug' => $this->slug()] + ($period ? ['from' => $period[0]->toDateString(), 'to' => $period[1]->toDateString()] : []) + $params);
    }

    public function stats(): array
    {
        return $this->family() === self::MASTER ? [
            ['label' => 'All'],
            ['label' => 'Active', 'where' => ['active' => true], 'tone' => 'success'],
            ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'danger'],
        ] : [];
    }

    // Layar v1 yang memegang form & aturan simpan (route name). null = grid hanya-baca.
    protected function v1Route(): ?string
    {
        return null;
    }

    // Komponen v1 yang aturannya dipakai ulang (toggle aktif, dsb) — satu sumber kebenaran dengan v1.
    protected function v1Component(): ?string
    {
        return null;
    }

    protected function canCreate(): bool
    {
        return $this->family() !== self::REPORT && $this->v1Route() !== null;
    }

    protected string $activeColumn = 'activate';

    // Lookup lintas-database satu kali per halaman (pola 1 CLAUDE.md §2), dipanggil sebelum row().
    protected function prefetch(Collection $items): void {}

    public function rows(): array
    {
        $items = $this->query()->limit($this->limit)->get();
        $this->prefetch($items);

        return $items->map(function (Model $m) {
            $row = ['id' => $m->getKey()] + $this->row($m);
            if ($this->family() === self::MASTER) {
                $row['active'] = (bool) $m->getAttribute($this->activeColumn);
            }

            return $row;
        })->all();
    }

    // Aktif/Nonaktif Grid Master. Pakai aturan v1 bila ada (mis. nonaktif pegawai ikut mengunci akun), selain itu balik kolom.
    public function toggle(int $id): bool
    {
        $m = $this->query()->getModel()->newQuery()->findOrFail($id);
        $v1 = $this->v1Component() ? app('livewire')->new($this->v1Component()) : null;
        if ($v1 instanceof Component && method_exists($v1, 'toggleActive')) {
            $v1->toggleActive($id);
        } else {
            $m->setAttribute($this->activeColumn, ! $m->getAttribute($this->activeColumn));
            if ($m->getConnection()->getSchemaBuilder()->hasColumn($m->getTable(), 'updated_by')) {
                $m->setAttribute('updated_by', auth()->user()?->login);
            }
            $m->save();
        }

        return (bool) $m->newQuery()->whereKey($id)->value($this->activeColumn);
    }

    // Alamat buka baris ({id}). Bawaan: layar v1 dengan ?open={id}.
    public function openUrl(): ?string
    {
        return $this->v1Route() ? route($this->v1Route()).'?open={id}' : null;
    }

    // Alamat + New Record. Bawaan: layar v1 dengan ?new=1.
    public function newUrl(): ?string
    {
        return $this->canCreate() ? route($this->v1Route()).'?new=1' : null;
    }

    // Route v1 yang parameternya id baris (mis. master.warehouse.edit {warehouse}) -> alamat berpola {id}.
    protected function routeUrl(string $name, string $param): string
    {
        return str_replace('987654321', '{id}', route($name, [$param => 987654321]));
    }

    public function url(): string
    {
        return route('v2.screen', $this->slug());
    }

    // Isi atribut x-dl::grid.
    public function props(): array
    {

        return [
            'id' => $this->slug(),
            'rows' => array_map(fn ($r) => array_diff_key($r, array_flip($this->restrictedKeys())), $this->rows()),
            'columns' => array_values(array_filter($this->columns(), fn ($c) => ! in_array($c['key'], $this->restrictedKeys(), true))),
            'stats' => $this->stats(),
            'filters' => $this->filters(),
            'groups' => $this->groups(),
            'groupBy' => $this->groupBy(),
            'detail' => $this->detailTexts(),
            'rowTone' => $this->rowTone(),
            'search' => $this->search(),
            'mode' => $this->family() === self::REPORT ? 'report' : 'entry',
            'actions' => match ($this->family()) {
                self::MASTER => 'master',
                self::TRANSACTIONAL => $this->openUrl() ? 'worklist' : 'none',
                default => 'none',
            },
            'activeKey' => $this->family() === self::MASTER ? 'active' : null,
            'newRecord' => $this->newUrl() ? '+ New Record' : null,
            'newUrl' => $this->newUrl(),
            'openUrl' => $this->openUrl(),
            'toggleUrl' => $this->family() === self::MASTER ? str_replace('__ID__', '{id}', route('v2.grid.toggle', [$this->slug(), '__ID__'])) : null,
            'source' => $this->sourceText(),
        ];
    }

    // x-dl::grid tidak menerjemahkan prop detail: judul & label kolom tabel anak diterjemahkan di sini (turunan cukup menulis English).
    private function detailTexts(): array
    {
        $d = $this->detail();
        if (isset($d['title'])) {
            $d['title'] = t($d['title']);
        }
        if (isset($d['columns'])) {
            $d['columns'] = array_map(fn (array $c) => isset($c['label']) ? array_replace($c, ['label' => t($c['label'])]) : $c, $d['columns']);
        }

        return $d;
    }

    protected function sourceText(): string
    {
        return Src::of(...array_map(fn ($c) => ($m = new $c)->getConnectionName().'.'.$m->getTable(), $this->models()));
    }

    public function sourceLabel(): string
    {
        return $this->sourceText();
    }

    // Bentuk yang sama dengan Screens::screen() supaya route v2.screen & breadcrumb memakainya apa adanya.
    public function screen(): array
    {
        return ['code' => 'G-'.$this->slug(), 'slug' => $this->slug(), 'label' => $this->title(), 'icon' => $this->icon(), 'ref' => static::class,
            'mockup' => null, 'status' => 'review', 'url' => $this->url(), 'type' => 'grid', 'module' => $this->moduleLabel(),
            'module_code' => $this->module(), 'group' => $this->group(), 'view' => 'v2.screens._dbgrid', 'grid' => $this,
            'filters' => $this->defaultPeriod() !== null || $this->params() !== []];
    }
}
