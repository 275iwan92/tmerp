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
        return ['rows' => $this->rows(), 'columns' => $this->columns(), 'saveUrl' => route('v2.lookup.save', $this->slug()),
            'source' => $this->sourceText()];
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
                $rules = $cols->mapWithKeys(fn ($c) => [$c['key'] => array_values(array_filter([
                    ! empty($c['required']) ? 'required' : 'nullable',
                    ($c['type'] ?? 'text') === 'number' ? 'numeric' : null,
                    ($c['type'] ?? 'text') === 'check' ? 'boolean' : null,
                    ! empty($c['unique']) ? Rule::unique($model->getConnectionName().'.'.$model->getTable(), $c['key'])->ignore($id, $model->getKeyName()) : null,
                ]))])->all();
                $v = Validator::make($in, array_merge_recursive($rules, $this->rules()), [], $cols->pluck('label', 'key')->all());
                if ($v->fails()) {
                    throw ValidationException::withMessages(['row' => 'Baris '.($i + 1).': '.$v->errors()->first()]);
                }
                $data = $cols->mapWithKeys(fn ($c) => [$c['key'] => $in[$c['key']] === '' ? null : $in[$c['key']]])->all();
                $row = $id ? $model->newQuery()->findOrFail($id) : $model->newInstance(['created_by' => $login]);
                $row->fill($data + ['updated_by' => $login])->save();
            }
        });

        return $this->rows();
    }
}
