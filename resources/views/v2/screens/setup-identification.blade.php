{{-- Setup > Identification (v2). Data & aturan = App\Support\V2\Data\Identification. Identitas aplikasi (menu, topbar, login) dan
     identitas perusahaan (kop semua cetakan). Logo aplikasi TIDAK sama dengan logo perusahaan. Administrator saja. --}}
@php
    use App\Support\Branding\CompanyLogo;
    use App\Support\V2\Data\Identification;
    use App\Support\V2\Src;

    $app = Identification::app();
    $co = Identification::company();
    $appLogo = $app['logo_path'] ? asset('storage/'.$app['logo_path']) : null;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::card title="Application Identity" description="Name, tagline, logo and font of the application: used in the menu, top bar and login page. This is not the company name or logo (see Company Identity below).">
    <x-dl::brand tone="dark" :name="raw($app['app_name'])" :tagline="raw((string) $app['app_tagline'])" :logo="$appLogo" />
    <x-dl::action-form action="{{ route('v2.identification.app') }}" enctype="multipart/form-data">
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="app_name" label="App Name" required :value="old('app_name', $app['app_name'])" :source="Src::of('tm2sys.sys_constant.APP_NAME')" />
            <x-dl::field.text name="app_tagline" label="Tagline" :value="old('app_tagline', $app['app_tagline'])" :source="Src::of('tm2sys.sys_constant.APP_TAGLINE')" />
            <x-dl::field.select name="app_font" label="Application Font" required :options="Identification::fontOptions()" :value="old('app_font', $app['app_font'])" :placeholder="null" :source="Src::of('tm2sys.sys_constant.APP_FONT_FAMILY')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.upload name="logo" label="Application Logo" accept="image/*" hint="PNG or JPG, up to 1 MB." :source="Src::of('tm2sys.sys_constant.APP_LOGO_PATH')" />
        </x-dl::form.row>
        <x-dl::action-bar><x-dl::button preset="save" /></x-dl::action-bar>
    </x-dl::action-form>
    @if ($appLogo)
        <x-dl::action-form action="{{ route('v2.identification.app.logo.remove') }}">
            <x-dl::button variant="secondary" icon="fa-trash" label="Remove Logo" />
        </x-dl::action-form>
    @endif
</x-dl::card>

<x-dl::card title="Company Identity" description="Used across the application and on the header of every printed document. The registered name and NPWP are printed only when a taxable (PPN) document is printed with the PPN option.">
    <x-dl::action-form action="{{ route('v2.identification.company') }}" enctype="multipart/form-data">
        <x-dl::form.row :cols="2">
            <x-dl::field.text name="name" label="Company Name" required :value="old('name', $co['name'])" hint="Default on screens and printouts (Non PPN)" :source="Src::of('tm2sys.sys_constant.COMPANY_NAME')" />
            <x-dl::field.text name="legal_name" label="Registered Name (Legal Entity)" required :value="old('legal_name', $co['legal_name'])" hint="Header of PPN documents" :source="Src::of('tm2sys.sys_constant.COMPANY_LEGAL_NAME')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="2">
            <x-dl::field.text name="npwp" label="NPWP" :case-toggle="false" :value="old('npwp', $co['npwp'])" :source="Src::of('tm2mst.org_company.npwp_number')" />
            <x-dl::field.text name="phone" label="Phone" :case-toggle="false" :value="old('phone', $co['phone'])" :source="Src::of('tm2mst.org_company.phone')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="2">
            <x-dl::field.text name="address" label="Address" :multiline="true" :value="old('address', $co['address'])" :source="Src::of('tm2mst.org_company.address')" />
            <x-dl::field.text name="email" label="Email" type="email" :case-toggle="false" :value="old('email', $co['email'])" :source="Src::of('tm2mst.org_company.email')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="2">
            <x-dl::field.upload name="logo_color" label="Company Logo (light background)" accept="image/*" hint="Used on the header of all printouts. A black-and-white version is made automatically. PNG, up to 2 MB." :source="Src::of('tm2sys.sys_constant.COMPANY_LOGO_PATH')" />
            <x-dl::field.upload name="logo_inverse" label="Company Logo (dark background)" accept="image/*" hint="White-text version for dark backgrounds. Transparent PNG, up to 2 MB." :source="Src::of('tm2sys.sys_constant.COMPANY_LOGO_INVERSE_PATH')" />
        </x-dl::form.row>
        <x-dl::action-bar><x-dl::button preset="save" /></x-dl::action-bar>
    </x-dl::action-form>
    @if (CompanyLogo::url())
        <x-dl::brand tone="dark" :name="raw($co['name'])" tagline="" :logo="CompanyLogo::url()" />
        <x-dl::action-form action="{{ route('v2.identification.company.logo.remove', 'color') }}"><x-dl::button variant="secondary" icon="fa-trash" label="Remove Light Logo" /></x-dl::action-form>
    @endif
    @if (CompanyLogo::url('inverse'))
        <x-dl::field.display label="Dark background logo" value="Uploaded" />
        <x-dl::action-form action="{{ route('v2.identification.company.logo.remove', 'inverse') }}"><x-dl::button variant="secondary" icon="fa-trash" label="Remove Dark Logo" /></x-dl::action-form>
    @endif
</x-dl::card>
