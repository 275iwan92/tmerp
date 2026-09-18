{{--
    Wizard step-bar lintas modul (mockup_FORM_prodev_header.png, user 2026-09-03)
    -- dipakai di Inquiry Product, Pro-Dev, Product Costing, Master Card supaya
    officer bisa lompat antar tahap job yang SAMA tanpa balik ke grid dulu.
    Presentasi murni: host page yang hitung $steps/$jobs/$jobUrls, komponen ini
    cuma render. Warna pakai bg-navy/text-accent (sudah otomatis ikut
    tm2sys.sys_theme lewat --color-navy/--color-accent global, lihat
    theme-vars.blade.php) -- BUKAN palet gelap/hijau statis dari mockup asli.

    Props:
    - current: string, key step aktif (inquiry|development|costing|mastercard|analytics)
    - steps: array<string,array{label:string,url:?string}> keyed by step key.
             url null = step ini tidak reachable utk job yang sedang aktif
             (mis. Mode #2 part tanpa inquiry -> Inquiry/Costing null).
    - jobs: Collection/array of object{id,label,sublabel} -- daftar job utk dropdown.
    - selectedJobId: mixed, id job yang sedang aktif (buat highlight di dropdown).
    - jobSwitchRoute: string, nama route Livewire `switchJob` di host (dipanggil
      wire:click, host yang tentukan redirect kemana sesuai step aktif).
    - backUrl: string, tujuan grid modul ini (production.prodev.list utk ProDev
      & Master Card, inquiry.hub utk Inquiry, inquiry.costing.list utk Costing).
      SATU tombol Back seragam di kanan-atas dipasang di sini (bukan diulang
      per-layar/per-cabang kondisional) supaya tidak pernah hilang di state
      apa pun (user 2026-09-04: kejadian nyata -- cabang "noInquiryForView"
      di inquiry/form.blade.php sempat tak punya tombol back sama sekali).
    - backLabel: string, default "Back" (standar tombol aksi LOCKED, CLAUDE.md §3
      -- Back selalu "Back" di kedua bahasa, tidak ikut toggle lang_code).
--}}
@props(['current', 'steps', 'jobs' => [], 'selectedJobId' => null, 'jobSwitchMethod' => 'switchJob', 'backUrl' => null, 'backLabel' => 'Back'])

<div class="bg-navy text-white px-5 py-2.5 flex items-center gap-1 flex-wrap sticky top-0 z-50 border-b border-white/10">
    <div class="flex items-center gap-1.5 mr-3 shrink-0">
        <div class="w-6 h-6 rounded bg-accent flex items-center justify-center text-[11px] font-black">P</div>
        <div class="leading-none">
            <div class="text-[11px] font-black tracking-wide">TMERP</div>
            <div class="text-[8.5px] text-white/50 -mt-0.5">ProDev Suite</div>
        </div>
    </div>

    {{-- Step TANPA url (mis. Mode #2 tanpa Inquiry) TETAP bisa diklik (user
    2026-09-04: "harus bisa klik dan lihat detail" -- jangan pernah bikin nav
    item mati total) -- klik nunjukkan alasan singkat di popover, bukan
    navigasi ke data yang memang tidak ada. --}}
    <nav class="flex items-center gap-0.5 bg-black/20 rounded-md p-0.5 mr-3" x-data="{ tip: null }">
        @foreach ($steps as $key => $step)
            @if ($step['url'])
                <a href="{{ $step['url'] }}" wire:navigate
                    class="px-2.5 py-1.5 rounded text-[10.5px] font-bold no-underline whitespace-nowrap {{ $key === $current ? 'bg-accent text-white' : 'text-white/65 hover:text-white hover:bg-white/10' }}">
                    {{ $step['label'] }}
                </a>
            @else
                <div class="relative">
                    <button type="button" @click="tip = (tip === '{{ $key }}' ? null : '{{ $key }}')"
                        class="px-2.5 py-1.5 rounded text-[10.5px] font-bold whitespace-nowrap text-white/40 hover:text-white/70 hover:bg-white/10 cursor-pointer">
                        {{ $step['label'] }}
                    </button>
                    <div x-show="tip === '{{ $key }}'" @click.outside="tip = null" x-cloak
                        class="absolute left-0 top-full mt-1.5 w-56 bg-[#0f1830] border border-white/15 text-white/85 text-[11px] leading-snug rounded-md p-2.5 shadow-lg z-50">
                        {{ $step['reason'] ?? 'Tidak tersedia untuk job ini.' }}
                    </div>
                </div>
            @endif
        @endforeach
    </nav>

    @if (count($jobs) > 0)
        <select wire:change="{{ $jobSwitchMethod }}($event.target.value)"
            class="bg-black/25 border border-white/15 text-white text-[10.5px] font-semibold rounded-md px-2 py-1.5 mr-auto max-w-[220px]">
            @foreach ($jobs as $job)
                <option value="{{ $job['id'] }}" @selected((string) $job['id'] === (string) $selectedJobId)>{{ $job['label'] }}</option>
            @endforeach
        </select>
    @else
        <span class="mr-auto"></span>
    @endif

    <span class="flex items-center gap-1.5 text-[10px] font-bold text-[#5fe3a0] bg-[#1c3a2a] px-2.5 py-1.5 rounded-md shrink-0" title="Imposition engine (App\Services\Imposition\ImpositionEngine) live &amp; dipakai perhitungan nyata di layar ini.">
        <span class="w-1.5 h-1.5 rounded-full bg-[#5fe3a0]"></span> ENGINE AKTIF
    </span>

    <button type="button" disabled title="Segera hadir -- belum ada mesinnya"
        class="text-[10.5px] font-semibold text-white/35 bg-white/5 px-2.5 py-1.5 rounded-md cursor-not-allowed shrink-0">🧭 Advisor</button>
    <button type="button" disabled title="Segera hadir -- belum ada mesinnya"
        class="text-[10.5px] font-semibold text-white/35 bg-white/5 px-2.5 py-1.5 rounded-md cursor-not-allowed shrink-0">⬇ Export Paths</button>

    @if ($backUrl)
        <a href="{{ $backUrl }}" title="{{ $backLabel }}" data-back
            class="flex items-center gap-1.5 text-[10.5px] font-bold text-white bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-md no-underline shrink-0">
            ← {{ $backLabel }}
        </a>
    @endif
</div>
