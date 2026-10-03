<?php

use App\Console\Commands\DdtBaseline;
use App\Console\Commands\DdtStage;
use App\Mail\EmailVerifyMail;
use App\Models\Acc\FinPettyCashTx;
use App\Models\Master\FlowConfig;
use App\Models\Master\MatCategory;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Master\ProdProductAcc;
use App\Models\Master\SysHoliday;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PqQuotation;
use App\Models\Pur\PrRequisition;
use App\Models\Pur\PvReceipt;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\CrmActivity;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sys\SysConstant;
use App\Models\Sys\SysDocShare;
use App\Models\Sys\SysMailSetting;
use App\Models\Sys\SysSupportTicket;
use App\Providers\AssistantHostProvider;
use App\Services\Acc\PettyCashService;
use App\Services\Docs\DocShareService;
use App\Services\Master\MaterialCategoryService;
use App\Services\ProDev\PartDieline;
use App\Services\Pur\SpecialReceiptService;
use App\Services\Sales\CpdelPromiseService;
use App\Services\Sales\DeliveryPromiseService;
use App\Services\Sales\InquiryAssembly;
use App\Services\Sales\InquiryDerivedWriter;
use App\Services\Sales\InquiryDuplicator;
use App\Services\Sales\InquiryStage;
use App\Services\Sales\InquiryWriter;
use App\Support\Branding\Brand;
use App\Support\Branding\CompanyLogo;
use App\Support\Engines\EngineRegistry;
use App\Support\Localization\Dictionary;
use App\Support\Mail\MailSettings;
use App\Support\V2\AppMenu;
use App\Support\V2\Board\Board;
use App\Support\V2\Data\Account;
use App\Support\V2\Data\ApprovalCenter;
use App\Support\V2\Data\ApprovalSetting;
use App\Support\V2\Data\Cpdel;
use App\Support\V2\Data\CrmActivities;
use App\Support\V2\Data\CrmComplaints;
use App\Support\V2\Data\CustomerOrder;
use App\Support\V2\Data\DeliveryOrder;
use App\Support\V2\Data\MailContacts;
use App\Support\V2\Data\PettyCash;
use App\Support\V2\Data\Prodev;
use App\Support\V2\Data\PurchaseOrder;
use App\Support\V2\Data\PurchaseQuotation;
use App\Support\V2\Data\PurchaseReceive;
use App\Support\V2\Data\PurchaseRequisition;
use App\Support\V2\Data\Quotation;
use App\Support\V2\Data\SubconDelivery;
use App\Support\V2\Data\SupplierInvoiceReceipt;
use App\Support\V2\Data\WorkCalendar;
use App\Support\V2\Flows;
use App\Support\V2\Grid\DbGrid;
use App\Support\V2\Grid\DbLookup;
use App\Support\V2\Grid\Grids;
use App\Support\V2\InquiryPayload;
use App\Support\V2\Print\PrintDocs;
use App\Support\V2\Schema;
use App\Support\V2\Screens;
use Chat\ChatService;
use Devlib\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Layar v2 (objek DevLib, Menu v2 tanpa top bar). Dimuat di dalam grup auth + force.password routes/web.php.
// mockup.test (APP_UI=v2) mendarat di sini; tmerp.test tetap v1 sampai go-live.
Route::prefix('v2')->name('v2.')->group(function () {
    // ?open=<kode menu> = tab yang dibuka saat mulai (mis. Direksi -> MGMT_EXEC), bila belum ada tab yang dipulihkan.
    // Yang punya papan widget (top management, App\Support\V2\Board\Board) melihat papan itu sebagai layar pembuka.
    Route::get('/', fn (Request $r) => view('v2.home', ['modules' => AppMenu::modules(Auth::user()), 'user' => AppMenu::user(Auth::user()),
        'board' => Board::profile(Auth::user()) ? Board::opening(Auth::user()) : null,
        'open' => $r->query('open'), 'fresh' => (bool) session()->pull('dl_fresh_login'), 'profilePage' => session('v2_profile_page'), 'assistant' => AssistantHostProvider::drawer(Auth::user()), 'chat' => app(ChatService::class)->boot(Auth::user()),
        'account' => Account::shell(Auth::user()),
        'tickets' => SysSupportTicket::with('replies')->where('created_by_user_id', Auth::id())->orderByDesc('id_ticket')->limit(20)->get()]))->name('home');

    // Papan widget: GET = HTML papan terbaru (dipanggil sesudah perintah asisten), POST = satu aksi Customize lalu HTML terbaru.
    Route::match(['get', 'post'], '/board', function (Request $r) {
        abort_unless(Board::profile(Auth::user()), 403);
        if ($r->isMethod('post') && ($error = Board::apply(Auth::user(), (string) $r->input('action'), $r->input('key'), $r->input('to')))) {
            return response()->json(['message' => t($error)], 422);
        }

        return ['html' => Board::html(Auth::user())];
    })->name('board');

    // Tiket Bantuan dari halaman profil (menu akun / F1) -> tm2sys.sys_support_ticket, dijawab tim developer di Setup > Support Tickets.
    Route::post('/support/ticket', function (Request $r) {
        $v = Validator::make($r->all(), ['description' => ['required', 'string', 'max:5000'], 'screen_context' => ['nullable', 'string', 'max:150']],
            [], ['description' => t('Problem description')]);
        if ($v->fails()) {
            return redirect()->route('v2.home')->withErrors($v)->withInput()->with('v2_profile_page', 'ticket');
        }
        $d = $v->validated();
        $t = SysSupportTicket::create($d + ['ticket_no' => SysSupportTicket::nextNo(), 'created_by_user_id' => Auth::id(), 'status' => 'OPEN']);

        return redirect()->route('v2.home')->with(['v2_profile_page' => 'ticket', 'v2_flash' => tf('Ticket :no sent to the support team.', ['no' => $t->ticket_no])]);
    })->name('support.ticket');

    Route::post('/logout', function (Request $r) {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    // Satu item menu: layar v2 (target v2:slug), layar v1 tmerp (data sesuai .env situs), atau kartu "belum ada layar".
    Route::get('/item/{code}', function (string $code) {
        [$kind, $url, $item] = AppMenu::resolve($code);

        return $url ? redirect($url) : view('v2.missing', ['item' => $item]);
    })->name('item');

    Route::get('/s/{slug}', function (string $slug) {
        $screen = Screens::screen($slug) ?? Grids::screen($slug) ?? abort(404);
        abort_unless(AppMenu::open($slug), 404);
        abort_unless(Auth::user()->canAccessModule($screen['module_code'] ?? 'SALES'), 403);
        $file = resource_path("views/v2/screens/{$slug}.blade.php");

        if (isset($screen['grid'])) {
            $file = (new ReflectionClass($screen['grid']))->getFileName();

            return view('v2.screen', ['screen' => $screen, 'built' => true, 'code' => file_get_contents($file)]);
        }

        return view('v2.screen', ['screen' => $screen, 'built' => is_file($file), 'code' => is_file($file) ? file_get_contents($file) : '']);
    })->name('screen');

    // Cetakan dokumen transaksi (user 2026-10-01): A5 landscape untuk 1-5 baris, A4 portrait di atas itu. Tata letak = objek DevLib
    // x-dl::print-doc (Devlib\PrintDoc\Document); satu kelas per dokumen di App\Support\V2\Print. ?prices=0 = format tanpa harga.
    Route::get('/print/{type}/{id}', function (Request $r, string $type, int $id) {
        abort_unless(isset(PrintDocs::MAP[$type]), 404);
        $screen = PrintDocs::MAP[$type][1];
        abort_unless(Auth::user()->canAccessModule((Screens::screen($screen) ?? Grids::screen($screen))['module_code'] ?? 'SALES'), 403);
        $from = (Screens::screen((string) $r->query('from')) ?? Grids::screen((string) $r->query('from'))) ? (string) $r->query('from') : $screen;
        $prices = $r->query('prices', '1') !== '0';

        return view('v2.print-doc', ['doc' => PrintDocs::make($type, $id, ['prices' => $prices]), 'type' => $type, 'id' => $id, 'prices' => $prices,
            'back' => route('v2.screen', $from).(in_array($type, PrintDocs::BACK_TO_LIST, true) ? '' : '?id='.$id)]);
    })->whereNumber('id')->name('print');

    // Cetakan laporan grid (DbGrid::printable): objek DevLib x-dl::report, isi sama dengan grid di layar (periode ?from=&to=).
    Route::get('/report/{slug}/print', function (string $slug) {
        $grid = Grids::find($slug) ?? abort(404);
        abort_unless($grid->printable() && AppMenu::open($slug), 404);
        abort_unless(Auth::user()->canAccessModule($grid->module()), 403);

        return view('v2.print-report', ['grid' => $grid, 'company' => ['name' => Brand::companyName(), 'tagline' => Brand::tagline(),
            'initials' => mb_strtoupper(mb_substr(Brand::companyName(), 0, 2)), 'logo' => CompanyLogo::url(), 'logoBw' => CompanyLogo::url('bw')]]);
    })->name('report.print');

    // Aktif/Nonaktif baris Grid Master (App\Support\V2\Grid\DbGrid::toggle) -> status terbaru dari database.
    Route::post('/grid/{slug}/toggle/{id}', function (string $slug, int $id) {
        $grid = Grids::find($slug) ?? abort(404);
        abort_unless($grid->family() === DbGrid::MASTER && Auth::user()->canAccessModule($grid->module()), 403);

        return response()->json(['active' => $grid->toggle($id)]);
    })->name('grid.toggle');

    // Save Grid Lookup (App\Support\V2\Grid\DbLookup::save) -> isi terbaru dari database.
    Route::post('/lookup/{slug}/save', function (Request $r, string $slug) {
        $grid = Grids::find($slug) ?? abort(404);
        abort_unless($grid instanceof DbLookup && Auth::user()->canAccessModule($grid->module()), 403);
        try {
            return response()->json(['rows' => $grid->save((array) $r->input('changed', []))]);
        } catch (ValidationException $e) {
            return response()->json(['error' => collect($e->errors())->flatten()->first()], 422);
        }
    })->name('lookup.save');

    // Duplicate inquiry (spec sama, customer lain) -> NEW DRAFT baru; logika = App\Services\Sales\InquiryDuplicator (sama dgn v1).
    Route::post('/inquiry/duplicate', function (Request $r) {
        abort_unless(Auth::user()->canAccessModule('SALES'), 403);
        $data = $r->validate([
            'source_id' => ['required', 'integer', 'exists:tm2sdi.iqp_inquiry,id_inquiry'],
            'customer_id' => ['required', 'integer', 'exists:tm2mst.cust_customer,id_customer'],
        ], [], ['customer_id' => t('Target customer')]);
        $source = IqpInquiry::findOrFail($data['source_id']);
        $new = InquiryDuplicator::duplicate($source, (int) $data['customer_id'], Auth::user()->login);

        return redirect()->route('v2.screen', 'inquiry')
            ->with('v2_flash', tf('Duplicate created: :new (New Product, DRAFT) from :source.', ['new' => $new->inquiry_no, 'source' => $source->inquiry_no]));
    })->name('inquiry.duplicate');

    // Inquiry Baru v2 -> database situs (ddt* di mockup.test, tm2* live). Logika = App\Services\Sales\InquiryWriter (salinan persist*() v1).
    Route::prefix('inquiry')->name('inquiry.')->group(function () {
        $writer = fn () => new InquiryWriter(Auth::user()->login);
        $doc = function (int $id) {
            abort_unless(Auth::user()->canAccessModule('SALES'), 403);

            return IqpInquiry::findOrFail($id);
        };

        // Simpan / Confirm (action=confirm). Balasan JSON: url dokumen tersimpan untuk dimuat ulang.
        Route::post('/save', function (Request $r) use ($writer) {
            abort_unless(Auth::user()->canAccessModule('SALES'), 403);
            $old = $r->filled('id') ? IqpInquiry::findOrFail((int) $r->input('id')) : null;
            $r->validate(InquiryPayload::rules(), [], ['customer_id' => t('Customer'), 'type_code' => t('Product Type'), 'product_draft_name' => t('Product name')]);
            $img = ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:'.(int) (SysConstant::number('INQUIRY_ARTWORK_MAX_MB', 10) * 1024)];
            $r->validate(['parts.*.artwork' => $img, 'parts.*.artwork_inner' => $img]);
            $inq = $writer()->save(InquiryPayload::from($r, $old), $old, InquiryPayload::artworks($r));
            $confirm = $r->input('action') === 'confirm';
            if ($confirm) {
                $writer()->confirm($inq);
            }

            return response()->json(['id' => $inq->id_inquiry, 'url' => route('v2.screen', 'inquiry-new').'?id='.$inq->id_inquiry,
                'message' => $confirm ? tf('Inquiry :no confirmed — the form is locked, it is now in Pro-Dev › New.', ['no' => $inq->inquiry_no])
                    : tf('Saved as :no (:state).', ['no' => $inq->inquiry_no, 'state' => $inq->refresh()->sub_state])]);
        })->name('save');

        Route::post('/{id}/end', function (Request $r, int $id) use ($writer, $doc) {
            $inq = $doc($id);
            $data = $r->validate(['state' => ['required', 'in:CANCELLED,LOST'], 'reason' => ['required', 'string', 'max:255']], [], ['reason' => t('Reason')]);
            $data['state'] === 'CANCELLED' ? $writer()->cancel($inq, $data['reason']) : $writer()->markLost($inq, $data['reason']);

            return response()->json(['message' => $data['state'] === 'CANCELLED' ? tf('Inquiry :no cancelled.', ['no' => $inq->inquiry_no]) : tf('Inquiry :no marked as lost (LOST).', ['no' => $inq->inquiry_no])]);
        })->name('end');

        // Artwork boleh diganti setelah Confirm (user 2026-09-28): langsung tersimpan tanpa tombol Simpan.
        Route::post('/{id}/artwork', function (Request $r, int $id) use ($writer, $doc) {
            $inq = $doc($id);
            $r->validate(['part_id' => ['required', 'integer'], 'artwork' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.(int) (SysConstant::number('INQUIRY_ARTWORK_MAX_MB', 10) * 1024)]]);
            $part = $inq->parts()->findOrFail((int) $r->input('part_id'));
            $inner = $r->boolean('inner');
            $writer()->storeArtwork($part, $r->file('artwork'), $inner);

            return response()->json(['message' => $inner ? tf('Inner side artwork of :part saved.', ['part' => $part->part_name]) : tf('Artwork of :part saved.', ['part' => $part->part_name])]);
        })->name('artwork');

        // Quick-add customer dari Inquiry (status DRAFT, review Finance/Credit tetap wajib).
        Route::post('/customer', function (Request $r) use ($writer) {
            abort_unless(Auth::user()->canAccessModule('SALES'), 403);
            $d = $r->validate(['name' => ['required', 'string', 'max:200'], 'type' => ['required', 'in:local,import'], 'phone' => ['nullable', 'string', 'max:50'],
                'email' => ['nullable', 'email', 'max:150'], 'address' => ['nullable', 'string']], [], ['name' => t('Customer Name')]);
            $c = $writer()->quickCustomer($d + ['phone' => null, 'email' => null, 'address' => null]);

            return response()->json(['id' => $c->id_customer, 'label' => $c->customer_name, 'code' => $c->customer_code,
                'message' => tf('New customer ":name" (:code) created — status DRAFT.', ['name' => $c->customer_name, 'code' => $c->customer_code])]);
        })->name('customer');
    });

    // Master Product (docs/flow_product_master.md): nama diubah Sales, isian Proof Digital, catat ACC customer.
    Route::prefix('product/{id}')->name('product.')->whereNumber('id')->group(function () {
        $product = function (int $id) {
            abort_unless(Auth::user()->canAccessModule('SALES'), 403);

            return ProdProduct::findOrFail($id);
        };
        Route::post('/name', function (Request $r, int $id) use ($product) {
            $d = $r->validate(['product_name' => ['required', 'string', 'max:200']], [], ['product_name' => t('Product name')]);
            $product($id)->update($d + ['updated_by' => Auth::user()->login]);

            return back()->with('v2_flash', t('Product name saved.'));
        })->name('name');
        Route::post('/proof', function (Request $r, int $id) use ($product) {
            $d = $r->validate(['drawing_by' => ['nullable', 'string', 'max:100'], 'design_date' => ['nullable', 'date']], [], ['drawing_by' => t('Drawing by')]);
            $product($id)->update($d + ['updated_by' => Auth::user()->login]);

            return back()->with('v2_flash', t('Digital Proof fields saved.'));
        })->name('proof');
        Route::post('/acc', function (Request $r, int $id) use ($product) {
            $p = $product($id);
            $d = $r->validate(['acc_type' => ['required', 'in:'.implode(',', array_keys(ProdProductAcc::TYPES))], 'acc_at' => ['required', 'date'],
                'approved_by' => ['required', 'string', 'max:100'], 'channel' => ['required', 'in:'.implode(',', array_keys(ProdProductAcc::CHANNELS))],
                'note' => ['nullable', 'string', 'max:500'], 'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240']],
                [], ['acc_at' => t('ACC date & time'), 'approved_by' => t('ACC given by'), 'channel' => t('Channel'), 'evidence' => t('Evidence')]);
            $file = $r->file('evidence');
            ProdProductAcc::create(collect($d)->except('evidence')->all() + ['product_id' => $p->id_product,
                'evidence_path' => $file?->store("product/{$p->id_product}/acc", 'public'), 'evidence_filename' => $file?->getClientOriginalName(),
                'created_by' => Auth::user()->login, 'updated_by' => Auth::user()->login]);

            return back()->with('v2_flash', tf(':type recorded.', ['type' => t(ProdProductAcc::TYPES[$d['acc_type']])]));
        })->name('acc');
    });

    // Rakitan (ASSEMBLY) Inquiry Baru: kelompokkan part jadi rakitan + jenis sambungan (App\Services\Sales\InquiryAssembly).
    Route::prefix('inquiry/{id}/assembly')->name('inquiry.assembly.')->whereNumber('id')->middleware('module:SALES')->group(function () {
        $back = fn (int $id) => redirect(route('v2.screen', 'inquiry-new').'?id='.$id.'#rakitan');
        Route::post('/', function (Request $r, int $id) use ($back) {
            $d = $r->validate(['assembly_name' => ['required', 'string', 'max:100'], 'join_type_id' => ['required', 'integer'],
                'children' => ['required', 'array', 'min:2'], 'qty_in_parent' => ['nullable', 'numeric', 'min:0.0001']],
                ['children.min' => t('Choose at least 2 parts.')], ['assembly_name' => t('Assembly name'), 'join_type_id' => t('Join type'), 'children' => t('Part')]);
            try {
                (new InquiryAssembly(Auth::user()->login))->create(IqpInquiry::findOrFail($id), $d['assembly_name'], (int) $d['join_type_id'], $d['children'], (float) ($d['qty_in_parent'] ?? 1) ?: 1);
            } catch (InvalidArgumentException $e) {
                return $back($id)->withErrors(['children' => $e->getMessage()]);
            }

            return $back($id)->with('v2_flash', tf('Assembly ":name" created.', ['name' => $d['assembly_name']]));
        })->name('store');
        Route::post('/{part}/delete', function (int $id, int $part) use ($back) {
            (new InquiryAssembly(Auth::user()->login))->remove(IqpInquiry::findOrFail($id), $part);

            return $back($id)->with('v2_flash', t('Assembly taken apart; its parts stand alone again.'));
        })->whereNumber('part')->name('delete');
    });

    // Quotation v2 (App\Support\V2\Data\Quotation = aturan komponen v1 pages::quotation.entry). Customer tidak diketik: dari inquiry.
    Route::prefix('quotation')->name('quotation.')->middleware('module:SALES')->group(function () {
        $open = fn (int $id) => redirect(route('v2.screen', 'quotation').'?id='.$id);
        Route::post('/create', function (Request $r) use ($open) {
            $d = $r->validate(['inquiry_ids' => ['required', 'array', 'min:1'], 'inquiry_ids.*' => ['integer']], [], ['inquiry_ids' => t('Inquiry')]);
            try {
                $q = Quotation::create($d['inquiry_ids']);
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['inquiry_ids' => $e->getMessage()]);
            }

            return $open($q->id_quotation)->with('v2_flash', tf('Quotation :no created (DRAFT).', ['no' => $q->quotation_no]));
        })->name('create');

        Route::prefix('{id}')->whereNumber('id')->group(function () use ($open) {
            Route::post('/save', function (Request $r, int $id) use ($open) {
                $q = QuoQuotation::findOrFail($id);
                // Tombol Delete panel Form (x-dl::form) mengirim action=delete ke rute simpan yang sama.
                if ($r->input('action') === 'delete') {
                    Quotation::delete($q);

                    return redirect(route('v2.screen', 'quotation'))->with('v2_flash', tf('Draft :no deleted.', ['no' => $q->quotation_no]));
                }
                $r->validate(['issued_date' => ['required', 'date'], 'valid_until' => ['nullable', 'date'], 'deadline_date' => ['nullable', 'date'],
                    'items' => ['array'], 'items.*.description' => ['nullable', 'string', 'max:500'], 'items.*.qty_unit' => ['nullable', 'string', 'max:20']],
                    [], ['issued_date' => t('Document date')]);
                Quotation::save($q, $r->only(Quotation::HEADER), $r->has('cc_present') ? (array) $r->input('cc', []) : null,
                    (array) $r->input('items', []), array_keys((array) $r->input('remove', [])));
                $q->refresh();
                if ($r->input('action') === 'add_line') {
                    Quotation::addLine($q);

                    return $open($id)->with('v2_flash', t('Free line added — fill in the description, qty and price, then Save.'));
                }
                if ($r->input('action') === 'confirm') {
                    Quotation::confirm($q);

                    return $open($id)->with('v2_flash', tf('Quotation :no confirmed (issued). Send it to the customer through Print / Email.', ['no' => $q->quotation_no]));
                }

                return $open($id)->with('v2_flash', t('Quotation saved.'));
            })->name('save');
            Route::post('/add-inquiry', function (Request $r, int $id) use ($open) {
                $d = $r->validate(['inquiry_ids' => ['required', 'array', 'min:1']], [], ['inquiry_ids' => t('Inquiry')]);
                Quotation::addInquiries(QuoQuotation::findOrFail($id), $d['inquiry_ids']);

                return $open($id)->with('v2_flash', t('Inquiry added to the quotation.'));
            })->name('add-inquiry');
            Route::post('/delete', function (int $id) {
                $q = QuoQuotation::findOrFail($id);
                Quotation::delete($q);

                return redirect(route('v2.screen', 'quotation'))->with('v2_flash', tf('Draft :no deleted.', ['no' => $q->quotation_no]));
            })->name('delete');
            Route::post('/cancel', function (Request $r, int $id) use ($open) {
                $d = $r->validate(['cancel_reason' => ['required', 'string', 'max:255']], [], ['cancel_reason' => t('Cancel reason')]);
                Quotation::cancel(QuoQuotation::findOrFail($id), $d['cancel_reason']);

                return $open($id)->with('v2_flash', t('Quotation cancelled; its inquiries can be quoted again.'));
            })->name('cancel');
            Route::post('/lost', function (Request $r, int $id) use ($open) {
                $d = $r->validate(['lost_reason' => ['required', 'string', 'max:255']], [], ['lost_reason' => t('Lost Reason')]);
                Quotation::lost(QuoQuotation::with('items.inquiry')->findOrFail($id), $d['lost_reason']);

                return $open($id)->with('v2_flash', t('Quotation closed as LOST.'));
            })->name('lost');
            Route::post('/win', function (int $id) {
                $order = Quotation::win(QuoQuotation::with('items.inquiry')->findOrFail($id));

                return $order ? redirect(route('v2.screen', 'customer-order').'?id='.$order)->with('v2_flash', t('WIN — Customer Order created from this quotation.'))
                    : back()->withErrors(['win' => t('A Customer Order cannot be created from this quotation.')]);
            })->name('win');
            Route::post('/activity', function (Request $r, int $id) use ($open) {
                Quotation::addActivity(QuoQuotation::findOrFail($id), (array) $r->input('act', []));

                return $open($id)->with('v2_flash', t('Follow-up recorded.'));
            })->name('activity');
            Route::post('/activity/{aid}/delete', function (int $id, int $aid) use ($open) {
                Quotation::deleteActivity(QuoQuotation::findOrFail($id), $aid);

                return $open($id)->with('v2_flash', t('Follow-up deleted.'));
            })->whereNumber('aid')->name('activity.delete');
        });
    });

    // Purchase Requisition v2 (App\Support\V2\Data\PurchaseRequisition = aturan komponen v1 pages::pur.pr.entry). Satu rute simpan:
    // action = save | confirm (Submit ke flow approval) | approve | reject | hold | delete | add_material | add_free.
    Route::prefix('pr')->name('pr.')->middleware('module:PROC')->group(function () {
        $open = fn (int $id) => redirect(route('v2.screen', 'purchase-requisition').'?id='.$id);
        Route::post('/create', function (Request $r) use ($open) {
            $d = $r->validate(['department_id' => ['required', 'integer']], [], ['department_id' => t('Department')]);
            $pr = PurchaseRequisition::create((int) $d['department_id']);

            return $open($pr->pr_id)->with('v2_flash', t('PR draft created — add items, then Confirm (submit).'));
        })->name('create');
        Route::post('/{id}/save', function (Request $r, int $id) use ($open) {
            $pr = PrRequisition::findOrFail($id);
            $action = (string) $r->input('action', 'save');
            if (in_array($action, ['approve', 'reject', 'hold'], true)) {
                $err = PurchaseRequisition::decide($pr, $action, (string) $r->input('_reason', ''));

                return $err ? $open($id)->withErrors(['approval' => $err]) : $open($id)->with('v2_flash', tf('Approval decision saved (:action).', ['action' => ucfirst($action)]));
            }
            if ($action === 'delete') {
                PurchaseRequisition::delete($pr);

                return redirect(route('v2.screen', 'purchase-requisition'))->with('v2_flash', t('Draft PR deleted.'));
            }
            $r->validate(['i_doc_date' => ['nullable', 'date'], 'i_need_date' => ['nullable', 'date'], 'lines.*.i_need_date' => ['nullable', 'date']]);
            PurchaseRequisition::save($pr, $r->all());
            $msg = t('PR saved.');
            if ($action === 'add_material') {
                if (! $r->filled('material_id')) {
                    return $open($id)->withErrors(['add' => t('Choose an item from the master.')]);
                }
                PurchaseRequisition::addMaterial($pr->refresh(), (int) $r->input('material_id'));
                $msg = t('Master item added — fill in the qty & estimated price.');
            } elseif ($action === 'add_free') {
                if (trim((string) $r->input('free_description')) === '') {
                    return $open($id)->withErrors(['free' => t('Fill in the free item description.')]);
                }
                $err = PurchaseRequisition::addFree($pr->refresh(), (string) $r->input('free_description'), (string) $r->input('free_uom', ''), $r->integer('free_account_id') ?: null);
                if ($err) {
                    return $open($id)->withErrors(['free' => $err]);
                }
                $msg = t('Free item added.');
            } elseif ($action === 'confirm') {
                $pr->refresh()->load('lines');
                if ($pr->lines->isEmpty() || ! $pr->i_department_id) {
                    return $open($id)->withErrors(['confirm' => t('Cannot Confirm yet: the PR needs a department and at least 1 item.')]);
                }
                $blocked = PurchaseRequisition::submit($pr);
                $msg = $blocked ?: t('PR confirmed (submitted) — waiting for approval.');
            }

            return $open($id)->with('v2_flash', $msg);
        })->whereNumber('id')->name('save');
    });

    // Purchase Quotation v2 (App\Support\V2\Data\PurchaseQuotation): upload file penawaran -> PQ Draft terbaca -> cek -> Confirm ->
    // Bandingkan per PR -> Buat PO. action (save) = save | confirm | cancel | delete; Buat PO = POST pq/po.
    Route::prefix('pq')->name('pq.')->middleware('module:PROC')->group(function () {
        $home = fn () => route('v2.screen', 'purchase-quotation');
        $open = fn (int $id) => redirect($home().'?id='.$id);
        Route::post('/upload', function (Request $r) use ($home, $open) {
            $r->validate(['pr_id' => ['nullable', 'integer'], 'files' => ['required', 'array', 'min:1', 'max:10'], 'files.*' => ['file', 'max:20480']],
                [], ['files' => t('Quotation File'), 'files.*' => t('Quotation File')]);
            $made = [];
            $errors = [];
            foreach ($r->file('files') as $f) {
                try {
                    $made[] = PurchaseQuotation::upload($r->filled('pr_id') ? (int) $r->input('pr_id') : null, $f);
                } catch (InvalidArgumentException $e) {
                    $errors[] = $e->getMessage();
                }
            }
            if (count($made) === 1 && ! $errors) {
                return $open($made[0]->pq_id)->with('v2_flash', t('File read into a PQ Draft — check the yellow rows, then Confirm.'));
            }
            $back = redirect($home())->with('v2_flash', tf(':n file(s) became PQ Drafts — open them one by one to check.', ['n' => count($made)]));

            return $errors ? $back->withErrors(['files' => implode(' ', $errors)]) : $back;
        })->name('upload');
        Route::post('/{id}/save', function (Request $r, int $id) use ($home, $open) {
            $pq = PqQuotation::findOrFail($id);
            $action = (string) $r->input('action', 'save');
            if ($action === 'delete') {
                PurchaseQuotation::delete($pq);

                return redirect($home())->with('v2_flash', t('Draft PQ deleted.'));
            }
            if ($action === 'cancel') {
                $err = PurchaseQuotation::cancel($pq, (string) $r->input('_reason', ''));

                return $err ? $open($id)->withErrors(['cancel' => $err]) : $open($id)->with('v2_flash', t('PQ cancelled.'));
            }
            $r->validate(['i_doc_date' => ['nullable', 'date'], 'i_quotation_valid_until' => ['nullable', 'date'], 'i_fx_rate' => ['nullable', 'numeric', 'min:0']]);
            PurchaseQuotation::save($pq, $r->all());
            if ($action === 'confirm') {
                $err = PurchaseQuotation::confirm($pq->refresh());

                return $err ? $open($id)->withErrors(['confirm' => tf('Cannot Confirm yet: :errors', ['errors' => implode(' ', $err)])]) : $open($id)->with('v2_flash', tf('PQ :no confirmed — ready to be compared & turned into a PO.', ['no' => $pq->doc_no]));
            }

            return $open($id)->with('v2_flash', t('PQ saved.'));
        })->whereNumber('id')->name('save');
        // Buat PO dari baris PQ terpilih (layar Bandingkan atau tombol di form PQ).
        Route::post('/po', function (Request $r) {
            $ids = array_merge((array) $r->input('pq_line_ids', []), array_values(array_filter((array) $r->input('pick', []))));
            try {
                $pos = PurchaseQuotation::createPo($ids);
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['po' => $e->getMessage()]);
            }
            $first = array_key_first($pos);

            return redirect(route('v2.screen', 'purchase-order').(count($pos) === 1 ? '?id='.$first : ''))
                ->with('v2_flash', tf('Quotation lines went into :po — continue in Purchase Order.', ['po' => implode(', ', $pos)]));
        })->name('po');
    });

    // Kas Kecil v2 (App\Services\Acc\PettyCashService): kasbon / pertanggungjawaban / reimburse PO Kontan, isi ulang, setting.
    Route::prefix('petty')->name('petty.')->middleware('module:FINACC')->group(function () {
        $home = fn () => route('v2.screen', 'petty-cash');
        $fund = fn () => PettyCash::fund() ?? abort(404, t('There is no active Petty Cash yet.'));
        $run = function (callable $fn, string $ok) use ($home) {
            try {
                $tx = $fn();
            } catch (InvalidArgumentException $e) {
                return back()->withInput()->withErrors(['petty' => $e->getMessage()]);
            }

            return redirect($home())->with('v2_flash', str_replace(':no', $tx->tx_no, $ok));
        };
        Route::post('/advance/{po}', function (Request $r, int $po) use ($run) {
            $d = $r->validate(['amount' => ['required'], 'receiver_name' => ['required', 'string', 'max:100']], [], ['amount' => t('Cash advance amount'), 'receiver_name' => t('Money receiver')]);

            return $run(fn () => PettyCashService::advance(PoOrder::findOrFail($po), (float) Locale::input($d['amount']), $d['receiver_name'], Auth::user()->login),
                t('Cash advance :no recorded — money handed over, the Advance / Petty Cash journal is posted.'));
        })->whereNumber('po')->name('advance');
        Route::post('/settle/{po}', function (Request $r, int $po) use ($run) {
            $r->validate(['nota' => ['required', 'array'], 'nota_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'], 'receiver_name' => ['nullable', 'string', 'max:100']],
                [], ['nota' => t('Receipt amount'), 'nota_file' => t('Receipt photo')]);
            $nota = collect((array) $r->input('nota'))->map(fn ($v) => (float) Locale::input($v))->all();
            $path = $r->file('nota_file')?->store('petty/'.now()->format('Ym'), 'public');

            return $run(fn () => PettyCashService::settle(PoOrder::findOrFail($po), $nota, $path, Auth::user()->login, (string) $r->input('receiver_name', '')),
                t(':no recorded — the PO price was replaced by the receipt amount, the PO is ready to be recorded in Goods Receipt (SPB).'));
        })->whereNumber('po')->name('settle');
        Route::post('/topup', function (Request $r) use ($run, $fund) {
            $d = $r->validate(['amount' => ['required'], 'bank_account_id' => ['required', 'integer'], 'tx_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:255']],
                [], ['amount' => t('Top-up amount'), 'bank_account_id' => t('Bank account'), 'tx_date' => t('Date')]);

            return $run(fn () => PettyCashService::topup($fund(), (float) Locale::input($d['amount']), (int) $d['bank_account_id'], $d['tx_date'], Auth::user()->login, (string) ($d['notes'] ?? '')),
                t('Top-up :no recorded — the Petty Cash / Bank journal is posted.'));
        })->name('topup');
        Route::post('/tx/{id}/cancel', function (Request $r, int $id) use ($home) {
            try {
                $tx = FinPettyCashTx::findOrFail($id);
                PettyCashService::cancel($tx, (string) $r->input('cancel_reason', ''), Auth::user()->login);
            } catch (InvalidArgumentException $e) {
                return back()->withInput()->withErrors(['cancel' => $e->getMessage()]);
            }

            return redirect($home())->with('v2_flash', tf('Transaction :no cancelled — its journal is reversed.', ['no' => $tx->tx_no]));
        })->whereNumber('id')->name('cancel');
        Route::post('/setup', function (Request $r) use ($home, $fund) {
            $d = $r->validate(['petty_name' => ['required', 'string', 'max:100'], 'custodian_user_id' => ['required', 'integer'], 'imprest_amount' => ['nullable']],
                [], ['petty_name' => t('Petty Cash Name'), 'custodian_user_id' => t('Custodian')]);
            $fund()->update(['petty_name' => $d['petty_name'], 'custodian_user_id' => (int) $d['custodian_user_id'],
                'imprest_amount' => filled($d['imprest_amount'] ?? null) ? (float) Locale::input($d['imprest_amount']) : null, 'updated_by' => Auth::user()->login]);

            return redirect($home())->with('v2_flash', t('Petty cash setting saved.'));
        })->name('setup');
    });

    // Purchase Order v2 (App\Support\V2\Data\PurchaseOrder = aturan komponen v1 pages::pur.po.entry). Satu rute simpan:
    // action = save | confirm (Submit) | approve | reject | hold | send (Kirim ke Supplier) | delete | add_alloc.
    Route::prefix('po')->name('po.')->middleware('module:PROC')->group(function () {
        $open = fn (int $id) => redirect(route('v2.screen', 'purchase-order').'?id='.$id);
        Route::post('/create', function (Request $r) use ($open) {
            $d = $r->validate(['supplier_id' => ['required', 'integer'], 'po_kind' => ['nullable', 'in:NORMAL,CASH'], 'cash_mode' => ['nullable', 'in:ADVANCE,REIMBURSE'],
                'shop_name' => ['nullable', 'string', 'max:150']], [], ['supplier_id' => t('Supplier')]);
            if (($d['po_kind'] ?? 'NORMAL') === 'CASH') {
                $po = PurchaseOrder::createCash((int) $d['supplier_id'], $d['cash_mode'] ?? 'ADVANCE', $d['shop_name'] ?? null);

                return $open($po->po_id)->with('v2_flash', t('Cash PO draft is ready — add items (Material Master / free description) or pull from a PR, fill in the qty & estimated price.'));
            }
            $po = PurchaseOrder::create((int) $d['supplier_id']);

            return $open($po->po_id)->with('v2_flash', t('PO draft is ready — pull lines from an approved PR.'));
        })->name('create');
        Route::post('/{id}/save', function (Request $r, int $id) use ($open) {
            $po = PoOrder::findOrFail($id);
            $action = (string) $r->input('action', 'save');
            if (in_array($action, ['approve', 'reject', 'hold'], true)) {
                $err = PurchaseOrder::decide($po, $action, (string) $r->input('_reason', ''));

                return $err ? $open($id)->withErrors(['approval' => $err]) : $open($id)->with('v2_flash', tf('Approval decision saved (:action).', ['action' => ucfirst($action)]));
            }
            if ($action === 'send') {
                PurchaseOrder::send($po);

                return $open($id)->with('v2_flash', t('PO marked as sent to the supplier (SENT) — ready to be received in Goods Receipt.'));
            }
            if ($action === 'delete') {
                PurchaseOrder::delete($po);

                return redirect(route('v2.screen', 'purchase-order'))->with('v2_flash', t('Draft PO deleted; the PR allocation is released again.'));
            }
            $r->validate(['i_doc_date' => ['nullable', 'date'], 'i_delivery_date' => ['nullable', 'date']]);
            PurchaseOrder::save($po, $r->all());
            $msg = t('PO saved.');
            if ($action === 'add_material') {
                if (! $r->filled('material_id')) {
                    return $open($id)->withErrors(['add' => t('Choose an item from the Material Master.')]);
                }
                PurchaseOrder::addMaterial($po->refresh(), (int) $r->input('material_id'));
                $msg = t('Item added — fill in the qty & estimated price.');
            } elseif ($action === 'add_free') {
                if (trim((string) $r->input('free_description')) === '') {
                    return $open($id)->withErrors(['free' => t('Fill in the item description.')]);
                }
                PurchaseOrder::addFree($po->refresh(), (string) $r->input('free_description'), (string) $r->input('free_uom', ''), $r->filled('free_account_id') ? (int) $r->input('free_account_id') : null);
                $msg = t('Free item added.');
            } elseif ($action === 'add_alloc') {
                if (! $r->filled('alloc_id')) {
                    return $open($id)->withErrors(['add' => t('Choose a PR line.')]);
                }
                PurchaseOrder::addAllocation($po->refresh(), (int) $r->input('alloc_id'));
                $msg = t('PR line pulled into the PO — fill in the price & discount.');
            } elseif ($action === 'confirm') {
                $po->refresh()->load('lines');
                if ($po->lines->isEmpty()) {
                    return $open($id)->withErrors(['confirm' => t('Cannot Confirm yet: the PO needs at least 1 line.')]);
                }
                $blocked = PurchaseOrder::submit($po);
                if ($po->refresh()->status === 'DRAFT') {
                    return $open($id)->withErrors(['confirm' => $blocked ?: t('Cannot Confirm yet.')]);
                }
                $msg = $blocked ?: ($po->isCash() ? t('Cash PO confirmed — waiting for the approval of the F&A Director.') : t('PO confirmed (submitted) — waiting for approval.'));
            }

            return $open($id)->with('v2_flash', $msg);
        })->whereNumber('id')->name('save');
    });

    // Penerimaan Barang / SPB v2 (App\Support\V2\Data\PurchaseReceive = aturan komponen v1 pages::pur.pv.entry).
    // action = save | confirm (Post: stok masuk + jurnal) | cancel (balik Post) | delete | add_lines.
    Route::prefix('pv')->name('pv.')->middleware('module:PROC')->group(function () {
        $open = fn (int $id) => redirect(route('v2.screen', 'purchase-receive').'?id='.$id);
        Route::post('/create', function (Request $r) use ($open) {
            $d = $r->validate(['supplier_id' => ['required', 'integer'], 'warehouse' => ['required', 'string'], 'po_line_ids' => ['required', 'array', 'min:1']],
                [], ['supplier_id' => t('Supplier'), 'warehouse' => t('Warehouse'), 'po_line_ids' => t('PO lines')]);
            try {
                $pv = PurchaseReceive::create((int) $d['supplier_id'], $d['warehouse'], $d['po_line_ids']);
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['po_line_ids' => $e->getMessage()]);
            }

            return $open($pv->pv_id)->with('v2_flash', tf('Goods receipt :no created — fill in the received qty, QC qty & quality, then Confirm (Post).', ['no' => $pv->label()]));
        })->name('create');
        // Bahan dari customer (wajib No. CO) & barang kembali dari subkon (SpecialReceiptService).
        Route::post('/create-customer', function (Request $r) use ($open) {
            $d = $r->validate(['co_id' => ['required', 'integer'], 'warehouse' => ['nullable', 'string']], [], ['co_id' => t('CO No.')]);
            try {
                $pv = SpecialReceiptService::createCustomer((int) $d['co_id'], $d['warehouse'] ?? null, Auth::user()->login);
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['co_id' => $e->getMessage()]);
            }

            return $open($pv->pv_id)->with('v2_flash', tf('Customer material receipt :no created — check the description & qty per line, then Confirm.', ['no' => $pv->label()]));
        })->name('create-customer');
        Route::post('/create-subcon', function (Request $r) use ($open) {
            $d = $r->validate(['supplier_id' => ['required', 'integer'], 'po_line_ids' => ['required', 'array', 'min:1'], 'do_id' => ['nullable', 'integer']],
                [], ['supplier_id' => t('Subcontractor'), 'po_line_ids' => t('Subcon PO lines')]);
            try {
                $pv = SpecialReceiptService::createSubcon((int) $d['supplier_id'], $d['po_line_ids'], $d['do_id'] ?? null, Auth::user()->login);
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['po_line_ids' => $e->getMessage()]);
            }

            return $open($pv->pv_id)->with('v2_flash', tf('Receipt from subcon :no created — fill in the returned qty & next process, then Confirm.', ['no' => $pv->label()]));
        })->name('create-subcon');
        // Cetak SPB jenis PO & Subkon (x-dl::print-out + doc-share); jenis Customer punya cetak sendiri (print-customer).
        Route::get('/{id}/print', function (int $id) {
            $pv = PvReceipt::with(['lines' => fn ($q) => $q->orderBy('line_no')])->whereIn('i_receipt_type', ['PO', 'SUBCON'])->findOrFail($id);
            $mst = DB::connection('tm2mst');
            $supplier = $mst->table('sup_supplier')->where('id_supplier', $pv->i_supplier_id)->first();
            $sub = $pv->i_receipt_type === 'SUBCON';
            $num = fn ($v) => (float) $v;
            $parties = array_filter([
                $sub ? 'Subcontractor' : 'Supplier' => [$supplier->supplier_name ?? '—', $supplier->default_address ?? ''],
                $sub ? 'Subcon PO' : 'Purchase Order' => $pv->c_po_doc_list ?: '—',
                'Received Date' => Locale::d($pv->i_doc_date),
                'Delivery Note No.' => $pv->i_supplier_do_no ?: '—',
                'Vehicle No.' => $pv->i_vehicle_no ?: '—',
                $sub ? 'Our Delivery Note (to subcon)' : 'Receiving Warehouse' => $sub
                    ? ($pv->i_do_id ? DB::connection('tm2sdi')->table('do_delivery')->where('id_delivery', $pv->i_do_id)->value('do_no') : '—')
                    : ($pv->i_warehouse_code ? $pv->i_warehouse_code.' · '.$mst->table('wh_warehouse')->where('warehouse_code', $pv->i_warehouse_code)->value('warehouse_name') : '—'),
            ]);
            $columns = $sub ? [
                ['key' => 'po', 'label' => 'Subcon PO'], ['key' => 'name', 'label' => 'Item', 'sub' => 'code'],
                ['key' => 'qty', 'label' => 'Qty Received', 'type' => 'number'], ['key' => 'uom', 'label' => 'UoM'],
                ['key' => 'next', 'label' => 'Sent To (next process)'], ['key' => 'short', 'label' => 'Shortage', 'type' => 'number'],
            ] : [
                ['key' => 'po', 'label' => 'PO'], ['key' => 'name', 'label' => 'Item Code & Name', 'sub' => 'code'],
                ['key' => 'qty_buy', 'label' => 'Qty', 'type' => 'number', 'group' => 'Purchase UoM'], ['key' => 'uom_buy', 'label' => 'UoM', 'group' => 'Purchase UoM'],
                ['key' => 'qty_stock', 'label' => 'Qty', 'type' => 'number', 'group' => 'Stock UoM'], ['key' => 'uom_stock', 'label' => 'UoM', 'group' => 'Stock UoM'],
                ['key' => 'ok', 'label' => 'Accepted', 'type' => 'number', 'group' => 'QC Result'], ['key' => 'reject', 'label' => 'Rejected', 'type' => 'number', 'group' => 'QC Result'],
                ['key' => 'lot', 'label' => 'Lot'],
            ];
            $rows = $pv->lines->map(fn ($l) => ['po' => $l->c_po_doc_no ? $l->c_po_doc_no.' #'.$l->c_po_line_no : '—', 'name' => $l->i_description, 'code' => $l->c_item_code ?: '',
                'qty' => $num($l->i_qty_purchase), 'uom' => $l->i_uom_purchase, 'next' => $l->i_next_process ?: '—', 'short' => (float) $l->c_qty_short ?: '',
                'qty_buy' => $num($l->i_qty_purchase), 'uom_buy' => $l->i_uom_purchase, 'qty_stock' => $num($l->i_qty_stock), 'uom_stock' => $l->i_uom_stock,
                'ok' => $num($l->c_qty_accepted), 'reject' => (float) $l->c_qty_rejected ?: '', 'lot' => $l->i_lot_no ?: ''])->all();

            return view('v2.print-receipt', ['pv' => $pv, 'supplier' => $supplier, 'parties' => $parties, 'columns' => $columns, 'rows' => $rows,
                'title' => $sub ? 'Subcon Return Receipt' : 'Goods Receipt Note', 'subtitle' => $sub ? 'Receipt from Subcontractor' : 'Goods Receipt Note (SPB)',
                'company' => ['name' => Brand::companyName(), 'tagline' => Brand::tagline(), 'initials' => mb_strtoupper(mb_substr(Brand::companyName(), 0, 2)),
                    'logo' => CompanyLogo::url(), 'logoBw' => CompanyLogo::url('bw')],
                'signatures' => $sub ? [['Handed Over By', 'Subcontractor / Driver'], ['Received By', 'Production / PPIC'], ['Checked By', 'Quality Control']]
                    : [['Handed Over By', 'Supplier / Driver'], ['Received By', 'Warehouse'], ['Checked By', 'Quality Control']]]);
        })->whereNumber('id')->name('print');
        // Tanda Terima Bahan Customer (x-dl::print-out + doc-share).
        Route::get('/{id}/print-customer', function (int $id) {
            $pv = PvReceipt::with(['lines' => fn ($q) => $q->orderBy('line_no')])->where('i_receipt_type', 'CUSTOMER')->findOrFail($id);
            $mst = DB::connection('tm2mst');
            $items = DB::connection('tm2sdi')->table('co_order_item')->whereIn('id_item', $pv->lines->pluck('i_co_line_id')->filter())->get(['id_item', 'line_seq', 'description'])->keyBy('id_item');
            $mat = $mst->table('mat_raw')->whereIn('material_id', $pv->lines->pluck('item_id')->filter())->pluck('material_code', 'material_id');

            return view('v2.print-customer-material', [
                'pv' => $pv, 'customer' => $mst->table('cust_customer')->where('id_customer', $pv->i_customer_id)->first(),
                'coNo' => DB::connection('tm2sdi')->table('co_order')->where('id_order', $pv->i_co_id)->value('co_no'),
                'warehouse' => $pv->i_warehouse_code ? $pv->i_warehouse_code.' · '.$mst->table('wh_warehouse')->where('warehouse_code', $pv->i_warehouse_code)->value('warehouse_name') : null,
                'company' => ['name' => Brand::companyName(), 'tagline' => Brand::tagline(), 'initials' => mb_strtoupper(mb_substr(Brand::companyName(), 0, 2)),
                    'logo' => CompanyLogo::url(), 'logoBw' => CompanyLogo::url('bw')],
                'rows' => $pv->lines->map(fn ($l) => ['co_item' => isset($items[$l->i_co_line_id]) ? '#'.$items[$l->i_co_line_id]->line_seq.' '.$items[$l->i_co_line_id]->description : '—',
                    'name' => $l->i_description, 'code' => $mat[$l->item_id] ?? '', 'qty' => (float) $l->i_qty_purchase, 'uom' => $l->i_uom_purchase, 'lot' => $l->i_lot_no ?: ''])->all(),
            ]);
        })->whereNumber('id')->name('print-customer');
        Route::post('/{id}/short', function (Request $r, int $id) use ($open) {
            $d = $r->validate(['pv_line_id' => ['required', 'integer'], 'short_qty' => ['required'], 'short_note' => ['required', 'string', 'max:200']],
                [], ['pv_line_id' => t('Line'), 'short_qty' => t('Short Qty'), 'short_note' => t('Remark')]);
            try {
                SpecialReceiptService::reportShortage(PvReceipt::findOrFail($id), (int) $d['pv_line_id'], (float) Locale::input($d['short_qty']), $d['short_note'], Auth::user()->login);
            } catch (InvalidArgumentException $e) {
                return $open($id)->withErrors(['short' => $e->getMessage()]);
            }

            return $open($id)->with('v2_flash', t('Shortage recorded — the received qty, Subcon PO and journal are corrected.'));
        })->whereNumber('id')->name('short');
        Route::post('/{id}/save', function (Request $r, int $id) use ($open) {
            $pv = PvReceipt::findOrFail($id);
            $action = (string) $r->input('action', 'save');
            if ($action === 'cancel') {
                [$ok, $m] = PurchaseReceive::cancel($pv, (string) $r->input('_reason', ''));

                return $ok ? $open($id)->with('v2_flash', $m) : $open($id)->withErrors(['cancel' => $m]);
            }
            if ($action === 'delete') {
                PurchaseReceive::delete($pv);

                return redirect(route('v2.screen', 'purchase-receive'))->with('v2_flash', tf(':no deleted.', ['no' => $pv->label()]));
            }
            $r->validate(['i_doc_date' => ['nullable', 'date']]);
            if ($pv->i_receipt_type !== 'PO') {
                SpecialReceiptService::save($pv, $r->all(), Auth::user()->login);
                if ($action === 'confirm') {
                    $err = SpecialReceiptService::post($pv->refresh(), Auth::user()->login);

                    return $err ? $open($id)->withErrors(['confirm' => tf('Cannot Confirm yet: :errors', ['errors' => implode(' ', $err)])])
                        : $open($id)->with('v2_flash', $pv->i_receipt_type === 'SUBCON' ? t('Subcon receipt confirmed — the Subcon PO & service journal are updated.') : t('Customer material receipt confirmed.'));
                }

                return $open($id)->with('v2_flash', t('Goods receipt saved.'));
            }
            PurchaseReceive::save($pv, $r->all());
            $msg = t('Goods receipt saved.');
            if ($action === 'add_lines') {
                if (! $r->filled('po_line_ids')) {
                    return $open($id)->withErrors(['add' => t('Choose PO lines.')]);
                }
                PurchaseReceive::addLines($pv->refresh(), (array) $r->input('po_line_ids'));
                $msg = t('PO lines added.');
            } elseif ($action === 'confirm') {
                [$ok, $m] = PurchaseReceive::post($pv);
                if (! $ok) {
                    return $open($id)->withErrors(['confirm' => tf('Cannot Confirm (Post) yet: :error', ['error' => $m])]);
                }
                $msg = $m;
            }

            return $open($id)->with('v2_flash', $msg);
        })->whereNumber('id')->name('save');
    });

    // Delivery Order v2 (App\Support\V2\Data\DeliveryOrder = aturan komponen v1 pages::dispatch.do.entry -> DispatchService).
    // action = save | pull (tarik jadwal kirim CO) | confirm | ship (Shipped) | cancel (alasan wajib). Surat Jalan memakai rute yang sama.
    Route::prefix('do')->name('do.')->middleware('module:SALES')->group(function () {
        $open = fn (int $id, string $slug = 'delivery-order') => redirect(route('v2.screen', $slug).'?id='.$id);
        Route::post('/create', function (Request $r) use ($open) {
            $d = $r->validate(['customer_id' => ['required', 'integer']], [], ['customer_id' => t('Customer')]);
            $do = DeliveryOrder::create((int) $d['customer_id']);

            return $open($do->id_delivery)->with('v2_flash', t('DO draft created — pull lines from the Customer Order delivery schedule.'));
        })->name('create');
        Route::post('/{id}/save', function (Request $r, int $id) use ($open) {
            $do = DoDelivery::findOrFail($id);
            $action = (string) $r->input('action', 'save');
            $slug = $r->input('from') === 'delivery-notes' ? 'delivery-notes' : 'delivery-order';
            if (in_array($action, ['confirm', 'ship', 'cancel'], true)) {
                if ($action === 'confirm' && $do->status === 'DRAFT') {
                    $err = DeliveryOrder::save($do, $r->all());
                    if ($err) {
                        return $open($id, $slug)->withErrors(['save' => $err]);
                    }
                }
                [$ok, $m] = DeliveryOrder::act($do->refresh(), $action, (string) $r->input('_reason', ''));

                return $ok ? $open($id, $slug)->with('v2_flash', ['confirm' => t('DO confirmed — the Delivery Note is ready to print.'), 'ship' => t('Goods have been shipped (SHIPPED).'), 'cancel' => t('DO cancelled.')][$action])
                    : $open($id, $slug)->withErrors(['action' => $m]);
            }
            $r->validate(['do_date' => ['nullable', 'date'], 'scheduled_ship_date' => ['nullable', 'date']]);
            $err = DeliveryOrder::save($do, $r->all());
            if ($action === 'pull') {
                if (! $r->filled('co_delivery_ids')) {
                    return $open($id, $slug)->withErrors(['save' => t('Choose a CO delivery schedule.')]);
                }
                $err = DeliveryOrder::pull($do->refresh(), (array) $r->input('co_delivery_ids')) ?: $err;
            }

            return $err ? $open($id, $slug)->withErrors(['save' => $err]) : $open($id, $slug)->with('v2_flash', $action === 'pull' ? t('Lines pulled from the CO delivery schedule.') : t('DO saved.'));
        })->whereNumber('id')->name('save');
    });

    // Surat Jalan Subkon v2 (App\Support\V2\Data\SubconDelivery, backlog02 19.1): action = save | confirm | cancel | delete.
    Route::prefix('subcon')->name('subcon.')->middleware('module:SALES')->group(function () {
        $home = fn () => route('v2.screen', 'subcon-delivery-note');
        $open = fn (int $id) => redirect($home().'?id='.$id);
        Route::post('/create', function (Request $r) use ($open) {
            $d = $r->validate(['supplier_id' => ['required', 'integer'], 'subcon_process' => ['required', 'string', 'max:80']], [], ['supplier_id' => t('Subcontractor'), 'subcon_process' => t('Subcontract Process')]);
            $do = SubconDelivery::create((int) $d['supplier_id'], $d['subcon_process']);

            return $open($do->id_delivery)->with('v2_flash', t('Draft created. Add the items, then Confirm.'));
        })->name('create');
        Route::post('/{id}/save', function (Request $r, int $id) use ($home, $open) {
            $do = SubconDelivery::find($id) ?? abort(404);
            $action = (string) $r->input('action', 'save');
            if ($action === 'delete') {
                SubconDelivery::delete($do);

                return redirect($home())->with('v2_flash', t('Draft deleted.'));
            }
            if ($action === 'cancel') {
                $err = SubconDelivery::act($do, 'cancel', (string) $r->input('_reason', ''));

                return $err ? $open($id)->withErrors(['cancel' => $err]) : $open($id)->with('v2_flash', t('Delivery note cancelled.'));
            }
            $r->validate(['do_date' => ['nullable', 'date'], 'scheduled_ship_date' => ['nullable', 'date'], 'supplier_id' => ['nullable', 'integer'], 'pr_id' => ['nullable', 'integer']]);
            SubconDelivery::save($do, $r->all());
            if ($action === 'confirm') {
                $err = SubconDelivery::act($do->refresh(), 'confirm');

                return $err ? $open($id)->withErrors(['confirm' => $err]) : $open($id)->with('v2_flash', t('Confirmed. The delivery note is ready to print.'));
            }

            return $open($id)->with('v2_flash', t('Saved.'));
        })->whereNumber('id')->name('save');
    });

    // Tanda Terima Tagihan Supplier v2 (App\Support\V2\Data\SupplierInvoiceReceipt): action = save | confirm | cancel | delete.
    Route::prefix('invoice-receipt')->name('invoice-receipt.')->middleware('module:FINACC')->group(function () {
        $home = fn () => route('v2.screen', 'supplier-invoice-receipt');
        $open = fn (int $id) => redirect($home().'?id='.$id);
        Route::post('/create', function (Request $r) use ($open) {
            $d = $r->validate(['supplier_id' => ['required', 'integer']], [], ['supplier_id' => t('Supplier')]);

            return $open(SupplierInvoiceReceipt::create((int) $d['supplier_id'])->id_invoice_receipt)->with('v2_flash', t('Draft created. Add the invoices, then Confirm.'));
        })->name('create');
        Route::post('/{id}/save', function (Request $r, int $id) use ($home, $open) {
            $doc = SupplierInvoiceReceipt::find($id) ?? abort(404);
            $action = (string) $r->input('action', 'save');
            if ($action === 'delete') {
                SupplierInvoiceReceipt::delete($doc);

                return redirect($home())->with('v2_flash', t('Draft deleted.'));
            }
            if ($action === 'cancel') {
                $err = SupplierInvoiceReceipt::act($doc, 'cancel', (string) $r->input('_reason', ''));

                return $err ? $open($id)->withErrors(['cancel' => t($err)]) : $open($id)->with('v2_flash', t('Receipt cancelled.'));
            }
            $r->validate(['receipt_date' => ['nullable', 'date'], 'planned_payment_date' => ['nullable', 'date']]);
            SupplierInvoiceReceipt::save($doc, $r->all());
            if ($action === 'confirm') {
                $err = SupplierInvoiceReceipt::act($doc->refresh(), 'confirm');

                return $err ? $open($id)->withErrors(['confirm' => t($err)]) : $open($id)->with('v2_flash', t('Confirmed. The receipt is ready to print.'));
            }

            return $open($id)->with('v2_flash', t('Saved.'));
        })->whereNumber('id')->name('save');
    });

    // CRM v2 (user 2026-10-02): aktivitas per customer + komplain / retur penjualan. Grup menu CRM dimatikan = rute ini ikut tertutup.
    Route::prefix('crm')->name('crm.')->middleware('module:SALES')->group(function () {
        $guard = fn (string $slug) => abort_unless(AppMenu::open($slug), 404);
        $actHome = fn () => route('v2.screen', 'crm-activity');
        $actRules = ['activity_date' => ['required', 'date'], 'next_action_date' => ['nullable', 'date'], 'activity_type' => ['required', 'string'], 'topic' => ['required', 'string']];

        // Aktivitas baru (customer dari langkah pilih customer / Customer 360; from = follow-up yang hasilnya dicatat).
        Route::post('/activity', function (Request $r) use ($guard, $actHome, $actRules) {
            $guard('crm-activity');
            $d = $r->validate(['customer_id' => ['required', 'integer', 'exists:tm2mst.cust_customer,id_customer']] + $actRules);
            $a = CrmActivities::save(CrmActivities::blank((int) $d['customer_id']), $r->all(), $r->integer('from') ?: null);

            return redirect($actHome().'?id='.$a->id_activity)->with('v2_flash', t('Activity recorded.'));
        })->name('activity.store');
        // action = save | delete | done | reopen.
        Route::post('/activity/{id}', function (Request $r, int $id) use ($guard, $actHome, $actRules) {
            $guard('crm-activity');
            $a = CrmActivity::query()->findOrFail($id);
            $action = (string) $r->input('action', 'save');
            if ($action === 'delete') {
                CrmActivities::delete($a);

                return redirect($actHome())->with('v2_flash', t('Activity deleted.'));
            }
            if (in_array($action, ['done', 'reopen'], true)) {
                CrmActivities::done($a, $action === 'done');

                return redirect($actHome().'?id='.$id)->with('v2_flash', $action === 'done' ? t('Follow-up marked as done.') : t('Follow-up reopened.'));
            }
            $r->validate($actRules);
            CrmActivities::save($a, $r->all());

            return redirect($actHome().'?id='.$id)->with('v2_flash', t('Saved.'));
        })->whereNumber('id')->name('activity.save');

        $home = fn () => route('v2.screen', 'crm-complaint');
        $open = fn (int $id) => redirect($home().'?id='.$id);
        Route::post('/complaint/create', function (Request $r) use ($guard, $open) {
            $guard('crm-complaint');
            $d = $r->validate(['customer_id' => ['required', 'integer', 'exists:tm2mst.cust_customer,id_customer'], 'complaint_type' => ['required', 'string']], [], ['customer_id' => t('Customer')]);

            return $open(CrmComplaints::create((int) $d['customer_id'], $d['complaint_type'])->id_complaint)->with('v2_flash', t('Draft created. Describe the complaint and add the products, then Confirm.'));
        })->name('complaint.create');
        // action = save | confirm | cancel | close | delete.
        Route::post('/complaint/{id}/save', function (Request $r, int $id) use ($guard, $home, $open) {
            $guard('crm-complaint');
            $doc = CrmComplaints::find($id) ?? abort(404);
            $action = (string) $r->input('action', 'save');
            if ($action === 'delete') {
                CrmComplaints::delete($doc);

                return redirect($home())->with('v2_flash', t('Draft deleted.'));
            }
            if ($action === 'cancel') {
                $err = CrmComplaints::act($doc, 'cancel', (string) $r->input('_reason', ''));

                return $err ? $open($id)->withErrors(['cancel' => t($err)]) : $open($id)->with('v2_flash', t('Complaint cancelled.'));
            }
            $r->validate(['complaint_date' => ['nullable', 'date']]);
            CrmComplaints::save($doc, $r->all());
            if (in_array($action, ['confirm', 'close'], true)) {
                $err = CrmComplaints::act(CrmComplaints::find($id), $action);
                $done = $action === 'confirm' ? ($doc->needs_qa ? t('Confirmed. The complaint is now on the QA desk.') : t('Confirmed.')) : t('Complaint closed.');

                return $err ? $open($id)->withErrors([$action => t($err)]) : $open($id)->with('v2_flash', $done);
            }

            return $open($id)->with('v2_flash', t('Saved.'));
        })->whereNumber('id')->name('complaint.save');
    });

    // QA: jawaban atas komplain customer (Production > Complaint Review). action = save | confirm. Dokumen komplain milik Sales tidak diubah.
    Route::post('/qc/complaint-review/{id}/save', function (Request $r, int $id) {
        abort_unless(AppMenu::open('qc-complaint-review'), 404);
        $doc = CrmComplaints::reviewable($id) ?? abort(404);
        $confirm = $r->input('action') === 'confirm';
        $err = CrmComplaints::saveReview($doc, $r->all(), $confirm);
        $back = redirect(route('v2.screen', 'qc-complaint-review').'?id='.$id);

        return $err ? $back->withErrors(['review' => t($err)]) : $back->with('v2_flash', $confirm ? t('Answer confirmed. Sales can now close the complaint.') : t('Saved.'));
    })->whereNumber('id')->middleware('module:PROD')->name('qc.complaint-review.save');

    // Customer Order v2 (App\Support\V2\Data\CustomerOrder = aturan komponen v1 pages::co.entry). Tombol "+" menyimpan isian dulu.
    Route::prefix('co')->name('co.')->middleware('module:SALES')->group(function () {
        $open = fn (int $id) => redirect(route('v2.screen', 'customer-order').'?id='.$id);
        Route::post('/create', function (Request $r) use ($open) {
            $d = $r->validate(['mode' => ['required', 'in:INTEGRATED,INSTANT'], 'quotation_id' => ['required_if:mode,INTEGRATED', 'nullable', 'integer'],
                'customer_id' => ['required_if:mode,INSTANT', 'nullable', 'integer']], [], ['quotation_id' => t('Quotation'), 'customer_id' => t('Customer')]);
            $o = $d['mode'] === 'INTEGRATED' ? CustomerOrder::createIntegrated((int) $d['quotation_id']) : CustomerOrder::createInstant((int) $d['customer_id']);

            return $o ? $open($o->id_order)->with('v2_flash', tf('Customer Order :no (:mode) is ready to be filled in.', ['no' => $o->co_no, 'mode' => $o->entry_mode]))
                : back()->withErrors(['quotation_id' => t('This quotation cannot become a Customer Order (not issued yet / LOST).')]);
        })->name('create');
        Route::post('/{id}/save', function (Request $r, int $id) use ($open) {
            $o = CoOrder::findOrFail($id);
            // Tombol Delete panel Form (x-dl::form) mengirim action=delete ke rute simpan yang sama.
            if ($r->input('action') === 'delete') {
                CustomerOrder::delete($o);

                return redirect(route('v2.screen', 'customer-order'))->with('v2_flash', tf('Draft :no deleted.', ['no' => $o->co_no]));
            }
            if (in_array($r->input('action'), ['approve', 'reject', 'hold'], true)) {
                $err = CustomerOrder::decide($o, (string) $r->input('action'), (string) $r->input('_reason', ''));

                return $err ? $open($id)->withErrors(['approval' => $err]) : $open($id)->with('v2_flash', tf('Approval decision saved (:action).', ['action' => ucfirst((string) $r->input('action'))]));
            }
            if ($r->input('action') === 'duplicate') {
                $new = CustomerOrder::duplicate($o);

                return $open($new->id_order)->with('v2_flash', tf('Copy of :source created as CO INSTANT :new (Draft) — correct it, then Confirm again.', ['source' => $o->co_no, 'new' => $new->co_no]));
            }
            if ($r->input('action') === 'cancel') {
                $err = CustomerOrder::cancel($o, (string) $r->input('_reason', ''));

                return $err ? $open($id)->withErrors(['cancel' => $err]) : $open($id)->with('v2_flash', tf('Customer Order :no CANCELLED — its delivery schedule is cancelled too.', ['no' => $o->co_no]));
            }
            $r->validate(['co_date' => ['nullable', 'date'], 'items.*.due_date' => ['nullable', 'date'], 'deliveries.*.ship_date' => ['nullable', 'date'],
                'product_id' => ['nullable', 'integer']], [], ['co_date' => t('CO Date')]);
            CustomerOrder::save($o, $r->all());
            $action = (string) $r->input('action', 'save');
            $msg = t('Customer Order saved.');
            if ($action === 'add_product') {
                if (! $r->filled('product_id')) {
                    return $open($id)->withErrors(['add' => t('Choose a Master Product.')]);
                }
                CustomerOrder::addProduct($o, (int) $r->input('product_id'));
                $msg = t('Product line added — fill in the qty & due date.');
            } elseif ($action === 'add_service') {
                CustomerOrder::addService($o);
                $msg = t('Service line added.');
            } elseif ($action === 'add_cost') {
                CustomerOrder::addCost($o);
                $msg = t('External cost added.');
            } elseif (str_starts_with($action, 'add_delivery_')) {
                CustomerOrder::addDelivery($o, (int) substr($action, 13));
                $msg = t('Delivery schedule added.');
            } elseif (str_starts_with($action, 'pick_qty_')) {
                // Customer memilih SATU qty per product; pilihan qty lain dari quotation dibuang dari CO (aturan user 2026-10-01).
                CustomerOrder::pickQty($o, (int) substr($action, 9));
                $msg = Dictionary::t('Qty chosen — the other qty options of that product were removed from this order.');
            } elseif ($action === 'confirm') {
                $o = CoOrder::with('items.deliveries')->findOrFail($id);
                if ($err = CustomerOrder::confirm($o)) {
                    return $open($id)->withErrors(['confirm' => $err]);
                }
                $msg = tf('Customer Order :no submitted — waiting for approval by the F&A SPV, then the Operational Manager. The delivery schedule (CPDEL) is created after the last approval.', ['no' => $o->co_no]);
            }

            return $open($id)->with('v2_flash', $msg);
        })->whereNumber('id')->name('save');
        Route::post('/{id}/delete', function (int $id) {
            $o = CoOrder::findOrFail($id);
            CustomerOrder::delete($o);

            return redirect(route('v2.screen', 'customer-order'))->with('v2_flash', tf('Draft :no deleted.', ['no' => $o->co_no]));
        })->whereNumber('id')->name('delete');
    });

    // Approval Center v2 (PR / PO / CO): keputusan approver tahap berjalan -> aturan layar dokumennya; log di flow_log.
    Route::post('/approval/{screen}', function (Request $r, string $screen) {
        $d = $r->validate(['id' => ['required', 'regex:/^[A-Z]+:\d+$/'], 'action' => ['required', 'in:approve,reject,hold'], '_reason' => ['nullable', 'string', 'max:200']]);
        [$module, $id] = explode(':', $d['id']);
        abort_unless(in_array($module, ApprovalCenter::SCREENS[$screen], true), 404);
        abort_unless(Auth::user()->canAccessModule(ApprovalCenter::MODULES[$module]['menu']), 403);
        $err = ApprovalCenter::decide($module, (int) $id, $d['action'], (string) ($d['_reason'] ?? ''));
        $back = redirect(route('v2.screen', 'approval-'.$screen));

        return $err ? $back->withErrors(['approval' => $err]) : $back->with('v2_flash', ['approve' => t('Approved — recorded in the log.'), 'reject' => t('Rejected — recorded in the log.'), 'hold' => t('On hold — recorded in the log.')][$d['action']]);
    })->whereIn('screen', array_keys(ApprovalCenter::SCREENS))->name('approval.decide');

    // Setting Approval + Officer Pengganti (PLT) -- App\Support\V2\Data\ApprovalSetting (tm2mst.flow_config*, flow_position_acting).
    Route::middleware('module:SETUP')->group(function () {
        $open = fn (int $id) => redirect(route('v2.screen', 'approval-setting').'?id='.$id);
        Route::post('/approval-setting/create', function (Request $r) use ($open) {
            $d = $r->validate(['module_code' => ['required', 'string', 'max:50'], 'notes' => ['nullable', 'string', 'max:200'], 'final_status' => ['nullable', 'string', 'max:30']],
                [], ['module_code' => t('Document Code')]);
            $c = ApprovalSetting::create($d['module_code'], (string) ($d['notes'] ?? ''), (string) ($d['final_status'] ?? ''));

            return $open($c->flow_config_id)->with('v2_flash', tf('Approval setting :code created — add the Levels, then Save.', ['code' => $c->module_code]));
        })->name('approval-setting.create');
        Route::post('/approval-setting/{id}', function (Request $r, int $id) use ($open) {
            $c = FlowConfig::findOrFail($id);
            $action = (string) $r->input('action', 'save');
            if (in_array($action, ['activate', 'deactivate'], true)) {
                ApprovalSetting::setActive($c, $action === 'activate');

                return $open($id)->with('v2_flash', $action === 'activate' ? tf('Approval setting :code activated.', ['code' => $c->module_code])
                    : tf('Approval setting :code deactivated (new documents do not go to approval).', ['code' => $c->module_code]));
            }
            ApprovalSetting::save($c, $r->all());

            return $open($id)->with('v2_flash', tf('Approval setting :code saved.', ['code' => $c->module_code]));
        })->whereNumber('id')->name('approval-setting.save');
        Route::post('/approval-acting', function (Request $r) {
            ApprovalSetting::saveActing($r->all());

            return redirect(route('v2.screen', 'approval-acting'))->with('v2_flash', t('Acting officer list (PLT) saved.'));
        })->name('approval-acting.save');
    });

    // Holiday Calendar v2 (App\Support\V2\Data\WorkCalendar): save / activate / deactivate satu hari libur.
    Route::post('/holiday/save', function (Request $r) {
        abort_unless(Auth::user()->canAccessModule('MDATA'), 403);
        $h = $r->filled('id') ? SysHoliday::findOrFail((int) $r->input('id')) : null;
        $action = (string) $r->input('action', 'save');
        if ($h && in_array($action, ['activate', 'deactivate'], true)) {
            $h->update(['activate' => $action === 'activate', 'updated_by' => Auth::user()->login]);
        } else {
            $h = WorkCalendar::saveHoliday($h, $r->all());
        }

        return redirect(route('v2.screen', 'holiday-calendar').'?year='.$h->holiday_date->year.'&id='.$h->id_holiday)
            ->with('v2_flash', Dictionary::t('Holiday saved.'));
    })->name('holiday.save');

    // Material Category v2 = pembentuk kode item (App\Services\Master\MaterialCategoryService): save / activate / deactivate satu
    // baris rantai Category 1 -> 5. Aturan rantai (posisi terkunci, prefix unik, kode tidak bentrok) ada di service.
    Route::post('/material-category/save', function (Request $r, MaterialCategoryService $svc) {
        abort_unless(Auth::user()->canAccessModule('MDATA'), 403);
        $node = $r->filled('id') ? MatCategory::findOrFail((int) $r->input('id')) : null;
        $action = (string) $r->input('action', 'save');
        $open = fn (MatCategory $n) => redirect(route('v2.screen', 'md-matcat').'?id='.$n->id_category);
        if ($node && in_array($action, ['activate', 'deactivate'], true)) {
            $svc->setActive($node, $action === 'activate', Auth::user()->login);

            return $open($node)->with('v2_flash', $action === 'activate' ? tf(':name activated.', ['name' => $node->category_name]) : tf(':name deactivated.', ['name' => $node->category_name]));
        }
        $saved = $svc->save($node, $r->all(), Auth::user()->login);

        return $open($saved)->with('v2_flash', tf(':name saved — item code :code.', ['name' => $saved->category_name, 'code' => $svc->code($saved->id_category)]));
    })->name('matcat.save');

    // Proof of Delivery v2: satu kedatangan di customer (jam tiba + hasil terima) = satu catatan KPI janji kirim.
    Route::post('/pod/{id}', function (Request $r, int $id) {
        abort_unless(Auth::user()->canAccessModule('SALES'), 403);
        $do = DoDelivery::whereIn('status', ['CONFIRMED', 'SHIPPED'])->findOrFail($id);
        $r->validate(['photo' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf']]);
        $pod = DeliveryPromiseService::record($do, $r->all(), $r->file('photo'), Auth::user()->login);

        return redirect(route('v2.screen', 'proof-of-delivery').'?id='.$id)
            ->with('v2_flash', Dictionary::t($pod->promise_kept ? 'Arrival recorded: promise kept.' : 'Arrival recorded: promise missed.'));
    })->name('pod.save');

    // Customer Plan Delivery v2 (App\Support\V2\Data\Cpdel = confirmSplit/confirmMerge v1 pages::co.delivery-plan).
    Route::prefix('cpdel')->name('cpdel.')->middleware('module:SALES')->group(function () {
        $open = fn (int $id) => redirect(route('v2.screen', 'cpdel').'?id='.$id);
        // Dua janji (App\Services\Sales\CpdelPromiseService): PPIC janji siap, Sales jadwal ulang / split setelah nego customer,
        // customer menolak -> eskalasi, Operational Manager memutuskan. Semua tercatat di co_delivery_negotiation.
        $line = fn (int $id) => Cpdel::query()->findOrFail($id);
        Route::post('/{id}/ready', function (Request $r, int $id) use ($open, $line) {
            abort_unless(CpdelPromiseService::canPlan(Auth::user()), 403);
            CpdelPromiseService::commitReady($line($id), $r->all(), Auth::user()->login);

            return $open($id)->with('v2_flash', Dictionary::t('Production promise saved.'));
        })->whereNumber('id')->name('ready');
        Route::post('/{id}/reschedule', function (Request $r, int $id) use ($open, $line) {
            abort_unless(CpdelPromiseService::canSell(Auth::user()), 403);
            $target = CpdelPromiseService::reschedule($line($id), $r->all(), Auth::user()->login);

            return $open($target?->id_delivery ?? $id)->with('v2_flash', Dictionary::t('Customer slot changed and logged.'));
        })->whereNumber('id')->name('reschedule');
        Route::post('/{id}/reject', function (Request $r, int $id) use ($open, $line) {
            abort_unless(CpdelPromiseService::canSell(Auth::user()), 403);
            CpdelPromiseService::customerRejected($line($id), $r->all(), Auth::user()->login);

            return $open($id)->with('v2_flash', Dictionary::t('Escalated to the Operational Manager.'));
        })->whereNumber('id')->name('reject');
        Route::post('/{id}/decide', function (Request $r, int $id) use ($open, $line) {
            abort_unless(CpdelPromiseService::canDecide(Auth::user()), 403);
            CpdelPromiseService::opsDecision($line($id), $r->all(), Auth::user()->login);

            return $open($id)->with('v2_flash', Dictionary::t('Decision saved. Production must commit a new ready time.'));
        })->whereNumber('id')->name('decide');
        Route::post('/merge', function (Request $r) use ($open) {
            abort_unless(CpdelPromiseService::canSell(Auth::user()), 403);
            $ids = array_map('intval', (array) $r->input('ids', []));
            $keep = (int) CoOrderDelivery::whereIn('id_delivery', $ids)->orderBy('ship_date')->value('id_delivery');
            $error = CpdelPromiseService::merge($ids, Auth::user()->login);

            return $error ? back()->withErrors(['merge' => $error]) : $open($keep)->with('v2_flash', Dictionary::t('Lines joined into the earliest date.'));
        })->name('merge');
        // Post to Production (aturan A.3): WO dibuat, baris boleh ditarik ke DO / Surat Jalan.
        Route::post('/{id}/post', function (int $id) use ($open) {
            $error = Cpdel::post($id);

            return $error ? $open($id)->withErrors(['post' => $error])
                : $open($id)->with('v2_flash', Dictionary::t('Posted to Production: Work Order created. This line can now be pulled into a Delivery Order.'));
        })->whereNumber('id')->name('post');
    });

    // Grid Pro-Dev New / Revise (satu baris = satu inquiry yang sudah Confirm Sales): buka = workbench part pertama yang belum
    // Confirmed. Part master dibuat di sini (pengganti tombol "Kirim ke ProDev" di form Sales; rulebook A.20).
    Route::get('/prodev/open/{inquiry}', function (int $inquiry) {
        $inq = IqpInquiry::findOrFail($inquiry);
        $part = InquiryStage::openInProdev($inq, Auth::user()->login);

        return $part
            ? redirect(route('v2.screen', 'prodev').'?part='.$part.'&inquiry='.$inq->id_inquiry)
            : redirect()->route('v2.screen', 'prodev-'.strtolower($inq->inquiry_type))->withErrors(['part' => tf('Inquiry :no has no part yet.', ['no' => $inq->inquiry_no])]);
    })->whereNumber('inquiry')->middleware('module:PROD')->name('prodev.open');

    // ProDev workbench: panggilan server = method komponen v1 pages::production.prodev (App\Support\V2\Data\Prodev).
    Route::prefix('prodev/{part}')->name('prodev.')->whereNumber('part')->middleware('module:PROD')->group(function () {
        Route::post('/call', function (Request $r, int $part) {
            $d = $r->validate(['method' => ['required', 'in:'.implode(',', Prodev::CALLS)], 'args' => ['array']]);

            $out = Prodev::call($part, $d['method'], $d['args'] ?? []);
            // Layout disimpan / di-Confirm -> tanda stat_confirm_prodev inquiry-nya dihitung ulang (1 saat semua part Confirmed).
            InquiryStage::syncProdevForParts([$part]);

            return response()->json($out);
        })->name('call');
        Route::post('/artwork', function (Request $r, int $part) {
            $r->validate(['file' => ['required', 'file']]);

            return response()->json(Prodev::uploadArtwork($part, $r->file('file')));
        })->name('artwork');
        // Panel Part & Dieline: ProDev berhak mengubah dieline & mockup part (docs/globaluserrules.md A.17).
        Route::post('/dieline', function (Request $r, int $part) {
            abort_unless(Prodev::dieline($part), 404);
            $img = ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:'.(int) (SysConstant::number('INQUIRY_ARTWORK_MAX_MB', 10) * 1024)];
            $r->validate(['dieline.dims' => ['required', 'array'], 'artwork' => $img, 'artwork_inner' => $img], [], ['dieline.dims' => t('Dimensions')]);
            $error = (new PartDieline(Auth::user()->login))->save(ProdPart::findOrFail($part), InquiryPayload::dieline($r->input('dieline')), $r->file('artwork'), $r->file('artwork_inner'));
            $back = redirect(route('v2.screen', 'prodev').'?part='.$part);

            return $error ? $back->withErrors(['artwork' => $error])
                : $back->with('v2_flash', Dictionary::t('Dieline saved. The imposition below now uses the new blank size.'));
        })->name('dieline');
    });

    // Inquiry Rekalkulasi / Revisi dari Master Product: Simpan (DRAFT) -> Confirm; Hapus selama DRAFT; Cancel sesudah Confirm.
    Route::prefix('derived')->name('derived.')->group(function () {
        $guard = fn () => abort_unless(Auth::user()->canAccessModule('SALES'), 403);
        $writer = fn () => new InquiryDerivedWriter(Auth::user()->login);
        $open = fn (IqpInquiry $d) => redirect(route('v2.screen', $d->inquiry_type === 'RECALC' ? 'inquiry-recalc' : 'inquiry-revise').'?id='.$d->id_inquiry);

        Route::post('/{type}', function (Request $r, string $type) use ($guard, $writer, $open) {
            $guard();
            $doc = $r->filled('id') ? IqpInquiry::where('inquiry_type', strtoupper($type))->findOrFail((int) $r->input('id')) : null;
            $product = ProdProduct::findOrFail((int) $r->input('product_id'));
            if ($type === 'recalc') {
                $d = $r->validate(['qty' => ['required', 'numeric', 'min:1'], 'deadline_quotation' => ['nullable', 'date']], [], ['qty' => t('Order qty')]);
                $doc = $writer()->recalc($product, $doc, $d + ['deadline_quotation' => null]);
            } else {
                $d = $r->validate(['revise_scope' => ['required', 'array', 'min:1'], 'revise_scope.*' => ['in:'.implode(',', array_keys(InquiryDerivedWriter::SCOPES))],
                    'revise_reason' => ['required', 'string', 'max:255'], 'design_number' => ['nullable', 'string', 'max:30'],
                    'parts.*.artwork' => ['nullable', 'image', 'max:10240'], 'parts.*.artwork_inner' => ['nullable', 'image', 'max:10240']],
                    [], ['revise_scope' => t('Revision scope'), 'revise_reason' => t('Revision reason')]);
                $doc = $writer()->revise($product, $doc, ['scope' => $d['revise_scope'], 'reason' => $d['revise_reason'], 'design_number' => $d['design_number'] ?? null,
                    'parts' => $r->input('parts', [])], InquiryDerivedWriter::artworks($r->file('parts', [])));
            }
            if ($r->input('action') === 'confirm') {
                $writer()->confirm($doc);

                return $open($doc)->with('v2_flash', tf('Inquiry :no confirmed — sent to ProDev → ProCost, the form is locked.', ['no' => $doc->inquiry_no]));
            }

            return $open($doc)->with('v2_flash', tf('Saved as :no (DRAFT).', ['no' => $doc->inquiry_no]));
        })->whereIn('type', ['recalc', 'revise'])->name('save');

        Route::post('/{id}/delete', function (int $id) use ($guard, $writer) {
            $guard();
            $doc = IqpInquiry::whereIn('inquiry_type', ['RECALC', 'REVISE'])->findOrFail($id);
            $writer()->delete($doc);

            return redirect(route('v2.screen', $doc->inquiry_type === 'RECALC' ? 'inquiry-recalc' : 'inquiry-revise'))->with('v2_flash', tf('Draft :no deleted.', ['no' => $doc->inquiry_no]));
        })->whereNumber('id')->name('delete');

        Route::post('/{id}/cancel', function (Request $r, int $id) use ($guard, $open) {
            $guard();
            $doc = IqpInquiry::whereIn('inquiry_type', ['RECALC', 'REVISE'])->findOrFail($id);
            $d = $r->validate(['cancel_reason' => ['required', 'string', 'max:255']], [], ['cancel_reason' => t('Cancel reason')]);
            (new InquiryWriter(Auth::user()->login))->cancel($doc, $d['cancel_reason']);

            return $open($doc)->with('v2_flash', tf('Inquiry :no cancelled.', ['no' => $doc->inquiry_no]));
        })->whereNumber('id')->name('cancel');
    });

    // Kamus penerjemah (LOCKED 2026-10-01): teks English dari JS (Devlib.h.t) yang belum ada di sys_dictionary dicatat.
    Route::post('/dictionary/register', function (Request $r) {
        $texts = array_slice(array_values(array_filter((array) $r->input('texts', []), 'is_string')), 0, 200);

        return response()->json(['added' => Dictionary::register($texts)]);
    })->name('dictionary.register');

    // Pilihan bahasa (x-dl::lang-switch, config devlib.lang_url): disimpan ke akun user, bukan hanya cookie perangkat,
    // supaya bahasanya ikut ke komputer lain. Daftar bahasa yang sah = sys_languages yang aktif (Devlib\Locale::languages).
    Route::post('/lang', function (Request $r) {
        $lang = (string) $r->input('lang');
        abort_unless(isset(Locale::languages()[$lang]), 422);
        Auth::user()->forceFill(['lang_code' => $lang])->save();

        return response()->noContent();
    })->name('lang');

    // Dialog Email x-dl::doc-share: cari alamat dari kontak customer, kontak supplier dan user (config devlib.mail_contacts_url).
    Route::get('mail-contacts', fn (Request $r) => response()->json(['rows' => MailContacts::search((string) $r->query('q', ''))]))
        ->name('mail-contacts');

    // Print & Email (objek DevLib x-dl::doc-share): simpan snapshot HTML -> token untuk QR / link online; kirim e-mail.
    Route::prefix('doc-share')->name('docshare.')->group(function () {
        Route::post('/', function (Request $r) {
            $d = $r->validate(['doc_type' => ['required', 'string', 'max:40'], 'doc_id' => ['nullable', 'integer'], 'doc_no' => ['nullable', 'string', 'max:60'],
                'title' => ['required', 'string', 'max:200'], 'html' => ['required', 'string', 'max:8000000']]);
            $share = (new DocShareService(Auth::user()->login))->snapshot($d);

            return response()->json(['token' => $share->token, 'url' => $share->url(), 'file' => DocShareService::fileName($share)]);
        })->name('store');

        Route::post('/{token}/email', function (Request $r, string $token) {
            $share = SysDocShare::where('token', $token)->firstOrFail();
            if (MailSettings::current()->require_verified && ! MailSettings::verified(Auth::user())) {
                return response()->json(['message' => t('Your e-mail is not verified yet. Open Profile > E-mail to verify it first:').' '.route('v2.account.email')], 422);
            }
            $list = fn ($k) => DocShareService::addresses($r->input($k));
            $r->merge(['to_list' => $list('to'), 'cc_list' => $list('cc'), 'bcc_list' => $list('bcc')]);
            $r->validate(['to_list' => ['required', 'array', 'min:1'], 'to_list.*' => ['email'], 'cc_list.*' => ['email'], 'bcc_list.*' => ['email'],
                'subject' => ['required', 'string', 'max:255'], 'message' => ['nullable', 'string', 'max:5000'],
                'image' => ['nullable', 'file', 'mimes:jpg,jpeg', 'max:15360'], 'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:15360']],
                ['to_list.required' => t('Enter at least one To address.'), 'to_list.*.email' => t('Invalid To address: :input'),
                    'cc_list.*.email' => t('Invalid CC address: :input'), 'bcc_list.*.email' => t('Invalid BCC address: :input')], ['subject' => t('Subject')]);
            $log = (new DocShareService(Auth::user()->login))->email($share, ['to' => $r->input('to_list'), 'cc' => $r->input('cc_list'), 'bcc' => $r->input('bcc_list'),
                'subject' => $r->input('subject'), 'message' => $r->input('message'), 'with_link' => $r->boolean('with_link')], $r->file('image'), $r->file('pdf'),
                ['name' => Auth::user()->name ?? Auth::user()->login, 'email' => Auth::user()->email, 'verified' => MailSettings::verified(Auth::user())]);

            return $log->status === 'SENT'
                ? response()->json(['message' => t('E-mail sent to').' '.$log->to_list.'.'])
                : response()->json(['message' => t('E-mail could not be sent:').' '.$log->error], 422);
        })->name('email');
    });

    // E-mail user: status + link verifikasi (signed, 24 jam) -> sec_user.email_verified. Setting E-mail sistem (admin).
    Route::prefix('account/email')->name('account.email')->group(function () {
        Route::get('/', fn () => view('v2.account-email'))->name('');
        Route::post('/send', function () {
            $u = Auth::user();
            if (! $u->email || ! filter_var($u->email, FILTER_VALIDATE_EMAIL)) {
                return back()->withErrors(['mail' => t('E-mail is not filled in / not valid.')]);
            }
            MailSettings::apply();
            $url = URL::temporarySignedRoute('v2.account.email.verify', now()->addDay(), ['id' => $u->id_user, 'hash' => sha1(strtolower($u->email))]);
            try {
                Mail::to($u->email)->send(new EmailVerifyMail($u->name ?: $u->login, $url));
            } catch (Throwable $e) {
                MailSettings::notifyFailure($u->login, $u->email, mb_substr($e->getMessage(), 0, 300));

                return back()->withErrors(['email' => tf('The verification link could not be sent: :error', ['error' => mb_substr($e->getMessage(), 0, 200)])]);
            }

            return back()->with('v2_flash', tf('Verification link sent to :email. Open that e-mail and click its button (valid for 24 hours).', ['email' => $u->email]));
        })->name('.send');
        Route::get('/verify/{id}/{hash}', function (int $id, string $hash) {
            $u = Auth::user();
            abort_unless($u->id_user === $id && hash_equals(sha1(strtolower((string) $u->email)), $hash), 403, t('This link is for another account / address.'));
            $u->forceFill(['email_verified' => true, 'email_verified_at' => now()])->save();

            return redirect(route('v2.account.email'))->with('v2_flash', tf('E-mail :email verified.', ['email' => $u->email]));
        })->middleware('signed')->name('.verify');
    });
    Route::prefix('settings/mail')->name('settings.mail')->group(function () {
        Route::get('/', function () {
            abort_unless(Auth::user()->is_admin, 403);

            return view('v2.mail-setting');
        })->name('');
        Route::post('/', function (Request $r) {
            abort_unless(Auth::user()->is_admin, 403);
            $d = $r->validate(['mailer' => ['required', 'in:env,smtp,log'], 'host' => ['nullable', 'required_if:mailer,smtp', 'string', 'max:150'],
                'port' => ['nullable', 'integer', 'between:1,65535'], 'encryption' => ['nullable', 'in:tls,ssl'], 'username' => ['nullable', 'string', 'max:150'],
                'password' => ['nullable', 'string', 'max:500'], 'from_address' => ['nullable', 'email', 'max:150'], 'from_name' => ['nullable', 'string', 'max:150']],
                [], ['host' => t('SMTP host'), 'from_address' => t('Sender address')]);
            $s = SysMailSetting::query()->first() ?? new SysMailSetting(['created_by' => Auth::user()->login]);
            $s->fill(collect($d)->except('password')->all() + ['from_user' => $r->boolean('from_user'), 'require_verified' => $r->boolean('require_verified'),
                'updated_by' => Auth::user()->login]);
            if ($r->filled('password')) {
                $s->password = $r->input('password');
            }
            $s->save();
            if ($r->input('action') !== 'test') {
                return back()->with('v2_flash', t('E-mail setting saved.'));
            }
            $u = Auth::user();
            if (! $u->email) {
                return back()->withErrors(['mail' => t('Fill in the e-mail of your account first (Security) to send a test.')]);
            }
            MailSettings::apply();
            try {
                Mail::raw(tf('Test e-mail from :app — the e-mail setting works.', ['app' => config('app.name')]), fn ($m) => $m->to($u->email)->subject(t('Test e-mail')));
            } catch (Throwable $e) {
                return back()->withErrors(['mail' => tf('Saved, but the test e-mail failed: :error', ['error' => mb_substr($e->getMessage(), 0, 300)])]);
            }

            return back()->with('v2_flash', tf('Saved; test e-mail sent to :email (mode: :mode).', ['email' => $u->email, 'mode' => $s->mailer]));
        })->name('.save');
    });

    // Pedoman alur (Markdown di docs/) -- dibaca user DAN developer sebelum mengubah alur terkait.
    Route::get('/pedoman/{slug}', function (string $slug) {
        // aturan = Pedoman Aturan User (dibuka dari Bantuan & dukungan di menu akun).
        $file = ['product-master' => 'flow_product_master.md', 'aturan' => 'globaluserrules.md'][$slug] ?? abort(404);
        $md = file_get_contents(base_path('docs/'.$file));

        return view('v2.guide', ['title' => trim(ltrim(strtok($md, "\n"), '# ')), 'html' => Str::markdown($md, ['html_input' => 'escape'])]);
    })->name('guide');

    Route::get('/flow/{module}', fn (string $module) => view('v2.flow', ['flow' => Flows::get($module) ?? abort(404)]))->name('flow');

    // Data Dictionary: khusus Administrator / grup DEVELOPER.
    Route::get('/dictionary', function () {
        abort_unless(AppMenu::user(Auth::user())['developer'], 403);

        return view('v2.dictionary', ['stats' => Schema::stats(), 'columns' => Schema::columns(), 'tables' => Schema::tables(),
            'duplicates' => Schema::duplicates(), 'copies' => Schema::copies(), 'changes' => Schema::changes()]);
    })->name('dictionary');

    // Engine Catalog: daftar engine + input / output / cara kerja. Khusus Administrator / grup DEVELOPER.
    Route::get('/engines', function () {
        abort_unless(AppMenu::user(Auth::user())['developer'], 403);

        return view('v2.engines', ['engines' => EngineRegistry::catalog()]);
    })->name('engines');

    // Data dummy: baseline & reset (user 2026-09-28). Developer saja; ddt:baseline menolak bila bukan database ddt*.
    Route::prefix('dummy')->name('dummy.')->group(function () {
        $guard = fn () => abort_unless(AppMenu::user(Auth::user())['developer'], 403);
        $run = function (string $action, string $ok) use ($guard) {
            $guard();
            set_time_limit(0);
            try {
                $code = Artisan::call('ddt:baseline', ['action' => $action, '--force' => true]);
            } catch (Throwable $e) {
                return back()->with('v2_error', $e->getMessage());
            }
            $last = trim(collect(explode("\n", Artisan::output()))->filter(fn ($l) => trim($l) !== '')->last() ?? '');

            // Ditolak (mis. struktur berubah sejak baseline): perintah mengembalikan kode gagal, bukan exception.
            return $code === 0 ? back()->with('v2_flash', $ok.' '.$last) : back()->with('v2_error', raw($last));
        };
        Route::get('/', function () use ($guard) {
            $guard();
            $manifest = DdtBaseline::manifest();

            // Drift = struktur ddt* sekarang vs baseline; selama ada, Reset ditolak (lihat DdtBaseline).
            return view('v2.dummy', ['manifest' => $manifest, 'drift' => array_map(fn ($r) => [
                'db' => $r['db'], 'table' => $r['table'], 'change' => t(DdtBaseline::DRIFT[$r['kind']][0]),
                'columns' => implode(', ', $r['columns']), 'effect' => t(DdtBaseline::DRIFT[$r['kind']][1]),
            ], $manifest ? DdtBaseline::drift() : []),
                // Set per stage (DdtStage): 'blocked' = set menyebut kolom / tabel yang sudah dihapus -> harus dibangun ulang.
                'stages' => array_map(fn ($m) => $m + ['blocked' => (bool) DdtStage::blocking($m)], DdtStage::sets())]);
        })->name('index');
        Route::post('/reset', fn () => $run('restore', t('Reset finished.')))->name('reset');
        Route::post('/save', fn () => $run('save', t('Baseline saved.')))->name('save');

        // Set data dummy per stage (user 2026-10-02): tiap set punya tombol Restore sendiri.
        $stage = function (array $args, string $ok) use ($guard) {
            $guard();
            set_time_limit(0);
            try {
                $code = Artisan::call('ddt:stage', $args + ['--force' => true]);
            } catch (Throwable $e) {
                return back()->with('v2_error', raw($e->getMessage()));
            }
            $last = trim(collect(explode("\n", Artisan::output()))->filter(fn ($l) => trim($l) !== '')->last() ?? '');

            return $code === 0 ? back()->with('v2_flash', raw($ok.' '.$last)) : back()->with('v2_error', raw($last));
        };
        Route::post('/stage/save', function (Request $r) use ($stage) {
            $d = $r->validate(['key' => ['required', 'regex:/^[a-z0-9][a-z0-9-]{0,39}$/'], 'label' => ['nullable', 'string', 'max:80'], 'note' => ['nullable', 'string', 'max:200']],
                ['key.regex' => t('Set name: lowercase letters, digits and minus only, e.g. my-test-1.')], ['key' => t('Set name')]);

            return $stage(['action' => 'save', 'key' => $d['key']] + array_filter(['--label' => $d['label'] ?? null, '--note' => $d['note'] ?? null]), '');
        })->name('stage.save');
        Route::post('/stage/{key}/{action}', fn (string $key, string $action) => $stage(['action' => $action, 'key' => $key], ''))
            ->where('key', '_?[a-z0-9][a-z0-9-]{0,39}')->whereIn('action', ['restore', 'delete'])->name('stage.do');
    });

    // Mockup HTML/gambar asli (docs/mockups), dibuka dari kartu info layar.
    Route::get('/berkas/{file}', function (string $file) {
        $root = realpath(config('devlib.mockup_path'));
        $real = realpath($root.DIRECTORY_SEPARATOR.$file);
        abort_unless($real && $root && is_file($real) && str_starts_with($real, $root.DIRECTORY_SEPARATOR), 404);

        return response()->file($real);
    })->where('file', '.*')->name('mockup.file');
});
