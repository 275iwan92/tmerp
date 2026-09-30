<?php

use App\Console\Commands\DdtBaseline;
use App\Mail\EmailVerifyMail;
use App\Models\Master\ProdProduct;
use App\Models\Master\ProdProductAcc;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PrRequisition;
use App\Models\Pur\PvReceipt;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sys\SysConstant;
use App\Models\Sys\SysDocShare;
use App\Models\Sys\SysMailSetting;
use App\Services\Docs\DocShareService;
use App\Services\Sales\InquiryAssembly;
use App\Services\Sales\InquiryDerivedWriter;
use App\Services\Sales\InquiryDuplicator;
use App\Services\Sales\InquiryWriter;
use App\Support\Mail\MailSettings;
use App\Support\V2\AppMenu;
use App\Support\V2\Data\Cpdel;
use App\Support\V2\Data\CustomerOrder;
use App\Support\V2\Data\DeliveryOrder;
use App\Support\V2\Data\Prodev;
use App\Support\V2\Data\PurchaseOrder;
use App\Support\V2\Data\PurchaseReceive;
use App\Support\V2\Data\PurchaseRequisition;
use App\Support\V2\Data\Quotation;
use App\Support\V2\Flows;
use App\Support\V2\InquiryPayload;
use App\Support\V2\Schema;
use App\Support\V2\Screens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

