{{-- Edit profile (full screen) v2 -- Profile > Edit profile > "Personal data, leave, documents, shifts" / "Account security".
     Port layar self-service v1 (pages/self-service/index.blade.php) ke objek DevLib: alur, aturan dan tabel sama,
     hanya kulit yang diganti (user 2026-10-03). Data pribadi read-only; perubahan = pengajuan + approval HR
     (App\Support\V2\Data\SelfService). Akun login dan pertanyaan pemulihan diubah langsung. --}}
@php
    use App\Support\V2\Data\SelfService;
    use Illuminate\Support\Facades\Auth;
@endphp

{{-- Layar = objek form khusus x-mp::hrm.self-service (MockupParts); isi = SelfService::desk(). --}}
<x-mp::hrm.self-service :me="SelfService::desk(Auth::user(), route('v2.profile'))" :crumbs="$crumbs" />
