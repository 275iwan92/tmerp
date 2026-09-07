<?php

use App\Models\Sys\SysConstant;
use App\Support\Branding\FontPresets;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

// Layar Identifikasi (dulu section pertama setup.configuration, dipecah jadi
// route sendiri 2026-09-07 -- docs/configurationstandard.md). Identitas
// APLIKASI (nama app/tagline/font/logo) -- BUKAN identitas perusahaan
// (org_company, dipakai kop cetak dokumen, punya layar/UI sendiri terpisah).
new #[Title('Identifikasi — TELASIH MAKMUR ERP')] class extends Component
{
    use WithFileUploads;

    public string $appName = '';

    public string $appTagline = '';

    public string $appFontKey = 'system-ui';

    public $logo = null;

    public ?string $currentLogoPath = null;

    public function mount(): void
    {
        $this->appName = SysConstant::text('APP_NAME', 'ERP TM');
        $this->appTagline = SysConstant::text('APP_TAGLINE', '');
        $this->appFontKey = SysConstant::text('APP_FONT_FAMILY', 'system-ui');
        $this->currentLogoPath = SysConstant::text('APP_LOGO_PATH');
    }

    public function fontPresetOptions(): array
    {
        return FontPresets::labels();
    }

    public function saveBranding(): void
    {
        $this->validate([
            'appName' => ['required', 'string', 'max:100'],
            'appTagline' => ['nullable', 'string', 'max:150'],
            'appFontKey' => ['required', 'string', 'in:'.implode(',', array_keys(FontPresets::labels()))],
            'logo' => ['nullable', 'image', 'max:1024'],
        ]);

        $this->setConstant('APP_NAME', $this->appName);
        $this->setConstant('APP_TAGLINE', $this->appTagline);
        $this->setConstant('APP_FONT_FAMILY', $this->appFontKey);

        if ($this->logo) {
            if ($this->currentLogoPath) {
                Storage::disk('public')->delete($this->currentLogoPath);
            }
            $path = $this->logo->store('branding', 'public');
            $this->setConstant('APP_LOGO_PATH', $path);
            $this->currentLogoPath = $path;
            $this->logo = null;
        }

        $this->dispatch('app-alert', type: 'success', message: 'Identitas aplikasi tersimpan.');
    }

    public function removeLogo(): void
    {
        if ($this->currentLogoPath) {
            Storage::disk('public')->delete($this->currentLogoPath);
        }
        $this->setConstant('APP_LOGO_PATH', null);
        $this->currentLogoPath = null;
        $this->dispatch('app-alert', type: 'info', message: 'Logo dihapus, kembali ke badge teks.');
    }

    protected function setConstant(string $key, ?string $value): void
    {
        SysConstant::updateOrCreate(
            ['constant_key' => $key],
            ['constant_text' => $value, 'updated_by' => Auth::user()->login]
        );
    }
};
?>

<div>
    <div class="max-w-[1100px] mx-auto my-5 px-4">

        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Identitas Aplikasi</div>
            <p class="text-[11px] text-muted mb-4">Nama, tagline, logo & font aplikasi — dipakai di topbar, halaman login, dan panel Filament /admin. Bukan nama company (itu tetap di Master Organization untuk struktur HR & kop dokumen cetak).</p>

            <form wire:submit="saveBranding" class="flex flex-col gap-3.5">
                <div class="grid two gap-3.5">
                    <x-form.input label="APP NAME *" wire:model="appName" name="appName" placeholder="mis. ERP TM" />
                    <x-form.input label="Tagline" wire:model="appTagline" name="appTagline" placeholder="mis. packaging solution" />
                </div>

                <div class="grid two gap-3.5 items-end">
                    <div>
                        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Font Aplikasi</label>
                        <select wire:model="appFontKey" class="w-full px-2 py-1.5 border border-[#c7cfda] rounded text-xs">
                            @foreach ($this->fontPresetOptions() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Logo Aplikasi</label>
                        <div class="flex items-center gap-3">
                            @if ($currentLogoPath)
                                <img src="{{ asset('storage/'.$currentLogoPath) }}" alt="Logo" class="w-10 h-10 rounded-md object-contain border border-[#e2e8f0] bg-[#f8fafc]">
                            @else
                                <div class="w-10 h-10 rounded-md border border-dashed border-[#c7cfda] flex items-center justify-center text-[10px] text-muted">Tanpa Logo</div>
                            @endif
                            <input type="file" wire:model="logo" accept="image/*" class="text-[11px]">
                            @if ($currentLogoPath)
                                <button type="button" wire:click="removeLogo" wire:confirm="Hapus logo, kembali ke badge teks?"
                                    class="text-[10.5px] font-semibold text-red-600 cursor-pointer">Hapus</button>
                            @endif
                        </div>
                        @error('logo') <span class="text-[10.5px] text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">💾 Simpan Identitas Aplikasi</button>
                </div>
            </form>
        </div>

    </div>
</div>