// Layar v2 (objek DevLib, Menu v2 tanpa top bar). Dimuat di dalam grup auth + force.password routes/web.php.
// mockup.test (APP_UI=v2) mendarat di sini; tmerp.test tetap v1 sampai go-live.
Route::prefix('v2')->name('v2.')->group(function () {
    // ?open=<kode menu> = tab yang dibuka saat mulai (mis. Direksi -> MGMT_EXEC), bila belum ada tab yang dipulihkan.
    Route::get('/', fn (Request $r) => view('v2.home', ['modules' => AppMenu::modules(Auth::user()), 'user' => AppMenu::user(Auth::user()),
        'open' => $r->query('open')]))->name('home');

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
        $screen = Screens::screen($slug) ?? abort(404);
        abort_unless(Auth::user()->canAccessModule($screen['module_code'] ?? 'SALES'), 403);
        $file = resource_path("views/v2/screens/{$slug}.blade.php");

        return view('v2.screen', ['screen' => $screen, 'built' => is_file($file), 'code' => is_file($file) ? file_get_contents($file) : '']);
    })->name('screen');

    // Duplicate inquiry (spec sama, customer lain) -> NEW DRAFT baru; logika = App\Services\Sales\InquiryDuplicator (sama dgn v1).
    Route::post('/inquiry/duplicate', function (Request $r) {
        abort_unless(Auth::user()->canAccessModule('SALES'), 403);
        $data = $r->validate([
            'source_id' => ['required', 'integer', 'exists:tm2sdi.iqp_inquiry,id_inquiry'],
            'customer_id' => ['required', 'integer', 'exists:tm2mst.cust_customer,id_customer'],
        ], [], ['customer_id' => 'Customer tujuan']);
        $source = IqpInquiry::findOrFail($data['source_id']);
        $new = InquiryDuplicator::duplicate($source, (int) $data['customer_id'], Auth::user()->login);

        return redirect()->route('v2.screen', 'inquiry')
            ->with('v2_flash', "Duplikat dibuat: {$new->inquiry_no} (Produk Baru, DRAFT) dari {$source->inquiry_no}.");
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
            $r->validate(InquiryPayload::rules(), [], ['customer_id' => 'Customer', 'type_code' => 'Tipe Produk', 'product_draft_name' => 'Nama Produk']);
            $img = ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:'.(int) (SysConstant::number('INQUIRY_ARTWORK_MAX_MB', 10) * 1024)];
            $r->validate(['parts.*.artwork' => $img, 'parts.*.artwork_inner' => $img]);
            $inq = $writer()->save(InquiryPayload::from($r, $old), $old, InquiryPayload::artworks($r));
            $confirm = $r->input('action') === 'confirm';
            if ($confirm) {
                $writer()->confirm($inq);
            }

            return response()->json(['id' => $inq->id_inquiry, 'url' => route('v2.screen', 'inquiry-new').'?id='.$inq->id_inquiry,
                'message' => $confirm ? "Inkuiri {$inq->inquiry_no} dikonfirmasi — status SUBMITTED, form terkunci." : "Tersimpan sebagai {$inq->inquiry_no} ({$inq->refresh()->sub_state})."]);
        })->name('save');

        Route::post('/{id}/end', function (Request $r, int $id) use ($writer, $doc) {
            $inq = $doc($id);
            $data = $r->validate(['state' => ['required', 'in:CANCELLED,LOST'], 'reason' => ['required', 'string', 'max:255']], [], ['reason' => 'Alasan']);
            $data['state'] === 'CANCELLED' ? $writer()->cancel($inq, $data['reason']) : $writer()->markLost($inq, $data['reason']);

            return response()->json(['message' => $data['state'] === 'CANCELLED' ? "Inkuiri {$inq->inquiry_no} dibatalkan." : "Inkuiri {$inq->inquiry_no} ditandai kalah (LOST)."]);
        })->name('end');

        // Artwork boleh diganti setelah Confirm (user 2026-09-28): langsung tersimpan tanpa tombol Simpan.
        Route::post('/{id}/artwork', function (Request $r, int $id) use ($writer, $doc) {
            $inq = $doc($id);
            $r->validate(['part_id' => ['required', 'integer'], 'artwork' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.(int) (SysConstant::number('INQUIRY_ARTWORK_MAX_MB', 10) * 1024)]]);
            $part = $inq->parts()->findOrFail((int) $r->input('part_id'));
            $inner = $r->boolean('inner');
            $writer()->storeArtwork($part, $r->file('artwork'), $inner);

            return response()->json(['message' => 'Artwork '.($inner ? 'sisi dalam ' : '')."{$part->part_name} tersimpan."]);
        })->name('artwork');

        Route::post('/{id}/prodev', function (Request $r, int $id) use ($writer, $doc) {
            $inq = $doc($id);
            $part = $inq->parts()->findOrFail((int) $r->input('part_id'));
            $prodPart = $writer()->sendToProDev($inq, $part);

            return response()->json(['url' => route('v2.screen', 'prodev').'?part='.$prodPart.'&inquiry='.$inq->id_inquiry]);
        })->name('prodev');

        // Quick-add customer dari Inquiry (status DRAFT, review Finance/Credit tetap wajib).
        Route::post('/customer', function (Request $r) use ($writer) {
            abort_unless(Auth::user()->canAccessModule('SALES'), 403);
            $d = $r->validate(['name' => ['required', 'string', 'max:200'], 'type' => ['required', 'in:local,import'], 'phone' => ['nullable', 'string', 'max:50'],
                'email' => ['nullable', 'email', 'max:150'], 'address' => ['nullable', 'string']], [], ['name' => 'Nama Customer']);
            $c = $writer()->quickCustomer($d + ['phone' => null, 'email' => null, 'address' => null]);

            return response()->json(['id' => $c->id_customer, 'label' => $c->customer_name, 'code' => $c->customer_code,
                'message' => "Customer baru \"{$c->customer_name}\" ({$c->customer_code}) dibuat — status DRAFT."]);
        })->name('customer');
    });

    // Master Product (docs/flow_product_master.md): nama diubah Sales, isian Proof Digital, catat ACC customer.
    Route::prefix('product/{id}')->name('product.')->whereNumber('id')->group(function () {
        $product = function (int $id) {
            abort_unless(Auth::user()->canAccessModule('SALES'), 403);

            return ProdProduct::findOrFail($id);
        };
        Route::post('/name', function (Request $r, int $id) use ($product) {
            $d = $r->validate(['product_name' => ['required', 'string', 'max:200']], [], ['product_name' => 'Nama product']);
            $product($id)->update($d + ['updated_by' => Auth::user()->login]);

            return back()->with('v2_flash', 'Nama product tersimpan.');
        })->name('name');
        Route::post('/proof', function (Request $r, int $id) use ($product) {
            $d = $r->validate(['drawing_by' => ['nullable', 'string', 'max:100'], 'design_date' => ['nullable', 'date']], [], ['drawing_by' => 'Drawing by']);
            $product($id)->update($d + ['updated_by' => Auth::user()->login]);

            return back()->with('v2_flash', 'Isian Proof Digital tersimpan.');
        })->name('proof');
        Route::post('/acc', function (Request $r, int $id) use ($product) {
            $p = $product($id);
            $d = $r->validate(['acc_type' => ['required', 'in:'.implode(',', array_keys(ProdProductAcc::TYPES))], 'acc_at' => ['required', 'date'],
                'approved_by' => ['required', 'string', 'max:100'], 'channel' => ['required', 'in:'.implode(',', array_keys(ProdProductAcc::CHANNELS))],
                'note' => ['nullable', 'string', 'max:500'], 'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240']],
                [], ['acc_at' => 'Tanggal & jam ACC', 'approved_by' => 'Di-ACC oleh', 'channel' => 'Saluran', 'evidence' => 'Bukti']);
            $file = $r->file('evidence');
            ProdProductAcc::create(collect($d)->except('evidence')->all() + ['product_id' => $p->id_product,
                'evidence_path' => $file?->store("product/{$p->id_product}/acc", 'public'), 'evidence_filename' => $file?->getClientOriginalName(),
                'created_by' => Auth::user()->login, 'updated_by' => Auth::user()->login]);

            return back()->with('v2_flash', ProdProductAcc::TYPES[$d['acc_type']].' tercatat.');
        })->name('acc');
    });

    // Rakitan (ASSEMBLY) Inquiry Baru: kelompokkan part jadi rakitan + jenis sambungan (App\Services\Sales\InquiryAssembly).
    Route::prefix('inquiry/{id}/assembly')->name('inquiry.assembly.')->whereNumber('id')->middleware('module:SALES')->group(function () {
        $back = fn (int $id) => redirect(route('v2.screen', 'inquiry-new').'?id='.$id.'#rakitan');
        Route::post('/', function (Request $r, int $id) use ($back) {
            $d = $r->validate(['assembly_name' => ['required', 'string', 'max:100'], 'join_type_id' => ['required', 'integer'],
                'children' => ['required', 'array', 'min:2'], 'qty_in_parent' => ['nullable', 'numeric', 'min:0.0001']],
                ['children.min' => 'Pilih minimal 2 part.'], ['assembly_name' => 'Nama rakitan', 'join_type_id' => 'Jenis sambungan', 'children' => 'Part']);
            try {
                (new InquiryAssembly(Auth::user()->login))->create(IqpInquiry::findOrFail($id), $d['assembly_name'], (int) $d['join_type_id'], $d['children'], (float) ($d['qty_in_parent'] ?? 1) ?: 1);
            } catch (InvalidArgumentException $e) {
                return $back($id)->withErrors(['children' => $e->getMessage()]);
            }

            return $back($id)->with('v2_flash', 'Rakitan "'.$d['assembly_name'].'" dibuat.');
        })->name('store');
        Route::post('/{part}/delete', function (int $id, int $part) use ($back) {
            (new InquiryAssembly(Auth::user()->login))->remove(IqpInquiry::findOrFail($id), $part);

            return $back($id)->with('v2_flash', 'Rakitan dibongkar; part-nya kembali berdiri sendiri.');
        })->whereNumber('part')->name('delete');
    });

    // Quotation v2 (App\Support\V2\Data\Quotation = aturan komponen v1 pages::quotation.entry). Customer tidak diketik: dari inquiry.
    Route::prefix('quotation')->name('quotation.')->middleware('module:SALES')->group(function () {
        $open = fn (int $id) => redirect(route('v2.screen', 'quotation').'?id='.$id);
        Route::post('/create', function (Request $r) use ($open) {
            $d = $r->validate(['inquiry_ids' => ['required', 'array', 'min:1'], 'inquiry_ids.*' => ['integer']], [], ['inquiry_ids' => 'Inquiry']);
            try {
                $q = Quotation::create($d['inquiry_ids']);
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['inquiry_ids' => $e->getMessage()]);
            }

            return $open($q->id_quotation)->with('v2_flash', "Quotation {$q->quotation_no} dibuat (DRAFT).");
        })->name('create');

        Route::prefix('{id}')->whereNumber('id')->group(function () use ($open) {
            Route::post('/save', function (Request $r, int $id) use ($open) {
                $q = QuoQuotation::findOrFail($id);
                // Tombol Delete panel Form (x-dl::form) mengirim action=delete ke rute simpan yang sama.
                if ($r->input('action') === 'delete') {
                    Quotation::delete($q);

                    return redirect(route('v2.screen', 'quotation'))->with('v2_flash', "Draft {$q->quotation_no} dihapus.");
                }
                $r->validate(['issued_date' => ['required', 'date'], 'valid_until' => ['nullable', 'date'], 'deadline_date' => ['nullable', 'date'],
                    'items' => ['array'], 'items.*.description' => ['nullable', 'string', 'max:500'], 'items.*.qty_unit' => ['nullable', 'string', 'max:20']],
                    [], ['issued_date' => 'Tanggal dokumen']);
                Quotation::save($q, $r->only(Quotation::HEADER), $r->has('cc_present') ? (array) $r->input('cc', []) : null,
                    (array) $r->input('items', []), array_keys((array) $r->input('remove', [])));
                $q->refresh();
                if ($r->input('action') === 'add_line') {
                    Quotation::addLine($q);

                    return $open($id)->with('v2_flash', 'Baris bebas ditambahkan — isi uraian, qty, dan harga lalu Simpan.');
                }
                if ($r->input('action') === 'confirm') {
                    Quotation::confirm($q);

                    return $open($id)->with('v2_flash', "Quotation {$q->quotation_no} di-Confirm (terbit). Kirim ke customer lewat Print / Email.");
                }

                return $open($id)->with('v2_flash', 'Quotation tersimpan.');
            })->name('save');
            Route::post('/add-inquiry', function (Request $r, int $id) use ($open) {
                $d = $r->validate(['inquiry_ids' => ['required', 'array', 'min:1']], [], ['inquiry_ids' => 'Inquiry']);
                Quotation::addInquiries(QuoQuotation::findOrFail($id), $d['inquiry_ids']);

                return $open($id)->with('v2_flash', 'Inquiry ditambahkan ke quotation.');
            })->name('add-inquiry');
            Route::post('/delete', function (int $id) {
                $q = QuoQuotation::findOrFail($id);
                Quotation::delete($q);

                return redirect(route('v2.screen', 'quotation'))->with('v2_flash', "Draft {$q->quotation_no} dihapus.");
            })->name('delete');
            Route::post('/cancel', function (Request $r, int $id) use ($open) {
                $d = $r->validate(['cancel_reason' => ['required', 'string', 'max:255']], [], ['cancel_reason' => 'Alasan batal']);
                Quotation::cancel(QuoQuotation::findOrFail($id), $d['cancel_reason']);

                return $open($id)->with('v2_flash', 'Quotation dibatalkan; inquiry-nya bisa ditawarkan lagi.');
            })->name('cancel');
            Route::post('/lost', function (Request $r, int $id) use ($open) {
                $d = $r->validate(['lost_reason' => ['required', 'string', 'max:255']], [], ['lost_reason' => 'Alasan kalah']);
                Quotation::lost(QuoQuotation::with('items.inquiry')->findOrFail($id), $d['lost_reason']);

                return $open($id)->with('v2_flash', 'Quotation ditutup LOST.');
            })->name('lost');
            Route::post('/win', function (int $id) {
                $order = Quotation::win(QuoQuotation::with('items.inquiry')->findOrFail($id));

                return $order ? redirect(route('v2.screen', 'customer-order').'?id='.$order)->with('v2_flash', 'WIN — Customer Order dibuat dari quotation ini.')
                    : back()->withErrors(['win' => 'Customer Order tidak bisa dibuat dari quotation ini.']);
            })->name('win');
            Route::post('/activity', function (Request $r, int $id) use ($open) {
                Quotation::addActivity(QuoQuotation::findOrFail($id), (array) $r->input('act', []));

                return $open($id)->with('v2_flash', 'Follow-up tercatat.');
            })->name('activity');
            Route::post('/activity/{aid}/delete', function (int $id, int $aid) use ($open) {
                Quotation::deleteActivity(QuoQuotation::findOrFail($id), $aid);

                return $open($id)->with('v2_flash', 'Follow-up dihapus.');
            })->whereNumber('aid')->name('activity.delete');
        });
    });

    // Purchase Requisition v2 (App\Support\V2\Data\PurchaseRequisition = aturan komponen v1 pages::pur.pr.entry). Satu rute simpan:
    // action = save | confirm (Submit ke flow approval) | approve | reject | hold | delete | add_material | add_free.
    Route::prefix('pr')->name('pr.')->middleware('module:PROC')->group(function () {
        $open = fn (int $id) => redirect(route('v2.screen', 'purchase-requisition').'?id='.$id);
        Route::post('/create', function (Request $r) use ($open) {
            $d = $r->validate(['department_id' => ['required', 'integer']], [], ['department_id' => 'Departemen']);
            $pr = PurchaseRequisition::create((int) $d['department_id']);

            return $open($pr->pr_id)->with('v2_flash', 'PR draft dibuat — tambahkan item lalu Confirm (ajukan).');
        })->name('create');
        Route::post('/{id}/save', function (Request $r, int $id) use ($open) {
            $pr = PrRequisition::findOrFail($id);
            $action = (string) $r->input('action', 'save');
            if (in_array($action, ['approve', 'reject', 'hold'], true)) {
                $err = PurchaseRequisition::decide($pr, $action, (string) $r->input('_reason', ''));

                return $err ? $open($id)->withErrors(['approval' => $err]) : $open($id)->with('v2_flash', 'Keputusan approval tersimpan ('.ucfirst($action).').');
            }
            if ($action === 'delete') {
                PurchaseRequisition::delete($pr);

                return redirect(route('v2.screen', 'purchase-requisition'))->with('v2_flash', 'Draft PR dihapus.');
            }
            $r->validate(['i_doc_date' => ['nullable', 'date'], 'i_need_date' => ['nullable', 'date'], 'lines.*.i_need_date' => ['nullable', 'date']]);
            PurchaseRequisition::save($pr, $r->all());
            $msg = 'PR tersimpan.';
            if ($action === 'add_material') {
                abort_unless($r->filled('material_id'), 422, 'Pilih item dari master.');
                PurchaseRequisition::addMaterial($pr->refresh(), (int) $r->input('material_id'));
                $msg = 'Item master ditambahkan — isi qty & harga estimasi.';
            } elseif ($action === 'add_free') {
                abort_unless(trim((string) $r->input('free_description')) !== '', 422, 'Isi uraian item bebas.');
                PurchaseRequisition::addFree($pr->refresh(), (string) $r->input('free_description'), (string) $r->input('free_uom', ''));
                $msg = 'Item bebas ditambahkan.';
            } elseif ($action === 'confirm') {
                $pr->refresh()->load('lines');
                if ($pr->lines->isEmpty() || ! $pr->i_department_id) {
                    return $open($id)->withErrors(['confirm' => 'Belum bisa Confirm: PR wajib punya departemen dan minimal 1 item.']);
                }
                $blocked = PurchaseRequisition::submit($pr);
                $msg = $blocked ?: 'PR di-Confirm (diajukan) — menunggu approval.';
            }

            return $open($id)->with('v2_flash', $msg);
        })->whereNumber('id')->name('save');
    });

    // Purchase Order v2 (App\Support\V2\Data\PurchaseOrder = aturan komponen v1 pages::pur.po.entry). Satu rute simpan:
    // action = save | confirm (Submit) | approve | reject | hold | send (Kirim ke Supplier) | delete | add_alloc.
    Route::prefix('po')->name('po.')->middleware('module:PROC')->group(function () {
        $open = fn (int $id) => redirect(route('v2.screen', 'purchase-order').'?id='.$id);
        Route::post('/create', function (Request $r) use ($open) {
            $d = $r->validate(['supplier_id' => ['required', 'integer']], [], ['supplier_id' => 'Supplier']);
            $po = PurchaseOrder::create((int) $d['supplier_id']);

            return $open($po->po_id)->with('v2_flash', 'Draft PO siap — tarik baris dari PR yang sudah approved.');
        })->name('create');
        Route::post('/{id}/save', function (Request $r, int $id) use ($open) {
            $po = PoOrder::findOrFail($id);
            $action = (string) $r->input('action', 'save');
            if (in_array($action, ['approve', 'reject', 'hold'], true)) {
                $err = PurchaseOrder::decide($po, $action, (string) $r->input('_reason', ''));

                return $err ? $open($id)->withErrors(['approval' => $err]) : $open($id)->with('v2_flash', 'Keputusan approval tersimpan ('.ucfirst($action).').');
            }
            if ($action === 'send') {
                PurchaseOrder::send($po);

                return $open($id)->with('v2_flash', 'PO ditandai sudah dikirim ke supplier (SENT) — siap diterima di Penerimaan Barang.');
            }
            if ($action === 'delete') {
                PurchaseOrder::delete($po);

                return redirect(route('v2.screen', 'purchase-order'))->with('v2_flash', 'Draft PO dihapus; alokasi PR dilepas lagi.');
            }
            $r->validate(['i_doc_date' => ['nullable', 'date'], 'i_delivery_date' => ['nullable', 'date']]);
            PurchaseOrder::save($po, $r->all());
            $msg = 'PO tersimpan.';
            if ($action === 'add_alloc') {
                abort_unless($r->filled('alloc_id'), 422, 'Pilih baris PR.');
                PurchaseOrder::addAllocation($po->refresh(), (int) $r->input('alloc_id'));
                $msg = 'Baris PR ditarik ke PO — isi harga & diskon.';
            } elseif ($action === 'confirm') {
                $po->refresh()->load('lines');
                if ($po->lines->isEmpty()) {
                    return $open($id)->withErrors(['confirm' => 'Belum bisa Confirm: PO wajib punya minimal 1 baris.']);
                }
                $blocked = PurchaseOrder::submit($po);
                $msg = $blocked ?: 'PO di-Confirm (diajukan) — menunggu approval.';
            }

            return $open($id)->with('v2_flash', $msg);
        })->whereNumber('id')->name('save');
    });

    // Penerimaan Barang / SPB v2 (App\Support\V2\Data\PurchaseReceive = aturan komponen v1 pages::pur.pv.entry).
    // action = save | confirm (Post: stok masuk + jurnal) | delete | add_lines.
    Route::prefix('pv')->name('pv.')->middleware('module:PROC')->group(function () {
        $open = fn (int $id) => redirect(route('v2.screen', 'purchase-receive').'?id='.$id);
        Route::post('/create', function (Request $r) use ($open) {
            $d = $r->validate(['supplier_id' => ['required', 'integer'], 'warehouse' => ['required', 'string'], 'po_line_ids' => ['required', 'array', 'min:1']],
                [], ['supplier_id' => 'Supplier', 'warehouse' => 'Gudang', 'po_line_ids' => 'Baris PO']);
            try {
                $pv = PurchaseReceive::create((int) $d['supplier_id'], $d['warehouse'], $d['po_line_ids']);
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['po_line_ids' => $e->getMessage()]);
            }

            return $open($pv->pv_id)->with('v2_flash', "Penerimaan {$pv->doc_no} dibuat — isi qty diterima, QC qty & kualitas, lalu Confirm (Post).");
        })->name('create');
        Route::post('/{id}/save', function (Request $r, int $id) use ($open) {
            $pv = PvReceipt::findOrFail($id);
            $action = (string) $r->input('action', 'save');
            if ($action === 'delete') {
                PurchaseReceive::delete($pv);

                return redirect(route('v2.screen', 'purchase-receive'))->with('v2_flash', "Draft {$pv->doc_no} dihapus.");
            }
            $r->validate(['i_doc_date' => ['nullable', 'date']]);
            PurchaseReceive::save($pv, $r->all());
            $msg = 'Penerimaan tersimpan.';
            if ($action === 'add_lines') {
                abort_unless($r->filled('po_line_ids'), 422, 'Pilih baris PO.');
                PurchaseReceive::addLines($pv->refresh(), (array) $r->input('po_line_ids'));
                $msg = 'Baris PO ditambahkan.';
            } elseif ($action === 'confirm') {
                [$ok, $m] = PurchaseReceive::post($pv);
                if (! $ok) {
                    return $open($id)->withErrors(['confirm' => 'Belum bisa Confirm (Post): '.$m]);
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
            $d = $r->validate(['customer_id' => ['required', 'integer']], [], ['customer_id' => 'Customer']);
            $do = DeliveryOrder::create((int) $d['customer_id']);

            return $open($do->id_delivery)->with('v2_flash', 'Draft DO dibuat — tarik baris dari jadwal kirim Customer Order.');
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

                return $ok ? $open($id, $slug)->with('v2_flash', ['confirm' => 'DO di-Confirm — Surat Jalan siap dicetak.', 'ship' => 'Barang sudah dikirim (SHIPPED).', 'cancel' => 'DO dibatalkan.'][$action])
                    : $open($id, $slug)->withErrors(['action' => $m]);
            }
            $r->validate(['do_date' => ['nullable', 'date'], 'scheduled_ship_date' => ['nullable', 'date']]);
            $err = DeliveryOrder::save($do, $r->all());
            if ($action === 'pull') {
                abort_unless($r->filled('co_delivery_ids'), 422, 'Pilih jadwal kirim CO.');
                $err = DeliveryOrder::pull($do->refresh(), (array) $r->input('co_delivery_ids')) ?: $err;
            }

            return $err ? $open($id, $slug)->withErrors(['save' => $err]) : $open($id, $slug)->with('v2_flash', $action === 'pull' ? 'Baris ditarik dari jadwal kirim CO.' : 'DO tersimpan.');
        })->whereNumber('id')->name('save');
    });

    // Customer Order v2 (App\Support\V2\Data\CustomerOrder = aturan komponen v1 pages::co.entry). Tombol "+" menyimpan isian dulu.
    Route::prefix('co')->name('co.')->middleware('module:SALES')->group(function () {
        $open = fn (int $id) => redirect(route('v2.screen', 'customer-order').'?id='.$id);
        Route::post('/create', function (Request $r) use ($open) {
            $d = $r->validate(['mode' => ['required', 'in:INTEGRATED,INSTANT'], 'quotation_id' => ['required_if:mode,INTEGRATED', 'nullable', 'integer'],
                'customer_id' => ['required_if:mode,INSTANT', 'nullable', 'integer']], [], ['quotation_id' => 'Quotation', 'customer_id' => 'Customer']);
            $o = $d['mode'] === 'INTEGRATED' ? CustomerOrder::createIntegrated((int) $d['quotation_id']) : CustomerOrder::createInstant((int) $d['customer_id']);

            return $o ? $open($o->id_order)->with('v2_flash', "Customer Order {$o->co_no} ({$o->entry_mode}) siap diisi.")
                : back()->withErrors(['quotation_id' => 'Quotation ini tidak bisa menjadi Customer Order (belum terbit / LOST).']);
        })->name('create');
        Route::post('/{id}/save', function (Request $r, int $id) use ($open) {
            $o = CoOrder::findOrFail($id);
            // Tombol Delete panel Form (x-dl::form) mengirim action=delete ke rute simpan yang sama.
            if ($r->input('action') === 'delete') {
                CustomerOrder::delete($o);

                return redirect(route('v2.screen', 'customer-order'))->with('v2_flash', "Draft {$o->co_no} dihapus.");
            }
            if ($r->input('action') === 'cancel') {
                $err = CustomerOrder::cancel($o, (string) $r->input('_reason', ''));

                return $err ? $open($id)->withErrors(['cancel' => $err]) : $open($id)->with('v2_flash', "Customer Order {$o->co_no} CANCELLED — jadwal kirimnya ikut dibatalkan.");
            }
            $r->validate(['co_date' => ['nullable', 'date'], 'items.*.due_date' => ['nullable', 'date'], 'deliveries.*.ship_date' => ['nullable', 'date'],
                'product_id' => ['nullable', 'integer']], [], ['co_date' => 'CO Date']);
            CustomerOrder::save($o, $r->all());
            $action = (string) $r->input('action', 'save');
            $msg = 'Customer Order tersimpan.';
            if ($action === 'add_product') {
                abort_unless($r->filled('product_id'), 422, 'Pilih Master Product.');
                CustomerOrder::addProduct($o, (int) $r->input('product_id'));
                $msg = 'Baris product ditambahkan — isi qty & due date.';
            } elseif ($action === 'add_service') {
                CustomerOrder::addService($o);
                $msg = 'Baris jasa ditambahkan.';
            } elseif ($action === 'add_cost') {
                CustomerOrder::addCost($o);
                $msg = 'Biaya luar ditambahkan.';
            } elseif (str_starts_with($action, 'add_delivery_')) {
                CustomerOrder::addDelivery($o, (int) substr($action, 13));
                $msg = 'Jadwal kirim ditambahkan.';
            } elseif ($action === 'confirm') {
                $o = CoOrder::with('items.deliveries')->findOrFail($id);
                if (! CustomerOrder::confirm($o)) {
                    return $open($id)->withErrors(['confirm' => 'Belum bisa Confirm: tiap baris PRODUCT wajib punya Due Date'.($o->isInstant() ? ' dan Master Product' : '').'.']);
                }
                $msg = "Customer Order {$o->co_no} CONFIRMED — jadwal kirim default dibuat di Customer Plan Delivery.";
            }

            return $open($id)->with('v2_flash', $msg);
        })->whereNumber('id')->name('save');
        Route::post('/{id}/delete', function (int $id) {
            $o = CoOrder::findOrFail($id);
            CustomerOrder::delete($o);

            return redirect(route('v2.screen', 'customer-order'))->with('v2_flash', "Draft {$o->co_no} dihapus.");
        })->whereNumber('id')->name('delete');
    });

    // Customer Plan Delivery v2 (App\Support\V2\Data\Cpdel = confirmSplit/confirmMerge v1 pages::co.delivery-plan).
    Route::prefix('cpdel')->name('cpdel.')->middleware('module:SALES')->group(function () {
        $open = fn (int $id) => redirect(route('v2.screen', 'cpdel').'?id='.$id);
        Route::post('/{id}/split', function (Request $r, int $id) use ($open) {
            $d = $r->validate(['ship_date' => ['required', 'date'], 'split_qty' => ['nullable', 'numeric']], [], ['ship_date' => 'Tanggal kirim', 'split_qty' => 'Qty dipecah']);
            $error = Cpdel::split($id, $d['split_qty'] ?? null, $d['ship_date']);

            return $error ? $open($id)->withErrors(['split' => $error]) : $open($id)->with('v2_flash', 'Jadwal kirim diperbarui.');
        })->whereNumber('id')->name('split');
        Route::post('/merge', function (Request $r) use ($open) {
            $ids = array_map('intval', (array) $r->input('ids', []));
            $error = Cpdel::merge($ids);

            return $error ? back()->withErrors(['merge' => $error])
                : $open((int) CoOrderDelivery::whereIn('id_delivery', $ids)->value('id_delivery'))->with('v2_flash', 'Baris berhasil digabung ke tanggal paling awal.');
        })->name('merge');
    });

    // ProDev workbench: panggilan server = method komponen v1 pages::production.prodev (App\Support\V2\Data\Prodev).
    Route::prefix('prodev/{part}')->name('prodev.')->whereNumber('part')->middleware('module:PROD')->group(function () {
        Route::post('/call', function (Request $r, int $part) {
            $d = $r->validate(['method' => ['required', 'in:'.implode(',', Prodev::CALLS)], 'args' => ['array']]);

            return response()->json(Prodev::call($part, $d['method'], $d['args'] ?? []));
        })->name('call');
        Route::post('/artwork', function (Request $r, int $part) {
            $r->validate(['file' => ['required', 'file']]);

            return response()->json(Prodev::uploadArtwork($part, $r->file('file')));
        })->name('artwork');
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
                $d = $r->validate(['qty' => ['required', 'numeric', 'min:1'], 'deadline_quotation' => ['nullable', 'date']], [], ['qty' => 'Qty order']);
                $doc = $writer()->recalc($product, $doc, $d + ['deadline_quotation' => null]);
            } else {
                $d = $r->validate(['revise_scope' => ['required', 'array', 'min:1'], 'revise_scope.*' => ['in:'.implode(',', array_keys(InquiryDerivedWriter::SCOPES))],
                    'revise_reason' => ['required', 'string', 'max:255'], 'design_number' => ['nullable', 'string', 'max:30'],
                    'parts.*.artwork' => ['nullable', 'image', 'max:10240'], 'parts.*.artwork_inner' => ['nullable', 'image', 'max:10240']],
                    [], ['revise_scope' => 'Scope revisi', 'revise_reason' => 'Alasan revisi']);
                $doc = $writer()->revise($product, $doc, ['scope' => $d['revise_scope'], 'reason' => $d['revise_reason'], 'design_number' => $d['design_number'] ?? null,
                    'parts' => $r->input('parts', [])], InquiryDerivedWriter::artworks($r->file('parts', [])));
            }
            if ($r->input('action') === 'confirm') {
                $writer()->confirm($doc);

                return $open($doc)->with('v2_flash', "Inkuiri {$doc->inquiry_no} di-Confirm — dikirim ke ProDev → ProCost, form terkunci.");
            }

            return $open($doc)->with('v2_flash', "Tersimpan sebagai {$doc->inquiry_no} (DRAFT).");
        })->whereIn('type', ['recalc', 'revise'])->name('save');

        Route::post('/{id}/delete', function (int $id) use ($guard, $writer) {
            $guard();
            $doc = IqpInquiry::whereIn('inquiry_type', ['RECALC', 'REVISE'])->findOrFail($id);
            $writer()->delete($doc);

            return redirect(route('v2.screen', $doc->inquiry_type === 'RECALC' ? 'inquiry-recalc' : 'inquiry-revise'))->with('v2_flash', "Draft {$doc->inquiry_no} dihapus.");
        })->whereNumber('id')->name('delete');

        // Sesudah Confirm: kirim part ke ProDev (REVISE -> product turunan dibuat di sini, IqpInquiry::ensureDraftProduct).
        Route::post('/{id}/prodev', function (Request $r, int $id) use ($guard) {
            $guard();
            $doc = IqpInquiry::whereIn('inquiry_type', ['RECALC', 'REVISE'])->findOrFail($id);
            $part = $doc->parts()->findOrFail((int) $r->input('part_id'));

            return redirect(route('v2.screen', 'prodev').'?part='.(new InquiryWriter(Auth::user()->login))->sendToProDev($doc, $part));
        })->whereNumber('id')->name('prodev');

        Route::post('/{id}/cancel', function (Request $r, int $id) use ($guard, $open) {
            $guard();
            $doc = IqpInquiry::whereIn('inquiry_type', ['RECALC', 'REVISE'])->findOrFail($id);
            $d = $r->validate(['cancel_reason' => ['required', 'string', 'max:255']], [], ['cancel_reason' => 'Alasan batal']);
            (new InquiryWriter(Auth::user()->login))->cancel($doc, $d['cancel_reason']);

            return $open($doc)->with('v2_flash', "Inkuiri {$doc->inquiry_no} dibatalkan.");
        })->whereNumber('id')->name('cancel');
    });

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
                return response()->json(['message' => 'E-mail Anda belum terverifikasi. Buka Profil > E-mail untuk verifikasi dulu: '.route('v2.account.email')], 422);
            }
            $list = fn ($k) => DocShareService::addresses($r->input($k));
            $r->merge(['to_list' => $list('to'), 'cc_list' => $list('cc'), 'bcc_list' => $list('bcc')]);
            $r->validate(['to_list' => ['required', 'array', 'min:1'], 'to_list.*' => ['email'], 'cc_list.*' => ['email'], 'bcc_list.*' => ['email'],
                'subject' => ['required', 'string', 'max:255'], 'message' => ['nullable', 'string', 'max:5000'],
                'image' => ['nullable', 'file', 'mimes:jpg,jpeg', 'max:15360'], 'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:15360']],
                ['to_list.required' => 'Isi minimal satu alamat To.', 'to_list.*.email' => 'Alamat To tidak valid: :input',
                    'cc_list.*.email' => 'Alamat CC tidak valid: :input', 'bcc_list.*.email' => 'Alamat BCC tidak valid: :input'], ['subject' => 'Subjek']);
            $log = (new DocShareService(Auth::user()->login))->email($share, ['to' => $r->input('to_list'), 'cc' => $r->input('cc_list'), 'bcc' => $r->input('bcc_list'),
                'subject' => $r->input('subject'), 'message' => $r->input('message'), 'with_link' => $r->boolean('with_link')], $r->file('image'), $r->file('pdf'),
                ['name' => Auth::user()->name ?? Auth::user()->login, 'email' => Auth::user()->email, 'verified' => MailSettings::verified(Auth::user())]);

            return $log->status === 'SENT'
                ? response()->json(['message' => 'E-mail terkirim ke '.$log->to_list.'.'])
                : response()->json(['message' => 'E-mail gagal dikirim: '.$log->error], 422);
        })->name('email');
    });

    // E-mail user: status + link verifikasi (signed, 24 jam) -> sec_user.email_verified. Setting E-mail sistem (admin).
    Route::prefix('account/email')->name('account.email')->group(function () {
        Route::get('/', fn () => view('v2.account-email'))->name('');
        Route::post('/send', function () {
            $u = Auth::user();
            abort_unless($u->email && filter_var($u->email, FILTER_VALIDATE_EMAIL), 422, 'E-mail belum diisi / tidak valid.');
            MailSettings::apply();
            $url = URL::temporarySignedRoute('v2.account.email.verify', now()->addDay(), ['id' => $u->id_user, 'hash' => sha1(strtolower($u->email))]);
            try {
                Mail::to($u->email)->send(new EmailVerifyMail($u->name ?: $u->login, $url));
            } catch (Throwable $e) {
                MailSettings::notifyFailure($u->login, $u->email, mb_substr($e->getMessage(), 0, 300));

                return back()->withErrors(['email' => 'Link verifikasi gagal dikirim: '.mb_substr($e->getMessage(), 0, 200)]);
            }

            return back()->with('v2_flash', 'Link verifikasi dikirim ke '.$u->email.'. Buka e-mail itu dan klik tombolnya (berlaku 24 jam).');
        })->name('.send');
        Route::get('/verify/{id}/{hash}', function (int $id, string $hash) {
            $u = Auth::user();
            abort_unless($u->id_user === $id && hash_equals(sha1(strtolower((string) $u->email)), $hash), 403, 'Link ini untuk akun / alamat lain.');
            $u->forceFill(['email_verified' => true, 'email_verified_at' => now()])->save();

            return redirect(route('v2.account.email'))->with('v2_flash', 'E-mail '.$u->email.' terverifikasi.');
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
                [], ['host' => 'SMTP host', 'from_address' => 'Alamat pengirim']);
            $s = SysMailSetting::query()->first() ?? new SysMailSetting(['created_by' => Auth::user()->login]);
            $s->fill(collect($d)->except('password')->all() + ['from_user' => $r->boolean('from_user'), 'require_verified' => $r->boolean('require_verified'),
                'updated_by' => Auth::user()->login]);
            if ($r->filled('password')) {
                $s->password = $r->input('password');
            }
            $s->save();
            if ($r->input('action') !== 'test') {
                return back()->with('v2_flash', 'Setting e-mail tersimpan.');
            }
            $u = Auth::user();
            abort_unless($u->email, 422, 'Isi e-mail akun Anda dulu (Security) untuk uji kirim.');
            MailSettings::apply();
            try {
                Mail::raw('E-mail uji dari '.config('app.name').' — setting e-mail berfungsi.', fn ($m) => $m->to($u->email)->subject('E-mail uji'));
            } catch (Throwable $e) {
                return back()->withErrors(['mail' => 'Tersimpan, tapi e-mail uji gagal: '.mb_substr($e->getMessage(), 0, 300)]);
            }

            return back()->with('v2_flash', 'Tersimpan; e-mail uji dikirim ke '.$u->email.' (mode: '.$s->mailer.').');
        })->name('.save');
    });

    // Pedoman alur (Markdown di docs/) -- dibaca user DAN developer sebelum mengubah alur terkait.
    Route::get('/pedoman/{slug}', function (string $slug) {
        $file = ['product-master' => 'flow_product_master.md'][$slug] ?? abort(404);
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

    // Data dummy: baseline & reset (user 2026-09-28). Developer saja; ddt:baseline menolak bila bukan database ddt*.
    Route::prefix('dummy')->name('dummy.')->group(function () {
        $guard = fn () => abort_unless(AppMenu::user(Auth::user())['developer'], 403);
        $run = function (string $action, string $ok) use ($guard) {
            $guard();
            set_time_limit(0);
            try {
                Artisan::call('ddt:baseline', ['action' => $action, '--force' => true]);
            } catch (Throwable $e) {
                return back()->with('v2_error', $e->getMessage());
            }

            return back()->with('v2_flash', $ok.' '.trim(collect(explode("\n", Artisan::output()))->filter()->last()));
        };
        Route::get('/', function () use ($guard) {
            $guard();

            return view('v2.dummy', ['manifest' => DdtBaseline::manifest()]);
        })->name('index');
        Route::post('/reset', fn () => $run('restore', 'Reset selesai.'))->name('reset');
        Route::post('/save', fn () => $run('save', 'Baseline disimpan.'))->name('save');
    });

    // Mockup HTML/gambar asli (docs/mockups), dibuka dari kartu info layar.
    Route::get('/berkas/{file}', function (string $file) {
        $root = realpath(config('devlib.mockup_path'));
        $real = realpath($root.DIRECTORY_SEPARATOR.$file);
        abort_unless($real && $root && is_file($real) && str_starts_with($real, $root.DIRECTORY_SEPARATOR), 404);

        return response()->file($real);
    })->where('file', '.*')->name('mockup.file');
});
