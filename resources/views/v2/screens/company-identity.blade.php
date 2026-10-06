{{-- Setup > Identification (v2, pengganti layar v1 pages::setup.identification). Data & aturan simpan = App\Support\V2\Data\Identification.
     Kartu Aplikasi = nama / tagline / font / logo yang tampil di menu, topbar dan login; kartu Perusahaan = isi kop cetakan dokumen. --}}
@php
    use App\Support\V2\Data\Identification;
    use App\Support\V2\Src;

    abort_unless(auth()->user()->is_admin, 403);
    $app = Identification::app();
    $co = Identification::company();
    $k = fn (string $key) => Src::of('tm2sys.sys_constant.constant_text').' ('.$key.')';
    $o = fn (string $col) => Src::of('tm2mst.org_company.'.$col);
    $confirm = "return confirm('".t('Remove this logo?')."')";
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::action-form action="{{ route('v2.identification.app') }}" enctype="multipart/form-data" data-dl-guard>
    <x-dl::card title="Application Identity" icon="settings" description="Name, tagline, logo and font of the application: shown in the menu, the top bar and the login page. Not the company name or logo (those are below, for printed documents).">
        <x-dl::form.row cols="3">
            <x-dl::field.text name="app_name" label="Application name" required :value="old('app_name', $app['name'])" placeholder="ERP TM" :source="$k('APP_NAME')" />
            <x-dl::field.text name="app_tagline" label="Tagline" :value="old('app_tagline', $app['tagline'])" placeholder="packaging solution" :source="$k('APP_TAGLINE')" />
            <x-dl::field.select name="app_font" label="Application font" required :options="Identification::fonts()" :value="old('app_font', $app['font'])" :source="$k('APP_FONT_FAMILY')" />
        </x-dl::form.row>
        <x-dl::form.row cols="2">
            <x-dl::field.upload name="app_logo" label="Application logo (max. 1 MB)" accept="image/*" :value="$app['logo']" :source="$k('APP_LOGO_PATH')" />
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.home')" />
            @if ($app['logo'])<x-dl::button variant="danger" icon="delete" label="Remove logo" type="submit" :formaction="route('v2.identification.app-logo.remove')" formnovalidate :onclick="$confirm" />@endif
            <x-dl::button preset="save" />
        </x-dl::action-bar>
    </x-dl::card>
</x-dl::action-form>

<x-dl::action-form action="{{ route('v2.identification.company') }}" enctype="multipart/form-data" data-dl-guard>
    <x-dl::card title="Company Identity" icon="card" description="Printed in the letterhead of every document. The registered name and NPWP are used on PPN documents; the company name on non-PPN documents.">
        <x-dl::form.row cols="2">
            <x-dl::field.text name="company_name" label="Company name" required :value="old('company_name', $co['name'])" placeholder="Telasih Makmur" hint="Default in the application and on non-PPN prints" :source="$k('COMPANY_NAME')" />
            <x-dl::field.text name="company_legal_name" label="Registered name (legal entity)" required :value="old('company_legal_name', $co['legal_name'])" placeholder="CV. Langgeng Jaya Makmur" hint="Letterhead of PPN documents" :source="$k('COMPANY_LEGAL_NAME')" />
        </x-dl::form.row>
        <x-dl::form.row cols="3">
            <x-dl::field.text name="npwp" label="NPWP" :case-toggle="false" :value="old('npwp', $co['npwp'])" placeholder="01.234.567.8-901.000" hint="Printed under the registered name on PPN documents" :source="$o('npwp_number')" />
            <x-dl::field.text name="phone" label="Phone" :case-toggle="false" :value="old('phone', $co['phone'])" placeholder="(031) 1234567" :source="$o('phone')" />
            <x-dl::field.text name="email" label="Email" type="email" :case-toggle="false" :value="old('email', $co['email'])" placeholder="info@company.co.id" :source="$o('email')" />
        </x-dl::form.row>
        <x-dl::field.text name="address" label="Address" multiline :value="old('address', $co['address'])" placeholder="Full address for the document letterhead" :source="$o('address')" />
        <x-dl::form.row cols="2">
            <x-dl::field.upload name="logo" label="Company logo (light background, max. 2 MB)" accept="image/*" :value="$co['logo']" hint="A black-and-white version is made automatically for black-and-white prints" :source="$k('COMPANY_LOGO_PATH')" />
            <x-dl::field.upload name="logo_inverse" label="Company logo (dark background, white text)" accept="image/*" :value="$co['logo_inverse']" :source="$k('COMPANY_LOGO_INVERSE_PATH')" />
        </x-dl::form.row>
        <x-dl::action-bar>
            @if ($co['logo'])<x-dl::button variant="danger" icon="delete" label="Remove light logo" type="submit" :formaction="route('v2.identification.company-logo.remove', 'color')" formnovalidate :onclick="$confirm" />@endif
            @if ($co['logo_inverse'])<x-dl::button variant="danger" icon="delete" label="Remove dark logo" type="submit" :formaction="route('v2.identification.company-logo.remove', 'inverse')" formnovalidate :onclick="$confirm" />@endif
            <x-dl::button preset="save" />
        </x-dl::action-bar>
    </x-dl::card>
</x-dl::action-form>
