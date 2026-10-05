{{-- Form khusus Edit profile / self-service. Contoh ini memakai akun yang belum terhubung ke pegawai ('linked' => false), jadi
     hanya bagian Security yang tampil; dengan pegawai, sebelas bagian dan delapan pop-up pengajuan ikut muncul.
     Di aplikasi: <x-mp::hrm.self-service :me="SelfService::desk(Auth::user(), route('v2.profile'))" :crumbs="$crumbs" /> --}}
<div class="dl-demo">
    <x-mp::hrm.self-service :crumbs="['Profile' => null, 'Edit profile' => null]" :me="[
        'home' => '#', 'linked' => false, 'tabs' => ['security' => ['Security', 'fa-shield-halved']], 'tab' => 'security', 'flash' => null, 'error' => null,
        'security' => ['accountAction' => '#', 'passwordAction' => '#', 'recoveryAction' => '#', 'emailUrl' => '#', 'name' => 'Sari Dewi', 'email' => 'sari@example.com',
            'phone' => '0812-0000-0000', 'verified' => 'Verified', 'setup' => null, 'birthDate' => '1990-04-12', 'motherName' => null, 'recoveryPhone' => '0812-0000-0000',
            'questionId' => 1, 'questions' => [1 => 'What was the name of your first school?', 2 => 'What is your favourite food?'], 'answer' => null],
    ]" />
</div>
