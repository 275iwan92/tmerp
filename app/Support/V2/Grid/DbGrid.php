<?php

namespace App\Support\V2\Grid;

use App\Support\V2\Src;
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
            default => 'Transaksi',
        };
    }

    public function search(): string
    {
        return 'Cari...';
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
            'detail' => $this->detail(),
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

    protected function sourceText(): string
    {
        return Src::of(...array_map(fn ($c) => ($m = new $c)->getConnectionName().'.'.$m->getTable(), $this->models()));
    }

    // Bentuk yang sama dengan Screens::screen() supaya route v2.screen & breadcrumb memakainya apa adanya.
    public function screen(): array
    {
        return ['code' => 'G-'.$this->slug(), 'slug' => $this->slug(), 'label' => $this->title(), 'icon' => $this->icon(), 'ref' => static::class,
            'mockup' => null, 'status' => 'review', 'url' => $this->url(), 'type' => 'grid', 'module' => $this->moduleLabel(),
            'module_code' => $this->module(), 'group' => $this->group(), 'view' => 'v2.screens._dbgrid', 'grid' => $this];
    }
}
