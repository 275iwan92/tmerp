<?php

use App\Models\Sys\SysSupportReply;
use App\Models\Sys\SysSupportTicket;
use App\Models\Sys\SysSupportTicketAttachment;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

// Help Center -- SATU tempat gabungan (instruksi user 2026-08-30: "Combine
// Help and support yang ticketing and Help and support yang menjelaskan
// segala hal"), 3 sub-menu: Tiket Bantuan (submit + riwayat balasan
// developer/system analyst -- MENGGANTIKAN modal "Help & Support" lama di
// main-menu.blade.php, yang sekarang cuma link ke sini), Cara Pakai Aplikasi
// (UI/UX), Modul (detail per modul). Dibuka via F1 (partials/f1-help-hotkey)
// atau link profil dropdown, KEDUANYA target="_blank".
new #[Title('Bantuan — TELASIH MAKMUR ERP')] class extends Component
{
    use WithFileUploads;

    #[Url(as: 'tab')]
    public string $tab = 'tickets'; // tickets | ui | modules

    // Konteks layar asal (diisi otomatis dari document.title tab yg memicu F1,
    // lihat partials/f1-help-hotkey.blade.php) -- boleh diedit manual juga.
    #[Url(as: 'ctx')]
    public string $support_context = '';

    public string $support_description = '';

    public array $support_attachments = [];

    public ?string $supportError = null;

    public ?string $supportSubmittedNo = null;

    public ?int $selectedTicketId = null;

    public string $newReply = '';

    #[Computed]
    public function myTickets()
    {
        return SysSupportTicket::with('replies')->where('created_by_user_id', Auth::id())->orderByDesc('id_ticket')->get();
    }

    #[Computed]
    public function selectedTicket(): ?SysSupportTicket
    {
        if (! $this->selectedTicketId) {
            return null;
        }

        return $this->myTickets->firstWhere('id_ticket', $this->selectedTicketId);
    }

    public function openTicket(int $id): void
    {
        $this->selectedTicketId = $id;
        $this->newReply = '';
    }

    public function submitSupportTicket(): void
    {
        $this->supportError = null;

        if (trim($this->support_description) === '') {
            $this->supportError = Translator::t('shell_help_desc_required', 'Deskripsi wajib diisi.');

            return;
        }

        $year = now()->format('y');
        $month = now()->format('m');
        $seq = SysSupportTicket::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count() + 1;

        $ticket = SysSupportTicket::create([
            'ticket_no' => "SPT{$year}{$month}".str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'created_by_user_id' => Auth::id(),
            'screen_context' => $this->support_context ?: null,
            'description' => $this->support_description,
            'status' => 'OPEN',
        ]);

        foreach ($this->support_attachments as $file) {
            SysSupportTicketAttachment::create([
                'ticket_id' => $ticket->id_ticket,
                'file_path' => $file->store('support-tickets', 'public'),
                'uploaded_at' => now(),
            ]);
        }

        $this->supportSubmittedNo = $ticket->ticket_no;
        $this->support_description = '';
        $this->support_attachments = [];
        unset($this->myTickets);
    }

    public function resetSupportForm(): void
    {
        $this->supportSubmittedNo = null;
        $this->supportError = null;
    }

    // Balasan BALIK dari user (dua arah -- staff balas di setup/support-tickets.blade.php).
    public function addReply(): void
    {
        if (! $this->selectedTicket || trim($this->newReply) === '') {
            return;
        }

        SysSupportReply::create([
            'ticket_id' => $this->selectedTicket->id_ticket,
            'author' => Auth::user()->name ?? Auth::user()->login,
            'body' => $this->newReply,
        ]);

        $this->newReply = '';
        unset($this->myTickets);
    }
};
?>

