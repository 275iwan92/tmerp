<?php

use App\Models\Inventory\InvSubconIssue;
use App\Models\Inventory\InvSubconIssueLine;
use App\Models\Inventory\WhTask;
use App\Models\Master\WhZone;
use App\Services\Inventory\CountTaskService;
use App\Services\Inventory\StockTransferService;
use App\Services\Inventory\SubconStockRefused;
use App\Services\Inventory\SubconStockService;
use App\Services\Inventory\WarehouseTaskRefused;
use App\Support\V2\Data\InTransit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Inventory & Warehouse v2.01: In Transit, Count Tasks, Send to Subcontractor. File route sendiri (dimuat routes/web.php).
// Logika: App\Services\Inventory\{StockTransferService, CountTaskService, SubconStockService}; layar: resources/views/v2/screens/{wh-in-transit,wh-count-tasks,subcon-send}.blade.php.
Route::prefix('v2/warehouse')->name('v2.wh.')->middleware('module:INV')->group(function () {
    $login = fn () => Auth::user()->login;

    // ------------------------------------------------------------ In Transit: terima dan selesaikan selisih
    $transit = fn (int $id) => route('v2.screen', 'wh-in-transit').'?id='.$id;

    Route::post('/transit/{id}/receive', function (Request $r, int $id) use ($login, $transit) {
        $doc = InTransit::find($id) ?? abort(404);
        $received = collect((array) $r->input('received', []))->map(fn ($v) => (float) $v)->all();
        $error = (new StockTransferService)->receive($doc, $received, $login());

        return $error ? redirect($transit($id))->withErrors(['receive' => $error]) : redirect($transit($id))->with('v2_flash', t('Received. Any difference stays in In Transit until it is settled.'));
    })->whereNumber('id')->name('transit.receive');

    Route::post('/transit/{id}/resolve', function (Request $r, int $id) use ($login, $transit) {
        $d = $r->validate(['line' => ['required', 'integer'], 'how' => ['required', 'in:LOSS,RETURN']], [], ['line' => 'Line', 'how' => 'Settle As']);
        $doc = InTransit::find($id) ?? abort(404);
        $error = (new StockTransferService)->resolveDiscrepancy($doc, (int) $d['line'], $d['how'], $login());

        return $error ? redirect($transit($id))->withErrors(['resolve' => $error]) : redirect($transit($id))->with('v2_flash', t('The difference was settled.'));
    })->whereNumber('id')->name('transit.resolve');

    // ------------------------------------------------------------ Count Tasks: mulai hitung satu area / semua yang jatuh tempo, isi hasil hitung
    $counts = fn (?int $task = null) => route('v2.screen', 'wh-count-tasks').($task ? '?id='.$task : '');

    Route::post('/count/start', function (Request $r) use ($login, $counts) {
        $d = $r->validate(['zone_id' => ['required', 'integer']], [], ['zone_id' => 'Area']);
        $zone = WhZone::query()->findOrFail((int) $d['zone_id']);
        try {
            $made = (new CountTaskService)->start($zone, $login());
        } catch (WarehouseTaskRefused $e) {
            return redirect($counts())->withErrors(['count' => $e->getMessage()]);
        }

        return redirect($counts())->with('v2_flash', $made > 0 ? tf(':n count task(s) made. The bins are locked until they are counted.', ['n' => $made]) : t('There is no stock in that area, so there is nothing to count.'));
    })->name('count.start');

    Route::post('/count/start-due', function () use ($login, $counts) {
        $made = (new CountTaskService)->startDue($login());

        return redirect($counts())->with('v2_flash', $made > 0 ? tf(':n count task(s) made for the areas that are due.', ['n' => $made]) : t('No area is due, or the due areas have no stock.'));
    })->name('count.start-due');

    Route::post('/count/{id}/confirm', function (Request $r, int $id) use ($login, $counts) {
        $d = $r->validate(['counted_qty' => ['required', 'numeric', 'min:0']], [], ['counted_qty' => 'Counted Quantity']);
        $task = WhTask::query()->where('task_type', 'COUNT')->findOrFail($id);
        try {
            $done = (new CountTaskService)->confirm($task, (float) $d['counted_qty'], $login());
        } catch (WarehouseTaskRefused $e) {
            return redirect($counts($id))->withInput()->withErrors(['count' => $e->getMessage()]);
        }

        return redirect($counts())->with('v2_flash', $done->adjustment_doc_no ? tf('Counted. Stock Opname :no corrected the stock.', ['no' => $done->adjustment_doc_no]) : t('Counted. No difference.'));
    })->whereNumber('id')->name('count.confirm');

    Route::post('/count/{id}/cancel', function (Request $r, int $id) use ($login, $counts) {
        try {
            (new CountTaskService)->cancel(WhTask::query()->where('task_type', 'COUNT')->findOrFail($id), (string) $r->input('_reason', ''), $login());
        } catch (WarehouseTaskRefused $e) {
            return redirect($counts($id))->withErrors(['count' => $e->getMessage()]);
        }

        return redirect($counts())->with('v2_flash', t('Count task cancelled.'));
    })->whereNumber('id')->name('count.cancel');

    // ------------------------------------------------------------ Send to Subcontractor
    $subcon = fn (?int $id = null) => route('v2.screen', 'subcon-send').($id ? '?id='.$id : '');

    Route::post('/subcon/create', function (Request $r) use ($login, $subcon) {
        $d = $r->validate(['po_id' => ['required', 'integer'], 'position' => ['required', 'string'], 'qty' => ['required', 'numeric', 'gt:0'],
            'due_return_date' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:255']], [], ['po_id' => 'Subcon PO', 'position' => 'Material and Bin', 'qty' => 'Quantity', 'due_return_date' => 'Due Back']);
        [$materialId, $binId] = array_map('intval', explode(':', $d['position']) + [0, 0]);
        try {
            $issue = (new SubconStockService)->send((int) $d['po_id'], $binId, $materialId, (float) $d['qty'], null, $d['due_return_date'] ?? null, $d['notes'] ?? null, $login());
        } catch (SubconStockRefused $e) {
            return redirect($subcon().'?new=1')->withInput()->withErrors(['send' => $e->getMessage()]);
        }

        return redirect($subcon($issue->id_subcon_issue))->with('v2_flash', tf('Sent. :no is now at the subcontractor.', ['no' => $issue->issue_no]));
    })->name('subcon.create');

    // action = return | consume | cancel
    Route::post('/subcon/{id}/act', function (Request $r, int $id) use ($login, $subcon) {
        $issue = InvSubconIssue::query()->findOrFail($id);
        $svc = new SubconStockService;
        try {
            switch ((string) $r->input('action')) {
                case 'return':
                    $d = $r->validate(['line' => ['required', 'integer'], 'qty' => ['required', 'numeric', 'gt:0'], 'to_location_id' => ['required', 'integer']], [], ['qty' => 'Quantity', 'to_location_id' => 'Return To Bin']);
                    $svc->returnUnused(InvSubconIssueLine::query()->where('issue_id', $id)->findOrFail((int) $d['line']), (float) $d['qty'], (int) $d['to_location_id'], $login());
                    $msg = t('Returned to the warehouse.');
                    break;
                case 'consume':
                    $d = $r->validate(['line' => ['required', 'integer'], 'qty' => ['required', 'numeric', 'gt:0']], [], ['qty' => 'Quantity']);
                    $svc->consume(InvSubconIssueLine::query()->where('issue_id', $id)->findOrFail((int) $d['line']), (float) $d['qty'], $login());
                    $msg = t('Recorded as used at the subcontractor.');
                    break;
                case 'cancel':
                    $svc->cancel($issue, (string) $r->input('_reason', ''), $login());
                    $msg = t('Send cancelled. The material is back in its bin.');
                    break;
                default:
                    abort(422);
            }
        } catch (SubconStockRefused $e) {
            return redirect($subcon($id))->withErrors(['subcon' => $e->getMessage()]);
        }

        return redirect($subcon($id))->with('v2_flash', $msg);
    })->whereNumber('id')->name('subcon.act');
});
