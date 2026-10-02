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
new #[Title('Identification — TELASIH MAKMUR ERP')] class extends Component
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

        $this->dispatch('app-alert', type: 'success', message: t('Company identity saved.'));
    }

    public function removeCompanyLogo(string $variant): void
    {
        abort_unless(in_array($variant, ['color', 'inverse'], true), 404);
        CompanyLogo::remove($variant, Auth::user()->login);
        $this->dispatch('app-alert', type: 'info', message: t('Company logo removed.'));
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

        $this->dispatch('app-alert', type: 'success', message: t('Application identity saved.'));
    }

    public function removeLogo(): void
    {
        if ($this->currentLogoPath) {
            Storage::disk('public')->delete($this->currentLogoPath);
        }
        $this->setConstant('APP_LOGO_PATH', null);
        $this->currentLogoPath = null;
        $this->dispatch('app-alert', type: 'info', message: t('Logo removed, back to the text badge.'));
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
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">{{ t('Application Identity') }}</div>
            <p class="text-[11px] text-muted mb-4">{{ t('Application name, tagline, logo & font — used in the menu, topbar and login page.') }} {{ t('Not the company name / logo (that is in the Company Identity card below, for the printed document letterhead).') }}</p>

            <form wire:submit="saveBranding" class="flex flex-col gap-3.5">
                <div class="grid two gap-3.5">
                    <x-form.input :label="t('APP NAME').' *'" wire:model="appName" name="appName" :placeholder="t('e.g. ERP TM')" />
                    <x-form.input :label="t('Tagline')" wire:model="appTagline" name="appTagline" :placeholder="t('e.g. packaging solution')" />
                </div>

                <div class="grid two gap-3.5 items-end">
                    <div>
                        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ t('Application Font') }}</label>
                        <select wire:model="appFontKey" class="w-full px-2 py-1.5 border border-[#c7cfda] rounded text-xs">
                            @foreach ($this->fontPresetOptions() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ t('Application Logo') }}</label>
                        <div class="flex items-center gap-3">
                            @if ($currentLogoPath)
                                <img src="{{ asset('storage/'.$currentLogoPath) }}" alt="{{ t('Logo') }}" class="w-10 h-10 rounded-md object-contain border border-[#e2e8f0] bg-[#f8fafc]">
                            @else
                                <div class="w-10 h-10 rounded-md border border-dashed border-[#c7cfda] flex items-center justify-center text-[10px] text-muted">{{ t('No Logo') }}</div>
                            @endif
                            <input type="file" wire:model="logo" accept="image/*" class="text-[11px]">
                            @if ($currentLogoPath)
                                <button type="button" wire:click="removeLogo" wire:confirm="{{ t('Remove the logo and go back to the text badge?') }}"
                                    class="text-[10.5px] font-semibold text-red-600 cursor-pointer">{{ t('Remove') }}</button>
                            @endif
                        </div>
                        @error('logo') <span class="text-[10.5px] text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">💾 {{ t('Save Application Identity') }}</button>
                </div>
            </form>
        </div>

        <x-ui.card variant="panel" size="none" class="mt-4 p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">{{ t('Company Identity') }}</div>
            <p class="text-[11px] text-muted mb-4">{{ t('Used throughout the application and on the letterhead of every printed document.') }} {{ t('Registered Name + NPWP only appear when a VAT document is printed with the "PPN" option.') }}</p>

            <form wire:submit="saveCompany" class="flex flex-col gap-3.5">
                <div class="grid two gap-3.5">
                    <x-form.input :label="t('Company Name').' *'" wire:model="companyName" name="companyName" :placeholder="t('e.g. Telasih Makmur')" :hint="t('Default in the application & printouts (Non VAT)')" />
                    <x-form.input :label="t('Registered Name (Legal Entity)').' *'" wire:model="companyLegalName" name="companyLegalName" :placeholder="t('e.g. CV. Langgeng Jaya Makmur')" :hint="t('Letterhead of VAT document printouts')" />
                </div>
                <div class="grid two gap-3.5">
                    <x-form.input :label="t('NPWP')" wire:model="companyNpwp" name="companyNpwp" :case-toggle="false" :placeholder="t('e.g. 01.234.567.8-901.000')" :hint="t('Printed under the Registered Name on VAT documents')" />
                    <x-form.input :label="t('Phone')" wire:model="companyPhone" name="companyPhone" :case-toggle="false" :placeholder="t('e.g. (031) 1234567')" />
                </div>
                <div class="grid two gap-3.5">
                    <x-form.textarea :label="t('Address')" wire:model="companyAddress" name="companyAddress" autogrow :placeholder="t('Full address for the document letterhead')" />
                    <x-form.input :label="t('Email')" type="email" wire:model="companyEmail" name="companyEmail" :placeholder="t('e.g. info@company.co.id')" />
                </div>
                {{-- Logo perusahaan: kop cetakan (warna -> cetak warna, hitam-putih dibuat otomatis -> cetak hitam-putih). Bukan logo aplikasi. --}}
                <div class="grid two gap-3.5">
                    <div class="flex flex-col gap-2">
                        <x-form.file-dropzone :label="t('Company Logo (light background)')" wire:model="companyLogo" name="companyLogo" accept="image/*"
                            :hint="t('Used on the letterhead of every printout. A black-and-white version is made automatically for black-and-white printouts.').' '.t('PNG with transparent/white background, max 2 MB.')" />
                        @error('companyLogo') <span class="text-[10.5px] text-red-600">{{ $message }}</span> @enderror
                        @if (CompanyLogo::url())
                            <div class="flex items-center gap-3 flex-wrap">
                                <img src="{{ CompanyLogo::url() }}" alt="{{ t('Company logo') }}" class="h-8 max-w-full rounded border border-field-border bg-white p-1">
                                <img src="{{ CompanyLogo::url('bw') }}" alt="{{ t('Company logo black-and-white') }}" class="h-8 max-w-full rounded border border-field-border bg-white p-1">
                                <x-ui.button variant="link" size="xs" wire:click="removeCompanyLogo('color')" wire:confirm="{{ t('Remove the company logo? The printout letterhead goes back to the text name.') }}">{{ t('Remove') }}</x-ui.button>
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-col gap-2">
                        <x-form.file-dropzone :label="t('Company Logo (dark background)')" wire:model="companyLogoInverse" name="companyLogoInverse" accept="image/*"
                            :hint="t('White-lettering version, for placing on a dark background. PNG with transparent background, max 2 MB.')" />
                        @error('companyLogoInverse') <span class="text-[10.5px] text-red-600">{{ $message }}</span> @enderror
                        @if (CompanyLogo::url('inverse'))
                            <div class="flex items-center gap-3 flex-wrap">
                                <img src="{{ CompanyLogo::url('inverse') }}" alt="{{ t('Company logo white lettering') }}" class="h-8 max-w-full rounded bg-navy p-1">
                                <x-ui.button variant="link" size="xs" wire:click="removeCompanyLogo('inverse')" wire:confirm="{{ t('Remove the dark-background version of the company logo?') }}">{{ t('Remove') }}</x-ui.button>
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
