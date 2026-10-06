<?php

namespace App\Support\V2\Data;

use App\Models\Master\OrgCompany;
use App\Models\Sys\SysConstant;
use App\Support\Branding\CompanyLogo;
use App\Support\Branding\FontPresets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

// Setup > Identification (v2, pengganti pages::setup.identification). Dua kartu, aturan simpan sama dengan v1:
//   Aplikasi   -> sys_constant APP_NAME / APP_TAGLINE / APP_FONT_FAMILY / APP_LOGO_PATH (menu, topbar, login)
//   Perusahaan -> sys_constant COMPANY_NAME / COMPANY_LEGAL_NAME + org_company baris TM (kop cetakan) + CompanyLogo
final class Identification
{
    public static function app(): array
    {
        $logo = SysConstant::text('APP_LOGO_PATH');

        return ['name' => SysConstant::text('APP_NAME', 'ERP TM'), 'tagline' => SysConstant::text('APP_TAGLINE', ''),
            'font' => SysConstant::text('APP_FONT_FAMILY', 'system-ui'), 'logo' => $logo ? ['url' => asset('storage/'.$logo), 'name' => basename($logo)] : null];
    }

    public static function company(): array
    {
        $c = OrgCompany::where('company_code', 'TM')->first();
        $logo = fn (string $v) => CompanyLogo::url($v) ? ['url' => CompanyLogo::url($v), 'name' => basename((string) CompanyLogo::path($v))] : null;

        return ['name' => SysConstant::text('COMPANY_NAME', 'Telasih Makmur'), 'legal_name' => SysConstant::text('COMPANY_LEGAL_NAME', 'CV. Langgeng Jaya Makmur'),
            'npwp' => (string) $c?->npwp_number, 'address' => (string) $c?->address, 'phone' => (string) $c?->phone, 'email' => (string) $c?->email,
            'logo' => $logo('color'), 'logo_inverse' => $logo('inverse')];
    }

    public static function fonts(): array
    {
        return FontPresets::labels();
    }

    public static function saveApp(Request $r): void
    {
        $d = $r->validate(['app_name' => ['required', 'string', 'max:100'], 'app_tagline' => ['nullable', 'string', 'max:150'],
            'app_font' => ['required', 'string', 'in:'.implode(',', array_keys(FontPresets::labels()))], 'app_logo' => ['nullable', 'image', 'max:1024']],
            [], ['app_name' => t('Application name'), 'app_logo' => t('Application logo')]);

        self::set('APP_NAME', $d['app_name']);
        self::set('APP_TAGLINE', $d['app_tagline'] ?? '');
        self::set('APP_FONT_FAMILY', $d['app_font']);
        if ($r->hasFile('app_logo')) {
            if ($old = SysConstant::text('APP_LOGO_PATH')) {
                Storage::disk('public')->delete($old);
            }
            self::set('APP_LOGO_PATH', $r->file('app_logo')->store('branding', 'public'));
        }
    }

    public static function removeAppLogo(): void
    {
        if ($old = SysConstant::text('APP_LOGO_PATH')) {
            Storage::disk('public')->delete($old);
        }
        self::set('APP_LOGO_PATH', null);
    }

    public static function saveCompany(Request $r): void
    {
        $d = $r->validate(['company_name' => ['required', 'string', 'max:150'], 'company_legal_name' => ['required', 'string', 'max:150'],
            'npwp' => ['nullable', 'string', 'max:30'], 'address' => ['nullable', 'string', 'max:500'], 'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'], 'logo' => ['nullable', 'image', 'max:2048'], 'logo_inverse' => ['nullable', 'image', 'max:2048']],
            [], ['company_name' => t('Company name'), 'company_legal_name' => t('Registered name'), 'logo' => t('Company logo')]);
        $by = Auth::user()->login;

        foreach (['color' => 'logo', 'inverse' => 'logo_inverse'] as $variant => $field) {
            if ($r->hasFile($field)) {
                $f = $r->file($field);
                CompanyLogo::store($variant, $f->getRealPath(), $f->extension(), $by);
            }
        }
        self::set('COMPANY_NAME', trim($d['company_name']));
        self::set('COMPANY_LEGAL_NAME', trim($d['company_legal_name']));
        OrgCompany::where('company_code', 'TM')->update(['npwp_number' => trim((string) ($d['npwp'] ?? '')) ?: null,
            'address' => trim((string) ($d['address'] ?? '')) ?: null, 'phone' => trim((string) ($d['phone'] ?? '')) ?: null,
            'email' => trim((string) ($d['email'] ?? '')) ?: null, 'updated_by' => $by]);
    }

    public static function removeCompanyLogo(string $variant): void
    {
        abort_unless(in_array($variant, ['color', 'inverse'], true), 404);
        CompanyLogo::remove($variant, Auth::user()->login);
    }

    private static function set(string $key, ?string $value): void
    {
        SysConstant::updateOrCreate(['constant_key' => $key], ['constant_text' => $value, 'updated_by' => Auth::user()->login]);
    }
}
