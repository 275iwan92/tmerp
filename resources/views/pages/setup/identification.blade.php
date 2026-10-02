<?php

use App\Models\Master\OrgCompany;
use App\Models\Sys\SysConstant;
use App\Support\Branding\CompanyLogo;
use App\Support\Branding\FontPresets;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

// Layar Identifikasi (dulu section pertama setup.configuration, dipecah jadi
// route sendiri 2026-09-07 -- docs/configurationstandard.md). Identitas
// APLIKASI (nama app/tagline/font/logo), plus kartu Identitas PERUSAHAAN
// (2026-09-23, keputusan user: admin harus bisa mengisi sendiri & semua yang
// diisi tercetak di kop): nama perusahaan & nama terdaftar -> sys_constant
// COMPANY_NAME / COMPANY_LEGAL_NAME; NPWP/alamat/telepon/email -> org_company
// baris TM (sumber kop x-print.header). Lihat configurationstandard.md §3.1.
new #[Title('Identifikasi — TELASIH MAKMUR ERP')] class extends Component
{
    use WithFileUploads;

    public string $appName = '';

    public string $appTagline = '';

    public string $appFontKey = 'system-ui';

    public $logo = null;

    public ?string $currentLogoPath = null;

    public string $companyName = '';

    public string $companyLegalName = '';

    public string $companyNpwp = '';

    public string $companyAddress = '';

    public string $companyPhone = '';

    public string $companyEmail = '';

    // Logo PERUSAHAAN (kop cetakan) -- terpisah dari logo aplikasi di atas (App\Support\Branding\CompanyLogo).
    public $companyLogo = null;

    public $companyLogoInverse = null;

    public function mount(): void
    {
        $this->appName = SysConstant::text('APP_NAME', 'ERP TM');
        $this->appTagline = SysConstant::text('APP_TAGLINE', '');
        $this->appFontKey = SysConstant::text('APP_FONT_FAMILY', 'system-ui');
        $this->currentLogoPath = SysConstant::text('APP_LOGO_PATH');

        $company = OrgCompany::where('company_code', 'TM')->first();
        $this->companyName = SysConstant::text('COMPANY_NAME', 'Telasih Makmur');
        $this->companyLegalName = SysConstant::text('COMPANY_LEGAL_NAME', 'CV. Langgeng Jaya Makmur');
        $this->companyNpwp = (string) $company?->npwp_number;
        $this->companyAddress = (string) $company?->address;
        $this->companyPhone = (string) $company?->phone;
        $this->companyEmail = (string) $company?->email;
    }

    public function saveCompany(): void
    {
        $this->validate([
            'companyName' => ['required', 'string', 'max:150'],
            'companyLegalName' => ['required', 'string', 'max:150'],
            'companyNpwp' => ['nullable', 'string', 'max:30'],
            'companyAddress' => ['nullable', 'string', 'max:500'],
            'companyPhone' => ['nullable', 'string', 'max:50'],
            'companyEmail' => ['nullable', 'email', 'max:100'],
            'companyLogo' => ['nullable', 'image', 'max:2048'],
            'companyLogoInverse' => ['nullable', 'image', 'max:2048'],
        ]);

        foreach (['color' => 'companyLogo', 'inverse' => 'companyLogoInverse'] as $variant => $prop) {
            if ($this->{$prop}) {
                CompanyLogo::store($variant, $this->{$prop}->getRealPath(), $this->{$prop}->extension(), Auth::user()->login);
                $this->{$prop} = null;
            }
        }

        $this->setConstant('COMPANY_NAME', trim($this->companyName));
        $this->setConstant('COMPANY_LEGAL_NAME', trim($this->companyLegalName));

        OrgCompany::where('company_code', 'TM')->update([
            'npwp_number' => trim($this->companyNpwp) ?: null,
            'address' => trim($this->companyAddress) ?: null,
            'phone' => trim($this->companyPhone) ?: null,
            'email' => trim($this->companyEmail) ?: null,
            'updated_by' => Auth::user()->login,
        ]);

        $this->dispatch('app-alert', type: 'success', message: 'Identitas perusahaan tersimpan.');
    }

    public function removeCompanyLogo(string $variant): void
    {
        abort_unless(in_array($variant, ['color', 'inverse'], true), 404);
        CompanyLogo::remove($variant, Auth::user()->login);
        $this->dispatch('app-alert', type: 'info', message: 'Logo perusahaan dihapus.');
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
            <p class="text-[11px] text-muted mb-4">Nama, tagline, logo & font aplikasi — dipakai di menu, topbar, dan halaman login. Bukan nama / logo perusahaan (itu di kartu Identitas Perusahaan di bawah, untuk kop dokumen cetak).</p>

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

        <x-ui.card variant="panel" size="none" class="mt-4 p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Identitas Perusahaan</div>
            <p class="text-[11px] text-muted mb-4">Dipakai di seluruh aplikasi dan kop semua dokumen cetak. Nama Terdaftar + NPWP hanya muncul saat dokumen ber-PPN dicetak dengan pilihan "PPN".</p>

            <form wire:submit="saveCompany" class="flex flex-col gap-3.5">
                <div class="grid two gap-3.5">
                    <x-form.input label="Nama Perusahaan *" wire:model="companyName" name="companyName" placeholder="mis. Telasih Makmur" hint="Default di aplikasi & cetakan (Non PPN)" />
                    <x-form.input label="Nama Terdaftar (Badan Hukum) *" wire:model="companyLegalName" name="companyLegalName" placeholder="mis. CV. Langgeng Jaya Makmur" hint="Kop cetakan dokumen PPN" />
                </div>
                <div class="grid two gap-3.5">
                    <x-form.input label="NPWP" wire:model="companyNpwp" name="companyNpwp" :case-toggle="false" placeholder="mis. 01.234.567.8-901.000" hint="Tercetak a.n. Nama Terdaftar pada dokumen PPN" />
                    <x-form.input label="Telepon" wire:model="companyPhone" name="companyPhone" :case-toggle="false" placeholder="mis. (031) 1234567" />
                </div>
                <div class="grid two gap-3.5">
                    <x-form.textarea label="Alamat" wire:model="companyAddress" name="companyAddress" autogrow placeholder="Alamat lengkap untuk kop dokumen" />
                    <x-form.input label="Email" type="email" wire:model="companyEmail" name="companyEmail" placeholder="mis. info@perusahaan.co.id" />
                </div>
                {{-- Logo perusahaan: kop cetakan (warna -> cetak warna, hitam-putih dibuat otomatis -> cetak hitam-putih). Bukan logo aplikasi. --}}
                <div class="grid two gap-3.5">
                    <div class="flex flex-col gap-2">
                        <x-form.file-dropzone label="Logo Perusahaan (latar terang)" wire:model="companyLogo" name="companyLogo" accept="image/*"
                            hint="Dipakai di kop semua cetakan. Versi hitam-putih dibuat otomatis untuk cetakan hitam-putih. PNG latar transparan/putih, maks 2 MB." />
                        @error('companyLogo') <span class="text-[10.5px] text-red-600">{{ $message }}</span> @enderror
                        @if (CompanyLogo::url())
                            <div class="flex items-center gap-3 flex-wrap">
                                <img src="{{ CompanyLogo::url() }}" alt="Logo perusahaan" class="h-8 max-w-full rounded border border-field-border bg-white p-1">
                                <img src="{{ CompanyLogo::url('bw') }}" alt="Logo perusahaan hitam-putih" class="h-8 max-w-full rounded border border-field-border bg-white p-1">
                                <x-ui.button variant="link" size="xs" wire:click="removeCompanyLogo('color')" wire:confirm="Hapus logo perusahaan? Kop cetakan kembali ke nama teks.">Hapus</x-ui.button>
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-col gap-2">
                        <x-form.file-dropzone label="Logo Perusahaan (latar gelap)" wire:model="companyLogoInverse" name="companyLogoInverse" accept="image/*"
                            hint="Versi tulisan putih, untuk dipasang di atas latar gelap. PNG latar transparan, maks 2 MB." />
                        @error('companyLogoInverse') <span class="text-[10.5px] text-red-600">{{ $message }}</span> @enderror
                        @if (CompanyLogo::url('inverse'))
                            <div class="flex items-center gap-3 flex-wrap">
                                <img src="{{ CompanyLogo::url('inverse') }}" alt="Logo perusahaan tulisan putih" class="h-8 max-w-full rounded bg-navy p-1">
                                <x-ui.button variant="link" size="xs" wire:click="removeCompanyLogo('inverse')" wire:confirm="Hapus logo perusahaan versi latar gelap?">Hapus</x-ui.button>
                            </div>
                        @endif
                    </div>
                </div>
                <x-ui.action-bar variant="inline">
                    <x-ui.button preset="save" />
                </x-ui.action-bar>
            </form>
        </x-ui.card>

    </div>
</div>
