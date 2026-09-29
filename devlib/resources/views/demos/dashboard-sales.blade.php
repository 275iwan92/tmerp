{{-- Di halaman pembuka: slot "dashboard" pada Shell, isi = DashboardData::for(user login). --}}
<x-dl::dashboard :board="\App\Support\DashboardData::forLogin('yuna')" source="App\Support\DashboardData (contoh)" />