<div class="min-h-screen bg-[#f4f6fa]" x-data="{ search: '' }">
    <header class="bg-navy text-white sticky top-0 z-30">
        <div class="max-w-[1200px] mx-auto px-5 h-16 flex items-center gap-4">
            <a href="{{ route('menu') }}" class="flex items-center gap-2.5 shrink-0">
                <span class="w-9 h-9 rounded-[10px] shrink-0" style="background: conic-gradient(from 200deg, #ff5f6d, #ffc371, #2f6fed, #8a5cf6, #ff5f6d);"></span>
                <span class="leading-tight hidden sm:block">
                    <b class="block text-[14px] tracking-[.3px] text-white">TELASIH MAKMUR</b>
                    <span class="block text-[9px] tracking-[2px] text-white/60 uppercase">{{ Translator::t('shell_help_header_title', 'Bantuan') }}</span>
                </span>
            </a>
            @if ($tab !== 'tickets')
                <div class="flex-1 max-w-[420px] ml-auto">
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-white/50 text-sm">🔍</span>
                        <input type="text" x-model="search" placeholder="{{ Translator::t('shell_help_search_placeholder', 'Cari topik bantuan…') }}"
                            class="w-full pl-9 pr-3 py-2 rounded-md bg-white/10 border border-white/20 text-white placeholder-white/50 text-[13px] focus:bg-white/15 focus:outline-none">
                    </div>
                </div>
            @endif
        </div>
        {{-- Sub-menu (instruksi user: "ada sub-menu mulai dari ticket... lalu cara pakai UI/UX... lalu penjelasan modul") --}}
        <div class="max-w-[1200px] mx-auto px-5 flex gap-1 border-t border-white/10">
            <button type="button" wire:click="$set('tab', 'tickets')" class="px-4 py-2.5 text-[13px] font-semibold border-b-2 {{ $tab === 'tickets' ? 'border-white text-white' : 'border-transparent text-white/60 hover:text-white' }}">🎫 {{ Translator::t('shell_help_tab_tickets', 'Tiket Bantuan') }}</button>
            <button type="button" wire:click="$set('tab', 'ui')" class="px-4 py-2.5 text-[13px] font-semibold border-b-2 {{ $tab === 'ui' ? 'border-white text-white' : 'border-transparent text-white/60 hover:text-white' }}">🧭 {{ Translator::t('shell_help_tab_ui', 'Cara Pakai Aplikasi') }}</button>
            <button type="button" wire:click="$set('tab', 'modules')" class="px-4 py-2.5 text-[13px] font-semibold border-b-2 {{ $tab === 'modules' ? 'border-white text-white' : 'border-transparent text-white/60 hover:text-white' }}">📚 {{ Translator::t('shell_help_tab_modules', 'Modul') }}</button>
        </div>
    </header>

    <div class="max-w-[1200px] mx-auto px-5 py-8">

        {{-- ============ TAB 1: TIKET BANTUAN ============ --}}
        @if ($tab === 'tickets')
            <div class="grid gap-6" style="grid-template-columns: minmax(0,1fr) 320px;">
                <div class="space-y-4">
                    @if ($this->selectedTicket)
                        {{-- Detail 1 tiket + thread balasan --}}
                        <button type="button" wire:click="$set('selectedTicketId', null)" class="text-[12px] font-bold text-muted hover:text-navy">&larr; {{ Translator::t('shell_help_back_to_ticket_list', 'Kembali ke daftar tiket saya') }}</button>
                        <div class="bg-white border border-[#e6e9ef] rounded-xl p-5">
                            <div class="flex items-center justify-between mb-1">
                                <h2 class="text-sm font-bold text-navy font-mono">{{ $this->selectedTicket->ticket_no }}</h2>
                                <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full {{ match ($this->selectedTicket->status) {
                                    'DONE' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                    'CLOSED' => 'bg-[#eef1f5] text-[#4b5568]',
                                    'IN_PROGRESS' => 'bg-[#fff5e0] text-[#8a5a00]',
                                    default => 'bg-[#eaf2ff] text-[#1d4ed8]',
                                } }}">{{ str_replace('_', ' ', $this->selectedTicket->status) }}</span>
                            </div>
                            <p class="text-[11px] text-muted mb-3">{{ $this->selectedTicket->screen_context ?: '—' }} · {{ $this->selectedTicket->created_at->translatedFormat('d M Y, H:i') }}</p>
                            <p class="text-[13px] text-[#333a4d] mb-4">{{ $this->selectedTicket->description }}</p>

                            @if ($this->selectedTicket->replies->isNotEmpty())
                                <div class="space-y-2 mb-4">
                                    @foreach ($this->selectedTicket->replies as $r)
                                        @php $isMine = $r->author === (Auth::user()->name ?? Auth::user()->login); @endphp
                                        <div class="rounded-md px-3 py-2 text-[12.5px] {{ $isMine ? 'bg-[#eaf2ff] ml-8' : 'bg-[#f4f6fa] mr-8' }}">
                                            <div class="flex justify-between text-[10px] text-muted mb-0.5"><span class="font-bold">{{ $r->author }}{{ $isMine ? ' ('.Translator::t('shell_help_you_suffix', 'Anda').')' : '' }}</span><span>{{ $r->created_at->diffForHumans(null, true) }}</span></div>
                                            <div>{{ $r->body }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-[12px] text-muted mb-4">{{ Translator::t('shell_help_no_reply_yet', 'Belum ada balasan dari tim support.') }}</p>
                            @endif

                            @unless (in_array($this->selectedTicket->status, ['DONE', 'CLOSED'], true))
                                <div class="flex gap-2 pt-3 border-t border-[#eef1f5]">
                                    <textarea wire:model="newReply" rows="2" placeholder="{{ Translator::t('shell_help_reply_placeholder', 'Balas / tambah info…') }}" class="flex-1 border border-[#d7dbe2] rounded-md px-2.5 py-1.5 text-[12.5px] resize-none"></textarea>
                                    <button type="button" wire:click="addReply" wire:loading.attr="disabled" class="self-end bg-navy text-white text-xs font-bold px-3.5 py-2 rounded-md shrink-0">{{ Translator::t('shell_help_btn_send', 'Kirim') }}</button>
                                </div>
                            @else
                                <p class="text-[11px] text-muted italic">{{ Translator::t('shell_help_ticket_closed_prefix', 'Tiket ini sudah') }} {{ strtolower(str_replace('_', ' ', $this->selectedTicket->status)) }}{{ Translator::t('shell_help_ticket_closed_suffix', ', tidak bisa dibalas lagi.') }}</p>
                            @endunless
                        </div>
                    @else
                        {{-- Form kirim tiket baru --}}
                        <div class="bg-white border border-[#e6e9ef] rounded-xl p-5">
                            <h2 class="text-sm font-bold text-navy mb-1">{{ Translator::t('shell_help_new_ticket_title', 'Kirim Tiket Baru') }}</h2>
                            <p class="text-[12px] text-muted mb-3">{{ Translator::t('shell_help_new_ticket_desc', 'Laporkan bug, minta bantuan, atau ajukan pertanyaan ke tim developer/system analyst.') }}</p>

                            @if ($supportSubmittedNo)
                                <div class="text-center py-6">
                                    <p class="text-3xl mb-1">✅</p>
                                    <p class="font-bold text-[#1f8a4c]">{{ Translator::t('shell_help_ticket_sent', 'Tiket terkirim') }}</p>
                                    <p class="text-[12px] text-muted mt-1">{{ Translator::t('shell_help_ticket_sent_prefix', 'Nomor tiket') }} <b>#{{ $supportSubmittedNo }}</b> {{ Translator::t('shell_help_ticket_sent_suffix', '— tim kami akan segera menindaklanjuti. Lihat statusnya di daftar "Tiket Saya" di sebelah kanan.') }}</p>
                                    <button type="button" wire:click="resetSupportForm" class="mt-3 text-[12px] font-bold text-navy underline">{{ Translator::t('shell_help_send_another', 'Kirim tiket lain') }}</button>
                                </div>
                            @else
                                @if ($supportError)
                                    <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $supportError }}</p>
                                @endif
                                <x-form.input label="{{ Translator::t('shell_help_context_label', 'Konteks layar (opsional)') }}" wire:model="support_context" placeholder="{{ Translator::t('shell_help_context_placeholder', 'mis. Master Customer, Customer Order #123') }}" class="mb-3" />
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ Translator::t('shell_help_description_label', 'Deskripsi *') }}</label>
                                <textarea wire:model="support_description" rows="4" placeholder="{{ Translator::t('shell_help_description_placeholder', 'Ceritakan apa yang terjadi — makin detail, makin cepat ditangani.') }}" class="w-full border border-[#d7dbe2] rounded-md px-2.5 py-1.5 text-[13px] resize-y mb-3"></textarea>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ Translator::t('shell_help_attachment_label', 'Lampiran screenshot (opsional)') }}</label>
                                <input type="file" wire:model="support_attachments" multiple accept="image/*" class="text-xs mb-2">
                                <div wire:loading wire:target="support_attachments" class="text-[11px] text-muted">{{ Translator::t('shell_help_uploading', 'Mengunggah…') }}</div>
                                @if (count($support_attachments))
                                    <div class="flex gap-2 flex-wrap mb-3">
                                        @foreach ($support_attachments as $i => $file)
                                            <div class="relative w-14 h-14">
                                                <img src="{{ $file->temporaryUrl() }}" class="w-full h-full object-cover rounded-md">
                                                <button type="button" wire:click="$set('support_attachments.{{ $i }}', null)" title="{{ Translator::t('shell_help_remove_attachment', 'Hapus') }}" class="absolute -top-1.5 -right-1.5 bg-white border border-[#d7dbe2] rounded-full w-[18px] h-[18px] text-[11px] leading-none">×</button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                <button type="button" wire:click="submitSupportTicket" wire:loading.attr="disabled" class="bg-[#1f8a4c] text-white text-sm font-bold px-4 py-2.5 rounded-md">{{ Translator::t('shell_help_btn_send_ticket', 'Kirim Tiket') }}</button>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Daftar tiket saya (kanan) --}}
                <div class="bg-white border border-[#e6e9ef] rounded-xl p-4 h-fit">
                    <p class="text-[11px] font-bold text-muted uppercase mb-2">{{ Translator::t('shell_help_my_tickets', 'Tiket Saya') }} ({{ $this->myTickets->count() }})</p>
                    <div class="space-y-1.5 max-h-[70vh] overflow-y-auto">
                        @forelse ($this->myTickets as $t)
                            <button type="button" wire:click="openTicket({{ $t->id_ticket }})"
                                class="w-full text-left px-2.5 py-2 rounded-md text-[12px] {{ $selectedTicketId === $t->id_ticket ? 'bg-[#eaf2ff]' : 'hover:bg-[#f7faff]' }}">
                                <div class="flex items-center justify-between gap-1.5">
                                    <span class="font-mono font-bold text-navy text-[11px]">{{ $t->ticket_no }}</span>
                                    <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded-full shrink-0 {{ match ($t->status) {
                                        'DONE' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                        'CLOSED' => 'bg-[#eef1f5] text-[#4b5568]',
                                        'IN_PROGRESS' => 'bg-[#fff5e0] text-[#8a5a00]',
                                        default => 'bg-[#eaf2ff] text-[#1d4ed8]',
                                    } }}">{{ str_replace('_', ' ', $t->status) }}</span>
                                </div>
                                <p class="text-[#495166] mt-0.5">{{ Str::limit($t->description, 44) }}</p>
                                @if ($t->replies->isNotEmpty())
                                    <p class="text-[10px] text-accent font-semibold mt-0.5">💬 {{ $t->replies->count() }} {{ Translator::t('shell_help_replies_suffix', 'balasan') }}</p>
                                @endif
                            </button>
                        @empty
                            <p class="text-[12px] text-muted px-1">{{ Translator::t('shell_help_no_tickets', 'Belum ada tiket.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        {{-- ============ TAB 2: CARA PAKAI APLIKASI ============ --}}
        @if ($tab === 'ui')
            <div class="flex gap-8 items-start">
                <nav class="hidden lg:block w-[220px] shrink-0 sticky top-32 text-[13px]">
                    <a href="#ui-navigasi" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Navigasi Umum</a>
                    <a href="#ui-grid" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Tabel / Grid</a>
                    <a href="#ui-grid-lanjutan" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Fitur Grid Lanjutan</a>
                    <a href="#ui-form" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Form Isian</a>
                    <a href="#ui-kanban" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Papan Kanban</a>
                    <a href="#ui-gantt" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Papan Gantt (Jadwal)</a>
                    <a href="#ui-cetak" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Cetak Dokumen</a>
                    <a href="#ui-lain" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Notifikasi, Chat &amp; AI</a>
                </nav>
                <div class="flex-1 min-w-0 space-y-6">
                    <div class="bg-[#eaf2ff] border border-[#c8ddfb] rounded-lg p-4 text-[13px] text-[#3b5b99]">
                        👋 Penjelasan tampilan-tampilan umum di aplikasi — begitu paham 1 grid/form, paham semuanya karena semua layar pakai pola yang sama.
                    </div>

                    <section id="ui-navigasi" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-32">
                        <h2 class="text-[15px] font-bold text-navy mb-3">🧭 Navigasi Umum</h2>
                        <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside">
                            <li><b>Bar modul di atas</b> (Sales, Procurement, Inventory, dst) — klik untuk lihat daftar layar di modul itu. Modul yang belum ada isinya akan tampak redup/tidak bisa diklik.</li>
                            <li><b>Sidebar kiri</b> berubah isinya sesuai modul yang dipilih, dikelompokkan per grup. <b>Klik nama grup (mis. "Transaksi") untuk ciutkan/buka</b> — ini tersembunyi kalau tidak dicoba, coba klik langsung nama grupnya, bukan cuma panah kecilnya.</li>
                            <li><b>Tab di atas area kerja</b> — tiap layar yang dibuka jadi tab baru, bisa buka banyak layar sekaligus tanpa kehilangan yang lain. Klik ✕ di tab untuk menutup.</li>
                            <li><b>Ikon profil</b> (kanan atas) — Edit profil, Ubah password, Portal Karyawan, My Security, dan <b>Bantuan (halaman ini)</b>.</li>
                            <li><b>Ikon 🔍 pencarian cepat</b> di topbar (atau tekan <kbd>Ctrl</kbd>+<kbd>K</kbd>) — cari nama layar langsung tanpa klik-klik menu satu-satu.</li>
                            <li><b>Tekan <kbd>F1</kbd> kapan saja</b> — langsung buka halaman Bantuan ini di tab baru, di layar mana pun Anda sedang berada, sudah otomatis membawa konteks layar itu ke form tiket.</li>
                        </ul>
                    </section>

                    <section id="ui-grid" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-32">
                        <h2 class="text-[15px] font-bold text-navy mb-3">📊 Tabel / Grid (Daftar Data)</h2>
                        <p class="text-[13px] text-[#333a4d] mb-3">Hampir semua layar daftar (Customer, Item, Purchase Order, dst) pakai tampilan yang SAMA — begitu paham 1 grid, paham semuanya.</p>
                        <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside">
                            <li><b>Kotak angka warna-warni di atas</b> — ringkasan cepat (Total/Aktif/Nonaktif/dst), bukan tombol, cuma info.</li>
                            <li><b>Kotak pencarian</b> — ketik langsung, hasil otomatis update (tak perlu tekan Enter atau klik tombol cari).</li>
                            <li><b>Tombol "+ New Record"</b> (pojok kanan atas tabel) — untuk menambah data baru.</li>
                            <li><b>Klik 2x pada baris</b> data — ini <b>satu-satunya cara buka detail/edit</b> lewat baris itu sendiri (1x klik tidak berbuat apa-apa di banyak layar — ini yang paling sering tidak disadari pengguna baru).</li>
                            <li><b>Ikon-ikon kecil di kolom paling kanan (atau kiri, tergantung layar)</b> — arahkan mouse ke ikon untuk lihat keterangannya (✎ = Edit, ⏻ = Aktifkan/Nonaktifkan, dan ikon lain sesuai layar). Untuk layar dokumen transaksi (PO, CO, dst), ikonnya ↗ Buka dan kadang ✓ Confirm.</li>
                            <li><b>Nomor halaman di bawah tabel</b> — data besar dipecah per halaman supaya tidak berat, bukan semua tampil sekaligus.</li>
                        </ul>
                    </section>

                    <section id="ui-grid-lanjutan" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-32">
                        <h2 class="text-[15px] font-bold text-navy mb-3">🛠️ Fitur Grid Lanjutan (di beberapa layar tertentu)</h2>
                        <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside">
                            <li><b>Panah ▸ di kiri baris</b> (mis. di Customer Order) — klik untuk intip isi dokumen (item/baris) langsung di tempat, tanpa pindah halaman.</li>
                            <li><b>Kotak centang di baris</b> (khusus data yang masih draft) — centang beberapa baris sekaligus untuk hapus massal. <b>Tahan tombol Shift lalu klik baris lain</b> untuk memilih SEMUA baris di antaranya sekaligus — jalan pintas ini gampang terlewat karena tidak kelihatan dari tampilannya.</li>
                            <li><b>Tombol ☰ Kolom / ↕ Sort / ⬇ Export</b> (di beberapa grid dengan banyak kolom) — atur kolom mana yang mau ditampilkan, urutkan data, atau unduh ke Excel/CSV/PDF.</li>
                            <li><b>Toggle "☰ List" / "▦ Card"</b> (khusus Master Employee) — ganti tampilan daftar jadi kartu foto.</li>
                        </ul>
                    </section>

                    <section id="ui-form" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-32">
                        <h2 class="text-[15px] font-bold text-navy mb-3">📝 Form Isian</h2>
                        <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside">
                            <li><b>Tanda bintang (*)</b> di label field — wajib diisi, tidak bisa disimpan kalau kosong.</li>
                            <li><b>Kesalahan isian</b> muncul warna merah tepat di bawah field yang bermasalah setelah tombol Simpan diklik.</li>
                            <li><b>Field abu-abu/tidak bisa diketik</b> — biasanya data yang dikunci (mis. dari akun login, atau dokumen yang sudah dikonfirmasi) supaya tidak berubah tanpa sengaja.</li>
                            <li><b>Tombol Batal</b> — kembali ke daftar tanpa menyimpan apa pun.</li>
                        </ul>
                    </section>

                    <section id="ui-kanban" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-32">
                        <h2 class="text-[15px] font-bold text-navy mb-3">🗂️ Papan Kanban</h2>
                        <p class="text-[13px] text-[#333a4d]">Dipakai di layar seperti Pipeline Board (Recruitment), Monitoring Produksi, dan Shift Roster — kartu mewakili 1 dokumen/pekerjaan/pegawai, kolom mewakili status/tahapan/mesin.</p>
                        <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside mt-2">
                            <li><b>Seret (drag) kartu ke kolom lain</b> untuk pindahkan statusnya — klik-tahan kartu, geser ke kolom tujuan, lepas. Arahkan mouse ke kartu untuk lihat keterangan ini juga.</li>
                            <li>Kalau perpindahannya tidak diperbolehkan (mis. lompat tahap), sistem akan menolak otomatis — coba lagi lewat tombol aksi di kartu sebagai alternatif kalau drag tidak berhasil.</li>
                        </ul>
                    </section>

                    <section id="ui-gantt" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-32">
                        <h2 class="text-[15px] font-bold text-navy mb-3">📅 Papan Gantt (Jadwal Produksi)</h2>
                        <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside">
                            <li><b>Seret badan balok jadwal</b> — geser ke kiri/kanan untuk pindah waktu, ke atas/bawah untuk pindah mesin/resource.</li>
                            <li><b>Seret tepi kanan balok</b> — ubah durasi/lama pengerjaan (perbesar/perkecil balok).</li>
                            <li>Highlight otomatis muncul kalau ada jadwal yang bertabrakan lintas mesin.</li>
                        </ul>
                    </section>

                    <section id="ui-cetak" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-32">
                        <h2 class="text-[15px] font-bold text-navy mb-3">🖨️ Cetak Dokumen (Nota, Quotation, dll)</h2>
                        <p class="text-[13px] text-[#333a4d]">Dokumen seperti Nota Inquiry, Quotation, dan dokumen resmi lain punya tombol "Cetak"/ikon printer di layar detailnya — klik untuk buka tampilan versi cetak, lalu pakai perintah cetak/simpan-PDF bawaan browser (Ctrl+P).</p>
                    </section>

                    <section id="ui-lain" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-32">
                        <h2 class="text-[15px] font-bold text-navy mb-3">💬 Notifikasi, Chat &amp; Asisten AI</h2>
                        <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside">
                            <li><b>🔔 Notifikasi</b> — info dari sistem (mis. dokumen butuh approval Anda).</li>
                            <li><b>💬 Chat</b> — pesan internal antar pengguna aplikasi.</li>
                            <li><b>✨ Asisten AI</b> — bantuan cepat berbasis AI di dalam aplikasi.</li>
                            <li>Semua ada di ikon profil/lonceng pojok kanan atas topbar.</li>
                        </ul>
                    </section>
                </div>
            </div>
        @endif

        {{-- ============ TAB 3: MODUL ============ --}}
        @if ($tab === 'modules')
            @php
                $modules = [
                    [
                        'id' => 'mod-sales', 'icon' => '💼', 'name' => 'Sales',
                        'desc' => 'Alur penjualan lengkap dari permintaan spesifikasi produk sampai barang dikirim ke pelanggan.',
                        'steps' => [
                            'Inquiry — catat spesifikasi produk yang diminta customer (ukuran, bahan, proses cetak). Tipe NEW (baru)/RECALC (hitung ulang harga, spek sama)/REVISI (spek berubah, jadi baris baru, riwayat lama tetap ada).',
                            'Imposition & Routing — Product Designer atur tata letak plano (berapa lembar muat di 1 plano) dan urutan proses produksi.',
                            'Product Costing — hitung HPP & margin berdasarkan hasil Inquiry+Imposition; harga & margin di sini RAHASIA (hanya tim Costing & Direksi), baru terlihat ke Sales setelah di-"publish".',
                            'Quotation — bikin penawaran harga resmi ke customer dari hasil Costing yang sudah dipublish.',
                            'Customer Order (CO) — begitu customer setuju, Quotation dikonfirmasi jadi CO (order fix, mengikat).',
                            'Rencana Kirim (Plan Delivery) — jadwalkan kapan & berapa banyak barang dikirim (bisa dicicil per termin).',
                            'Delivery Order (DO) / Surat Jalan — dokumen fisik pengiriman barang ke customer.',
                        ],
                        'note' => 'Tahapan ini berurutan dan SALING MENGUNCI — tahap belakang (mis. Quotation) tidak bisa ubah data tahap sebelumnya (Inquiry). Kalau spesifikasi berubah setelah Inquiry dikonfirmasi, harus lewat REVISI (bikin baris baru), bukan edit langsung.',
                    ],
                    [
                        'id' => 'mod-proc', 'icon' => '🛒', 'name' => 'Procurement',
                        'desc' => 'Proses pembelian barang/bahan baku dari supplier, dipicu kebutuhan produksi maupun permintaan manual.',
                        'steps' => [
                            'Purchase Requisition (PR) — permintaan beli dari user/departemen, melalui alur persetujuan (approval) sebelum lanjut.',
                            'Purchase Order (PO) — PO resmi ke supplier, dibuat dari PR yang sudah disetujui, juga lewat approval.',
                            'Purchase Voucher (PV) — bukti pembayaran ke supplier, otomatis membuat jurnal akuntansi.',
                            'Purchase Receive (PRT) — penerimaan fisik barang dari supplier, 3 jalur berbeda tergantung sumbernya: PO biasa, barang titipan customer, atau hasil subkontrak (jadi barang setengah jadi/SFG).',
                        ],
                        'note' => 'PR dan PO masing-masing punya jenjang approval sendiri (tidak bisa dilewati) sebelum boleh lanjut ke tahap berikutnya.',
                    ],
                    [
                        'id' => 'mod-inv', 'icon' => '📦', 'name' => 'Inventory',
                        'desc' => 'Catatan stok barang secara real-time — bahan baku, barang jadi, maupun barang setengah jadi.',
                        'steps' => [
                            'Master Stok & Kartu Stok — lihat saldo stok per item, bisa difilter per jenis (Bahan Baku/Barang Jadi/Setengah Jadi).',
                            'Stock In — barang masuk (dari Purchase Receive, retur produksi, dll).',
                            'Stock Out — barang keluar (dipakai produksi, dikirim ke customer, dll).',
                            'Stock Return — pengembalian barang (ke supplier atau dari produksi).',
                            'Stock Adjustment — koreksi manual selisih stok fisik vs catatan sistem, perlu approval.',
                        ],
                        'note' => 'Setiap pergerakan stok tercatat permanen di Kartu Stok per item — tidak ada cara "menghapus" riwayat, koreksi selalu lewat transaksi Adjustment baru.',
                    ],
                    [
                        'id' => 'mod-prod', 'icon' => '🏭', 'name' => 'Production',
                        'desc' => 'Perencanaan, penjadwalan, dan pemantauan proses produksi di lantai pabrik.',
                        'steps' => [
                            'Work Order (WO) — dibuat otomatis dari Rencana Kirim Sales, 1 WO = 1 bagian produk (part).',
                            'Penjadwalan — WO dijadwalkan ke mesin lewat Papan Gantt (drag geser waktu) atau Papan Kanban (drag pindah status/mesin).',
                            'Eksekusi proses — urutan baku: Belah Plano → Cetak → Coating → Laminasi → Plong/Diecut → Lem → Kupas → Packing → Kirim (Coating SELALU sebelum Laminasi).',
                            'Production Output — catat hasil tiap tahap (qty jadi, qty gagal/scrap dengan kategori penyebabnya).',
                        ],
                        'note' => '1 pesanan dengan beberapa bagian produk (part) akan punya beberapa WO terkait — pantau semuanya lewat Monitoring Dashboard, bukan 1 per 1.',
                    ],
                    [
                        'id' => 'mod-hrm', 'icon' => '👥', 'name' => 'Human Resources',
                        'desc' => 'Data karyawan, struktur organisasi, jadwal kerja, sampai proses rekrutmen tenaga kerja baru.',
                        'steps' => [
                            'Master Employee & Struktur Organisasi — data karyawan lengkap dan posisi masing-masing dalam hierarki perusahaan.',
                            'Shift Roster — jadwalkan karyawan ke shift kerja per mesin/hari (drag nama dari "pool" ke slot jadwal).',
                            'Cuti/Sakit/Izin — pengajuan & approval, otomatis catat Alpha kalau Izin lebih dari 4x sebulan.',
                            'Rekrutmen — dari permintaan tenaga kerja baru, posting lowongan, seleksi kandidat (Pipeline Board drag-drop antar tahap), sampai kandidat diterima jadi karyawan.',
                        ],
                        'note' => 'Ada Portal Karyawan TERPISAH (ikon profil → Portal Karyawan) untuk karyawan lihat data diri sendiri — beda dari layar HR ini yang khusus HRD kelola semua karyawan.',
                    ],
                    [
                        'id' => 'mod-mdata', 'icon' => '🗄️', 'name' => 'Master Data',
                        'desc' => 'Data induk yang dipakai bersama oleh SEMUA modul lain — sumber kebenaran tunggal.',
                        'steps' => [
                            'Customer & Supplier — data mitra bisnis (alamat, kontak, termin pembayaran).',
                            'Produk & Item Bahan Baku — spesifikasi produk jadi dan bahan baku/material.',
                            'Satuan (UoM), Mata Uang, Wilayah — data referensi umum lintas modul.',
                        ],
                        'note' => 'Kalau data di sini salah/kurang, dampaknya menjalar ke SEMUA modul yang memakainya — mis. salah alamat Customer di sini akan salah juga di Customer Order & Delivery Order.',
                    ],
                    [
                        'id' => 'mod-finacc', 'icon' => '💰', 'name' => 'Finance & Accounting',
                        'desc' => 'Pencatatan keuangan dan akuntansi perusahaan, dari kas harian sampai laporan.',
                        'steps' => [
                            'Chart of Account (COA) — daftar akun akuntansi standar perusahaan.',
                            'Jurnal Kas/Bank (BKM/BKK/BBM/BBK) — pencatatan uang masuk/keluar, alur Draft → Post.',
                            'DP Invoice — tagihan uang muka ke customer.',
                        ],
                        'note' => 'Sebagian besar jurnal di sini tercipta OTOMATIS dari transaksi modul lain (Purchase Voucher, Stock Out bahan baku) — jangan input manual dobel untuk transaksi yang sudah tercatat otomatis.',
                    ],
                    [
                        'id' => 'mod-setup', 'icon' => '🔒', 'name' => 'Setup & Security',
                        'desc' => 'Pengaturan akun, hak akses, dan administrasi sistem — khusus akun admin.',
                        'steps' => [
                            'Pengguna — buat & kelola akun login (username/nama/grup), reset password, aktifkan/nonaktifkan akun.',
                            'Grup — kumpulan pengguna yang berbagi hak akses sama.',
                            'Hak Akses Grup — atur izin per modul (Akses/Input/Approve/Export) untuk tiap grup.',
                            'Migrasi Data Legacy — tarik data dari sistem lama (ScriptCase) ke sistem baru.',
                        ],
                        'note' => 'Menu ini hanya muncul untuk akun admin — pengguna biasa tidak akan melihat grup Security di sidebar sama sekali.',
                    ],
                ];
            @endphp

            <div class="space-y-4">
                @foreach ($modules as $m)
                    <section id="{{ $m['id'] }}" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5">
                        <h2 class="text-[16px] font-bold text-navy mb-1">{{ $m['icon'] }} {{ $m['name'] }}</h2>
                        <p class="text-[13px] text-[#333a4d] mb-3">{{ $m['desc'] }}</p>
                        <p class="text-[11px] font-bold text-muted uppercase mb-2">Alur / Tahapan</p>
                        <ol class="space-y-1.5 text-[12.5px] text-[#333a4d] list-decimal list-inside mb-3">
                            @foreach ($m['steps'] as $step)
                                <li>{{ $step }}</li>
                            @endforeach
                        </ol>
                        <p class="text-[11.5px] text-[#8a6d00] bg-[#fef6e7] border border-[#f5e0b3] rounded-md px-3 py-2">💡 {{ $m['note'] }}</p>
                    </section>
                @endforeach
            </div>
        @endif

        <div class="text-center text-[11.5px] text-muted py-6">
            {{ Translator::t('shell_help_not_found_prompt', 'Tidak menemukan yang dicari?') }} <button type="button" wire:click="$set('tab', 'tickets')" class="text-accent font-bold underline">{{ Translator::t('shell_help_send_to_support', 'Kirim tiket ke tim support →') }}</button>
        </div>
    </div>
</div>
