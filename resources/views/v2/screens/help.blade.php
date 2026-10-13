{{-- REMARK
     Tujuan: pusat bantuan (F1): peta menu + panduan per layar, pedoman, alur (Peta Alur besar + Flows), tiket.
     Sumber hulu: sys_app_menu (lewat App\Support\V2\HelpMap), sys_help_topic / sys_help_line, App\Support\V2\Flows, sys_support_ticket;
     gambar Peta Alur di public/images/peta-alur/ (docs/attachments/peta-alur/).
     Input: ?tab=topics|guide|flow|tickets, ?topic=<slug layar> (dikirim Shell saat F1 dari layar aktif).
     Output: halaman di laci Side Screen; tombol Buka layar -> Shell membuka tab, laci tetap terbuka (melayang).
     Dipakai oleh: rute v2.help. Status: aktif (dummy mockup). --}}
{{-- Help & support v2: laci LEBAR dari kanan (F1 / Profile > Help & support). Empat bagian (user 2026-10-04):
     Help Topics = Help Center (semua topik per layar), User's Guide = panduan umum (sys_help_topic tipe GUIDE) + pedoman,
     Application's Flow = alur dokumen (App\Support\V2\Flows), Tickets = kirim tiket + lampiran dan balasan tim developer. --}}
@php
    use App\Models\Sys\SysHelpLine;
    use App\Models\Sys\SysHelpTopic;
    use App\Models\Sys\SysSupportTicket;
    use App\Services\Help\HelpCenter;
    use App\Support\Localization\Dictionary;
    use App\Support\V2\Flows;
    use App\Support\V2\HelpMap;
    use App\Support\V2\Src;
    use Devlib\Locale;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Storage;

    $tab = request('tab', 'topics');
    $map = HelpMap::payload(Auth::user());
    $help = fn (?string $en) => Dictionary::t($en, HelpCenter::CONTEXT);
    $guides = SysHelpTopic::query()->where('activate', true)->where('topic_type', SysHelpTopic::GUIDE)
        ->with(['lines' => fn ($q) => $q->where('activate', true)->orderBy('sort_order')])->orderBy('sort_order')->orderBy('title')->get();
    $tickets = SysSupportTicket::with(['replies', 'attachments'])->where('created_by_user_id', Auth::id())->orderByDesc('id_ticket')->limit(20)->get();
    $statusLabel = ['OPEN' => 'Open', 'IN_PROGRESS' => 'In progress', 'DONE' => 'Done', 'CLOSED' => 'Closed'];
    $statusTone = ['OPEN' => 'warn', 'IN_PROGRESS' => 'info', 'DONE' => 'success', 'CLOSED' => 'neutral'];
@endphp

<x-dl::side-screen.layout :sections="['topics' => ['Help Topics', 'fa-life-ring'], 'guide' => ['User\'s Guide', 'fa-book-open'], 'flow' => ['Application\'s Flow', 'fa-diagram-project'], 'tickets' => ['Tickets', 'fa-ticket', $tickets->whereIn('status', ['OPEN', 'IN_PROGRESS'])->count() ?: null]]" :active="$tab" :hints="['topics' => 'Pick a screen in the menu tree to read its guide: purpose, steps and each field. Filter by module or type; F1 opens the guide of the screen you are on.', 'guide' => 'Read the guides from top to bottom the first time; later, open the one you need. Each guide = numbered steps plus an explanation of the parts of the screen.', 'flow' => 'Point the mouse at a block to see its short rule; click a block to open its screen.', 'tickets' => 'Send a ticket to the developer team with screenshots; the reply appears here.']" :crumbs="$crumbs">
    <x-slot:top>
        @if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
        @if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif
    </x-slot:top>
    <x-slot:topics>
        <x-dl::help-map :tree="$map['tree']" :topics="$map['topics']" :current="request('topic')" :copy-url="route('v2.help')" />
    </x-slot:topics>

    <x-slot:guide>
            {{-- Pedoman tertulis = bagian dari User's Guide (user 2026-10-04), bukan tautan terpisah di rel. --}}
            <x-dl::card title="Written guides" description="The complete ERP rules and the Master Product & Revision guide." icon="fa-book">
                <x-dl::action-bar align="start">
                    <x-dl::button variant="secondary" icon="fa-book" label="ERP Rules Guide" :href="route('v2.guide', 'aturan')" />
                    <x-dl::button variant="secondary" icon="fa-book-open" label="Guide: Master Product & Revision" :href="route('v2.guide', 'product-master')" />
                </x-dl::action-bar>
            </x-dl::card>
            @foreach ($guides as $i => $g)
                @php
                    $steps = $g->lines->where('line_type', SysHelpLine::STEP)->values();
                    $fields = $g->lines->where('line_type', SysHelpLine::FIELD)->values();
                @endphp
                <x-dl::card :title="raw(($i + 1).'. '.$help($g->title))" :description="raw($help($g->purpose))" icon="fa-circle-check">
                    @if ($steps->isNotEmpty())
                        <x-dl::table :columns="['n' => 'Step', 'text' => 'What to do']" :rows="$steps->map(fn ($l, $k) => ['n' => $k + 1, 'text' => $help($l->body)])->all()" />
                    @endif
                    @if ($fields->isNotEmpty())
                        <x-dl::table :columns="['field' => 'Part of the screen', 'text' => 'Explanation']" :rows="$fields->map(fn ($l) => ['field' => Dictionary::t($l->field_label), 'text' => $help($l->body)])->all()" />
                    @endif
                </x-dl::card>
            @endforeach
    </x-slot:guide>

    <x-slot:flow>
            @php
                // Isi Peta Alur = pratinjau Jo (docs/attachments/peta-alur/help-support-preview.html); TERBACA = terlihat di gambar, DUGAAN = tafsiran belum terverifikasi.
                $T = 'TERBACA'; $D = 'DUGAAN';
                $legend = $T.' = terlihat langsung di gambar. '.$D.' = tafsiran, belum terverifikasi.';
                $lines = [
                    ['Loop atas', 'Sisi kanan PRO COST', 'Naik, ke kiri, turun masuk ke IQ PROD (satu arah)', $T],
                    ['Inquiry', 'CUSTOMER (No)', 'Naik di sisi kiri, masuk ke sisi kiri IQ PROD', $T],
                    ['Keputusan', 'QUO (baris atas)', 'CUSTOMER; Yes turun ke QUO, CO, SHIP SCHEDULE', $T],
                    ['POST', 'SHIP SCHEDULE', 'Titik POST, lalu masuk ke WO dari kiri', $T],
                    ['Ke pembelian', 'Tepi kanan blok atas (setelah MRP)', 'PR > PO > PV', $T],
                    ['PV ke WO', 'Sisi bawah PV', 'Belok kiri, turun masuk WO dari atas', $T],
                    ['PV ke tagihan', 'Sisi kanan PV', 'PURCHASE INVOICE (kotak merah muda di kanan atas)', $T],
                    ['Engine', 'ENGINE', 'Panah hijau tebal ke WO', $T],
                    ['WO ke jadwal', 'WO (sisi kanan)', 'Dua garis ke GANTT CHART; GANTT > RESCHEDULE JOBS', $T],
                    ['QC ke gudang', 'Sisi kanan area QC (di bawah BAD)', 'Naik, masuk WAREHOUSE PRODUCT dari kiri (satu-satunya masukan gudang)', $T.'; apakah hanya Good: '.$D],
                    ['Pengiriman', 'WAREHOUSE PRODUCT', 'DELIVERY; di dalamnya DO > DN', $T],
                    ['Tagihan', 'DELIVERY', 'CUSTOMER INVOICE', $T],
                ];
                $steps = [
                    ['1 Permintaan', 'IQ PROD', 'Inquiry dari customer', 'Diteruskan ke PRO DEV dan PRO COST', 'Sales', $T.' urutan, '.$D.' isi data'],
                    ['2 Pengembangan', 'PRO DEV', 'Spesifikasi produk dari inquiry', 'Produk siap dihitung biayanya', 'Engineering', $D.' isi'],
                    ['3 Biaya', 'PRO COST', 'Produk dan kebutuhan bahan/proses', 'Harga untuk QUO; garis kembali ke IQ PROD', 'Costing', $T.' loop, '.$D.' isi'],
                    ['4 Penawaran', 'QUO', 'Hasil costing', 'Penawaran ke CUSTOMER', 'Sales', $T],
                    ['5 Resep produksi', 'BOP, BOM', 'Produk yang disetujui', 'Daftar proses dan daftar bahan', 'Engineering', $D.' singkatan (BOP diduga Bill of Process)'],
                    ['6 Perencanaan', 'BOOKING, MRP', 'BOP dan BOM', 'Kebutuhan bahan ke PR', 'PPIC dan Purchasing', $T.' urutan, '.$D.' arti BOOKING'],
                    ['7 Keputusan', 'CUSTOMER', 'QUO', 'Yes lanjut ke CO; No kembali ke Inquiry', 'Sales', $T],
                    ['8 Pesanan', 'QUO, CO, SHIP SCHEDULE', 'Persetujuan customer', 'Customer Order dan jadwal kirim; POST membuat WO', 'Sales', $T],
                    ['9 Pembelian', 'PR, PO, PV', 'Hasil MRP', 'PV punya dua keluaran: bahan ke WO, dan PURCHASE INVOICE (tagihan pembelian)', 'Purchasing', $T.' urutan, '.$D.' arti PV'],
                    ['10 Work Order', 'WO + Release Raw Mat', 'POST dari pesanan, bahan dari PR/PO/PV', 'WO ke Gantt dan Master Card; bahan dikeluarkan', 'PPIC', $T],
                    ['11 Engine', 'ENGINE', 'Target, waste, spill, setup', 'Angka hitung ke WO (panah hijau)', 'PPIC', $T],
                    ['12 Jadwal', 'GANTT CHART, RESCHEDULE JOBS', 'WO', 'Jadwal mesin; Reschedule menyinkron ulang', 'PPIC', $T.'; pemicu Reschedule '.$D],
                    ['13 Lantai produksi', 'MASTER CARD', 'WO dan jadwal', 'Waktu Setup dan Prod (start/pause/stop), status Job, qty Good/Bad dari QC', 'PPIC', $T],
                    ['14 Hilir', 'WAREHOUSE PRODUCT, DELIVERY (DO dan DN), CUSTOMER INVOICE', 'Hasil QC', 'Barang terkirim; proses Delivery menjadi dasar Customer Invoice', 'Gudang dan Finance', $T.'; DO/DN bagian Delivery (DIKONFIRMASI Jo)'],
                ];
                $loops = [
                    ['CUSTOMER Yes / No', 'Yes membawa QUO ke CO dan SHIP SCHEDULE. No kembali ke Inquiry di kiri atas.', $T],
                    ['PRO COST > IQ PROD', 'Garis di atas blok, dari sisi kanan PRO COST kembali ke IQ PROD (satu arah). Hasil biaya bisa mengubah permintaan.', $T.' garis, '.$D.' maknanya'],
                    ['GANTT <> RESCHEDULE', 'Ikon sinkron di Reschedule dan di List of Jobs. Pemicunya: mesin rusak, order mendesak, atau hasil QC.', $T.' ikon, '.$D.' pemicu'],
                ];
                $prompts = [
                    ['Prompt PPIC', 'WO dan Release Raw Mat; Engine (target, waste, spill, setup); Gantt Chart dan Reschedule Jobs; Master Card: Setup, Prod, List of Jobs; QC input qty Good/Bad; BOOKING dan MRP (bersama Purchasing)'],
                    ['Prompt Purchasing', 'PR, PO, PV; Purchase Invoice (tagihan pembelian, keluaran PV); penerimaan hasil MRP; penerimaan barang (GR) tersendiri: tidak tergambar'],
                    ['Prompt Maintenance', 'Tidak ada di gambar. Satu titik temu yang masuk akal: mesin rusak sebagai pemicu Reschedule Jobs ('.$D.'). Jadwal perawatan preventif, permintaan perbaikan dan sparepart belum tergambar sama sekali.'],
                    ['Belum punya prompt', 'Sales dan Costing: IQ PROD, QUO, CO, PRO COST; Engineering: PRO DEV, BOP, BOM; Gudang, Delivery, DO/DN, Customer Invoice'],
                ];
                $gaps = [
                    ['Cabang No tanpa alasan', 'Gambar tidak mencatat alasan penolakan atau revisi harga. Apakah No selalu membuat Inquiry baru, atau QUO yang sama direvisi?'],
                    ['Arti PV', 'PV mengalir ke WO dan ke Purchase Invoice, jadi kemungkinan besar bukti penerimaan barang yang menjadi dasar tagihan pembelian ('.$D.'). Tidak ada langkah terima barang (GR) tersendiri setelah PO. Benarkah PV = penerimaan barang pembelian?'],
                    ['Dua jalur ke WO tanpa gerbang', 'WO menerima POST (pesanan) dan PR/PO/PV (bahan), tetapi tidak ada syarat bahan lengkap sebelum rilis. Apakah WO boleh dirilis sebelum bahan tiba?'],
                    ['Pemicu Reschedule tidak tergambar', 'Apa yang memicunya: mesin rusak, order mendesak, atau Bad dari QC?'],
                    ['BAD tanpa jalur', 'Hanya ada satu garis keluar dari area QC ke Warehouse. Rework, reject, scrap atau WO tambahan tidak digambar. Ke mana qty Bad pergi, dan apakah memicu produksi ulang?'],
                    ['BOOKING', 'Booking mesin, bahan, atau slot jadwal tidak terbaca.'],
                    ['Tidak ada legenda warna', 'Warna asli mungkin menandai departemen, tetapi itu hanya dugaan.'],
                    ['Tidak tergambar sama sekali', 'Maintenance, QC dalam proses, subkon, retur, pembayaran customer, dan stok bahan baku.'],
                ];
                $keys = fn (array $rows) => array_map(fn ($r) => array_combine(range('a', chr(96 + count($r))), $r), $rows);
            @endphp
            <x-dl::card title="Big flow map" description="From Inquiry to Customer Invoice, as drawn by Jo. Scroll sideways on a narrow screen." icon="fa-map">
                <x-dl::alert tone="info" :message="raw($legend)" />
                <div class="mp-flowmap"><img src="{{ asset('images/peta-alur/flow-besar-tmerp-final.png') }}" alt="Flow besar tmerp: Inquiry, IQ PROD, PRO DEV, PRO COST, QUO, BOP, BOM, Booking, MRP, PR, PO, PV, Customer, CO, Ship Schedule, WO, Engine, Gantt, Reschedule, Master Card, QC, Warehouse Product, Delivery, Customer Invoice"></div>
            </x-dl::card>
            <x-dl::card title="Line by line" icon="fa-bezier-curve">
                <x-dl::table :columns="['a' => 'Line', 'b' => 'From', 'c' => 'To', 'd' => 'Status']" :rows="$keys($lines)" />
            </x-dl::card>
            <x-dl::card title="Step by step" icon="fa-list-ol">
                <x-dl::table :columns="['a' => 'Stage', 'b' => 'Box', 'c' => 'Input', 'd' => 'Output', 'e' => 'Prompt', 'f' => 'Status']" :rows="$keys($steps)" />
            </x-dl::card>
            <x-dl::card title="Decisions and loops" icon="fa-rotate">
                <x-dl::table :columns="['a' => 'Decision / loop', 'b' => 'Reading', 'c' => 'Status']" :rows="$keys($loops)" />
            </x-dl::card>
            <x-dl::card title="Split into prompts" description="Proposed mapping of the boxes to the prompts Jo asked for." icon="fa-sitemap">
                <x-dl::table :columns="['a' => 'Prompt', 'b' => 'Boxes']" :rows="$keys($prompts)" />
            </x-dl::card>
            <x-dl::card title="Gaps and open questions" icon="fa-circle-question">
                <x-dl::table :columns="['a' => 'Gap', 'b' => 'Question']" :rows="$keys($gaps)" />
            </x-dl::card>
            @foreach (['SALES', 'COSTING'] as $module)
                @php $flow = Flows::get($module); @endphp
                <x-dl::card :title="raw($flow['title'])" icon="fa-diagram-project">
                    <x-dl::flow :title="''" :steps="$flow['steps']" source="app/Support/V2/Flows.php" />
                </x-dl::card>
            @endforeach
    </x-slot:flow>

    <x-slot:tickets>
        <x-dl::form.row :cols="2">
            <x-dl::action-form action="{{ route('v2.support.ticket') }}" enctype="multipart/form-data" data-dl-guard>
                <input type="hidden" name="return" value="help">
                <x-dl::card title="Send a Ticket" description="The developer team answers in Setup > Support & Ticket; the reply appears here." icon="fa-paper-plane">
                    <x-dl::field.text name="screen_context" label="Screen / menu" :value="old('screen_context')" placeholder="E.g. Customer Order" :source="Src::of('tm2sys.sys_support_ticket.screen_context')" />
                    <x-dl::field.text name="description" label="Problem description" :value="old('description')" multiline required placeholder="What happened, and what did you expect?" :source="Src::of('tm2sys.sys_support_ticket.description')" />
                    <x-dl::field.upload name="files" :multiple="true" :preview="false" label="Attachments (screenshot, PDF, Excel; up to 5 files, 5 MB each)" accept=".jpg,.jpeg,.png,.pdf,.xlsx,.xls,.docx" :source="Src::of('tm2sys.sys_support_ticket_attachment.file_path')" />
                    <x-dl::action-bar><x-dl::button type="submit" icon="fa-paper-plane" label="Send ticket" /></x-dl::action-bar>
                </x-dl::card>
            </x-dl::action-form>
            <x-dl::card title="My Tickets" icon="fa-ticket">
                @forelse ($tickets as $t)
                    <x-dl::card :title="raw($t->ticket_no.' · '.Locale::dt($t->created_at))" :description="raw($t->screen_context ?: '')" icon="fa-ticket">
                        <x-dl::badge :label="$statusLabel[$t->status] ?? $t->status" :tone="$statusTone[$t->status] ?? 'neutral'" />
                        <p>{{ $t->description }}</p>
                        @if ($t->attachments->isNotEmpty())
                            <x-dl::action-bar align="start">
                                @foreach ($t->attachments as $a)
                                    <x-dl::button variant="secondary" icon="fa-paperclip" :label="raw(basename($a->file_path))" :href="Storage::disk('public')->url($a->file_path)" target="_blank" />
                                @endforeach
                            </x-dl::action-bar>
                        @endif
                        @if ($r = $t->replies->last())<x-dl::field.display :label="raw(t('Reply from').' '.$r->author)" :value="$r->body" />@endif
                        @if ($t->resolution_note)<x-dl::field.display label="Resolution" :value="$t->resolution_note" />@endif
                    </x-dl::card>
                @empty
                    <x-dl::empty-state title="No tickets yet" message="Tickets you send appear here together with the support team replies." icon="fa-ticket" />
                @endforelse
            </x-dl::card>
        </x-dl::form.row>
    </x-slot:tickets>
</x-dl::side-screen.layout>
