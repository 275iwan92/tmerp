<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Master\OrgCompany;
use App\Models\Sys\SysConstant;
use App\Support\Branding\CompanyLogo;
use App\Support\Branding\FontPresets;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

// Setup > Identification (v2): identitas APLIKASI (nama, tagline, font, logo -> sys_constant APP_*) dan identitas PERUSAHAAN
// (nama -> sys_constant COMPANY_*, NPWP / alamat / telepon / email -> org_company TM, logo -> CompanyLogo). Semua yang diisi
// dipakai aplikasi dan tercetak di kop dokumen (aturan "semua data punya inputan"). Aturan sama dengan layar v1.
final class Identification
{
    private const COMPANY_CODE = 'TM';

    /** @return array<string, mixed> */
    public static function app(): array
    {
        return ['app_name' => SysConstant::text('APP_NAME', 'ERP TM'), 'app_tagline' => SysConstant::text('APP_TAGLINE', ''),
            'app_font' => SysConstant::text('APP_FONT_FAMILY', 'system-ui'), 'logo_path' => SysConstant::text('APP_LOGO_PATH')];
    }

    /** @return array<string, mixed> */
    public static function company(): array
    {
        $c = OrgCompany::where('company_code', self::COMPANY_CODE)->first();

        return ['name' => SysConstant::text('COMPANY_NAME', 'Telasih Makmur'), 'legal_name' => SysConstant::text('COMPANY_LEGAL_NAME', 'CV. Langgeng Jaya Makmur'),
            'npwp' => (string) $c?->npwp_number, 'address' => (string) $c?->address, 'phone' => (string) $c?->phone, 'email' => (string) $c?->email];
    }

    public static function fontOptions(): array
    {
        return FontPresets::labels();
    }

    public static function saveApp(array $in, ?UploadedFile $logo, string $by): void
    {
        $d = Validator::make($in, ['app_name' => ['required', 'string', 'max:100'], 'app_tagline' => ['nullable', 'string', 'max:150'],
            'app_font' => ['required', 'in:'.implode(',', array_keys(FontPresets::labels()))]])->validate();
        Validator::make(['logo' => $logo], ['logo' => ['nullable', 'image', 'max:1024']])->validate();

        self::set('APP_NAME', $d['app_name'], $by);
        self::set('APP_TAGLINE', $d['app_tagline'] ?? null, $by);
        self::set('APP_FONT_FAMILY', $d['app_font'], $by);
        if ($logo) {
            self::deleteAppLogo();
            self::set('APP_LOGO_PATH', $logo->store('branding', 'public'), $by);
        }
    }

    public static function removeAppLogo(string $by): void
    {
        self::deleteAppLogo();
        self::set('APP_LOGO_PATH', null, $by);
    }

    public static function saveCompany(array $in, ?UploadedFile $color, ?UploadedFile $inverse, string $by): void
    {
        $d = Validator::make($in + ['logo_color' => $color, 'logo_inverse' => $inverse], [
            'name' => ['required', 'string', 'max:150'], 'legal_name' => ['required', 'string', 'max:150'], 'npwp' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'], 'phone' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email', 'max:100'],
            'logo_color' => ['nullable', 'image', 'max:2048'], 'logo_inverse' => ['nullable', 'image', 'max:2048'],
        ])->validate();

        foreach (['color' => $color, 'inverse' => $inverse] as $variant => $file) {
            if ($file) {
                CompanyLogo::store($variant, $file->getRealPath(), $file->extension(), $by);
            }
        }
        self::set('COMPANY_NAME', trim($d['name']), $by);
        self::set('COMPANY_LEGAL_NAME', trim($d['legal_name']), $by);
        OrgCompany::where('company_code', self::COMPANY_CODE)->update([
            'npwp_number' => trim((string) ($d['npwp'] ?? '')) ?: null, 'address' => trim((string) ($d['address'] ?? '')) ?: null,
            'phone' => trim((string) ($d['phone'] ?? '')) ?: null, 'email' => trim((string) ($d['email'] ?? '')) ?: null, 'updated_by' => $by]);
    }

    public static function removeCompanyLogo(string $variant, string $by): void
    {
        abort_unless(in_array($variant, ['color', 'inverse'], true), 404);
        CompanyLogo::remove($variant, $by);
    }

    private static function deleteAppLogo(): void
    {
        if ($path = SysConstant::text('APP_LOGO_PATH')) {
            Storage::disk('public')->delete($path);
        }
    }

    private static function set(string $key, ?string $value, string $by): void
    {
        SysConstant::updateOrCreate(['constant_key' => $key], ['constant_text' => $value, 'updated_by' => $by]);
    }
}
