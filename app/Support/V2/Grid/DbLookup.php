<?php

namespace App\Support\V2\Grid;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Grid Lookup (keluarga DevLib yang disetujui user 2026-09-30): daftar bantu kecil, diisi langsung di sel, Save ke database.
// Kunci kolom = nama kolom tabel; kolom 'activate' (type check) = Aktif (tanpa Hapus, cukup dinonaktifkan).
// Turunan cukup: slug/title/module/query()/columns() (+ rules() bila perlu aturan tambahan).
abstract class DbLookup extends DbGrid
{
    public const LOOKUP = 'lookup';

    public function family(): string
    {
        return self::LOOKUP;
    }

    public function group(): string
    {
        return 'Master';
    }

    // Aturan tambahan per kolom (di luar required/unique/number dari columns()).
    protected function rules(): array
    {
        return [];
    }

    // Rapikan kiriman satu baris sebelum divalidasi (mis. kode jadi huruf besar tanpa spasi tepi).
    protected function prepare(array $in): array
    {
        return $in;
    }

    // Aturan lintas kolom / lintas baris yang tidak bisa ditulis sebagai rule per kolom. Kembalian = pesan salah, null = lolos.
    protected function check(array $in, ?int $id): ?string
    {
        return null;
    }

    // Akibat simpan satu baris pada baris lain (mis. hanya satu baris aktif per jenis) -- masih di dalam transaksi yang sama.
    protected function saved(Model $row): void {}

    // Baris baru. Bawaan: model baru diisi kolom layar; turunan boleh membuatnya lewat service (mis. nomor otomatis, kolom turunan).
    protected function create(Model $model, array $data, ?string $login): Model
    {
        $row = $model->newInstance(['created_by' => $login] + $this->defaults());
        $row->fill($data + ['updated_by' => $login])->save();

        return $row;
    }

    // Nilai kolom wajib yang tidak tampil di sheet, hanya untuk baris baru (mis. company_id selama perusahaannya satu).
    protected function defaults(): array
    {
        return [];
    }

    protected function row(Model $m): array
    {
        $row = [];
        foreach ($this->columns() as $c) {
            $v = $m->getAttribute($c['key']);
            $row[$c['key']] = match ($c['type'] ?? 'text') {
                'check' => (bool) $v,
                'number' => $v === null ? null : (float) $v,
                default => $v,
            };
        }

        return $row;
    }

    public function props(): array
    {
        return ['rows' => $this->rows(), 'columns' => $this->typedColumns(), 'saveUrl' => route('v2.lookup.save', $this->slug()),
            'stats' => $this->stats(), 'source' => $this->sourceText()];
    }

    // Simpan baris yang berubah dari layar. Semua atau tidak sama sekali (satu transaksi); kembalian = isi terbaru.
    public function save(array $changed): array
    {
        $model = $this->query()->getModel();
        $cols = collect($this->columns())->reject(fn ($c) => ! empty($c['readonly']));
        $login = auth()->user()?->login;

        $model->getConnection()->transaction(function () use ($changed, $model, $cols, $login) {
            foreach ($changed as $i => $in) {
                $id = is_numeric($in['id'] ?? null) ? (int) $in['id'] : null;
                $in = $this->prepare($in);
                $rules = $cols->mapWithKeys(fn ($c) => [$c['key'] => array_values(array_filter([
                    ! empty($c['required']) ? 'required' : 'nullable',
                    ($c['type'] ?? 'text') === 'number' ? 'numeric' : null,
                    ($c['type'] ?? 'text') === 'check' ? 'boolean' : null,
                    ! empty($c['unique']) ? Rule::unique($model->getConnectionName().'.'.$model->getTable(), $c['key'])->ignore($id, $model->getKeyName()) : null,
                ]))])->all();
                $v = Validator::make($in, array_merge_recursive($rules, $this->rules()), [], $cols->mapWithKeys(fn ($c) => [$c['key'] => t($c['label'])])->all());
                if ($error = $v->fails() ? $v->errors()->first() : $this->check($in, $id)) {
                    throw ValidationException::withMessages(['row' => tf('Row :n: :error', ['n' => $i + 1, 'error' => $error])]);
                }
                // lockSaved = kolom kode yang sudah dirujuk data lain: hanya bisa diisi di baris baru (layar mengunci selnya,
                // server tetap mengabaikan kiriman untuk baris yang sudah tersimpan).
                $data = $cols->reject(fn ($c) => $id && ! empty($c['lockSaved']))
                    ->mapWithKeys(fn ($c) => [$c['key'] => ($in[$c['key']] ?? null) === '' ? null : ($in[$c['key']] ?? null)])->all();
                if ($id) {
                    $row = $model->newQuery()->findOrFail($id);
                    $row->fill($data + ['updated_by' => $login])->save();
                } else {
                    $row = $this->create($model, $data, $login);
                }
                $this->saved($row);
            }
        });

        return $this->rows();
    }
}
