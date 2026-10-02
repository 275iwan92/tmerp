<?php

namespace App\Livewire\Concerns;

use App\Models\Master\ProdProcessMachine;
use App\Services\Production\MachineCapabilityService;
use Illuminate\Support\Facades\Auth;

// CRUD matriks kapabilitas Proses x Mesin, dipakai DUA arah oleh 2 layar:
//   - Master Proses  -> panel "Mesin yang bisa mengerjakan proses ini"
//   - Master Mesin   -> panel "Proses yang bisa dijalankan di mesin ini"
// Logikanya identik (baris yang sama, cuma sisi yang ditampilkan beda), jadi
// ditaruh di trait supaya aturan preference_rank & override tidak bercabang.
trait ManagesProcessMachineCapability
{
    // Baris yang sedang dibuka panel parameternya (NULL = tidak ada).
    public ?int $capEditingId = null;

    public array $capForm = [
        'setup_minutes' => '',
        'speed_per_hour' => '',
        'waste_pct' => '',
        'waste_fixed_qty' => '',
        'hourly_rate' => '',
        'manpower' => '',
        'gsm_min' => '',
        'gsm_max' => '',
        'note' => '',
    ];

    // Sisi seberang yang mau dipasangkan (id proses ATAU id mesin, tergantung layar).
    public ?int $capPickId = null;

    protected function capabilityService(): MachineCapabilityService
    {
        return app(MachineCapabilityService::class);
    }

    // Layar wajib memberi tahu sisi mana yang sedang dibuka.
    abstract protected function capabilityOwner(): array; // ['process_id' => int] | ['machine_id' => int]

    public function capAdd(): void
    {
        $owner = $this->capabilityOwner();

        if (! $this->capPickId || ! $owner) {
            return;
        }

        $keys = array_key_exists('process_id', $owner)
            ? ['process_id' => $owner['process_id'], 'machine_id' => (int) $this->capPickId]
            : ['process_id' => (int) $this->capPickId, 'machine_id' => $owner['machine_id']];

        // Pasangan yang pernah dinonaktifkan dihidupkan ulang, bukan dibuat
        // ganda -- UNIQUE(process_id, machine_id) memang melarang duplikat, dan
        // baris lama menyimpan parameter yang sudah pernah disetel officer.
        $existing = ProdProcessMachine::where($keys)->first();

        if ($existing) {
            $existing->update(['activate' => true, 'updated_by' => Auth::user()->login]);
        } else {
            ProdProcessMachine::create($keys + [
                'preference_rank' => 2,
                'is_certified' => false,
                'activate' => true,
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
            ]);
        }

        $this->capPickId = null;
        $this->refreshCapabilityViews();
    }

    public function capRemove(int $capabilityId): void
    {
        // Nonaktifkan, JANGAN delete: baris ini sumber parameter yang mungkin
        // sudah dipakai routing/WO lama. Hard delete memutus jejak kenapa
        // sebuah WO dulu memakai mesin itu.
        ProdProcessMachine::whereKey($capabilityId)
            ->update(['activate' => false, 'updated_by' => Auth::user()->login]);

        if ($this->capEditingId === $capabilityId) {
            $this->capEditingId = null;
        }

        $this->refreshCapabilityViews();
    }

    public function capRestore(int $capabilityId): void
    {
        ProdProcessMachine::whereKey($capabilityId)
            ->update(['activate' => true, 'updated_by' => Auth::user()->login]);

        $this->refreshCapabilityViews();
    }

    // Mesin utama untuk satu proses boleh lebih dari satu (2 mesin kembar),
    // jadi ini toggle, bukan radio -- tidak ada penurunan otomatis baris lain.
    public function capTogglePrimary(int $capabilityId): void
    {
        $cap = ProdProcessMachine::findOrFail($capabilityId);
        $cap->preference_rank = $cap->preference_rank === 1 ? 2 : 1;
        $cap->updated_by = Auth::user()->login;
        $cap->save();

        $this->refreshCapabilityViews();
    }

    public function capToggleCertified(int $capabilityId): void
    {
        $cap = ProdProcessMachine::findOrFail($capabilityId);
        $cap->is_certified = ! $cap->is_certified;
        $cap->updated_by = Auth::user()->login;
        $cap->save();

        $this->refreshCapabilityViews();
    }

    public function capEdit(int $capabilityId): void
    {
        $cap = ProdProcessMachine::findOrFail($capabilityId);
        $this->capEditingId = $capabilityId;

        foreach (array_keys($this->capForm) as $field) {
            // String kosong, bukan 0 -- kosong berarti "warisi default proses".
            $this->capForm[$field] = $cap->{$field} === null ? '' : (string) $cap->{$field};
        }
    }

    public function capCancelEdit(): void
    {
        $this->capEditingId = null;
    }

    public function capSaveParams(): void
    {
        if (! $this->capEditingId) {
            return;
        }

        $data = $this->validate([
            'capForm.setup_minutes' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'capForm.speed_per_hour' => ['nullable', 'numeric', 'min:0'],
            'capForm.waste_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'capForm.waste_fixed_qty' => ['nullable', 'integer', 'min:0'],
            'capForm.hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'capForm.manpower' => ['nullable', 'integer', 'min:0', 'max:255'],
            'capForm.gsm_min' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'capForm.gsm_max' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'capForm.note' => ['nullable', 'string', 'max:255'],
        ])['capForm'];

        if ($data['gsm_min'] !== '' && $data['gsm_max'] !== '' && (int) $data['gsm_min'] > (int) $data['gsm_max']) {
            $this->addError('capForm.gsm_max', t('Maximum grammage must be >= minimum.'));

            return;
        }

        ProdProcessMachine::whereKey($this->capEditingId)->update(
            collect($data)->map(fn ($v) => $v === '' ? null : $v)->all()
            + ['updated_by' => Auth::user()->login]
        );

        $this->capEditingId = null;
        $this->refreshCapabilityViews();
    }

    // Layar menimpa ini untuk membuang cache #[Computed]-nya sendiri.
    abstract protected function refreshCapabilityViews(): void;
}
