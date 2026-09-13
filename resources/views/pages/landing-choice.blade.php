<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// Layar pilih landing utk Administrator (keputusan user 2026-09-13): admin
// bisa kerja operasional (dev/config) ATAU lihat Laporan Manajemen, jadi
// diberi pilihan -- beda dari Direksi murni yang langsung dilempar ke
// laporan tanpa pilihan (lihat SecUser::isDireksiOnly()/main-menu mount()).
new #[Layout('layouts::shell')] #[Title('Pilih Tampilan — TELASIH MAKMUR ERP')] class extends Component
{
    public function mount(): void
    {
        // Bukan admin nyasar ke sini (mis. lewat URL langsung) -> lempar ke
        // alur normal masing-masing, jangan biarkan lihat layar pilihan ini.
        $user = Auth::user();
        if (! $user?->isAdminWithChoice()) {
            $this->redirect($user?->isDireksiOnly() ? route('report.executive') : route('menu'), navigate: false);
        }
    }
}; ?>

<div class="flex items-center justify-center min-h-screen p-6" style="background:var(--sp-bg,#f4f6fb)">
    <div class="w-full max-w-xl text-center">
        <h1 class="text-xl font-bold mb-1.5">Mau buka yang mana dulu?</h1>
        <p class="text-[13px] opacity-60 mb-6">Akun Administrator bisa masuk ke dua sisi — pilih salah satu, bisa pindah kapan saja lewat menu.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <a href="{{ route('report.executive') }}"
               class="rounded-xl border bg-white shadow-sm p-6 text-left hover:shadow-md hover:border-[#2563eb] transition">
                <i class="fa-solid fa-chart-line text-2xl text-[#2563eb] mb-2.5"></i>
                <h2 class="font-bold text-[14px] mb-1">Laporan Manajemen</h2>
                <p class="text-[11.5px] opacity-60">Dashboard eksekutif — omset, pembelian, utang usaha, Customer 360.</p>
            </a>

            <a href="{{ route('menu') }}"
               class="rounded-xl border bg-white shadow-sm p-6 text-left hover:shadow-md hover:border-[#16a34a] transition">
                <i class="fa-solid fa-grip text-2xl text-[#16a34a] mb-2.5"></i>
                <h2 class="font-bold text-[14px] mb-1">Menu Operasional</h2>
                <p class="text-[11.5px] opacity-60">Sales, Procurement, Production, HRM, dan modul transaksi lainnya.</p>
            </a>
        </div>
    </div>
</div>
