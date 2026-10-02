<?php

use App\Models\Master\ProdMasterCard;
use App\Services\MasterCard\MasterCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

// Master Card Collection v2 (Sales > Inquiry & Quotation > Pro-Dev). Logika = App\Services\MasterCard\MasterCardService.
// File route sendiri (dimuat routes/web.php sesudah v2.php) supaya tidak bercampur dengan pekerjaan modul lain di v2.php.
Route::prefix('v2/master-card')->name('v2.mcard.')->middleware('module:SALES')->group(function () {
    $svc = fn () => new MasterCardService(Auth::user()->login);
    $home = fn (?int $id = null) => route('v2.screen', 'master-card-collection').($id ? '?id='.$id : '');

    Route::post('/upload', function (Request $r) use ($svc, $home) {
        $r->validate(['files' => 'required|array|max:50', 'files.*' => 'file|max:30720|mimes:xlsx,xlsm,xls,jpg,jpeg,png'], [], ['files' => t('Master card file')]);
        $done = [];
        foreach ($r->file('files') as $f) {
            $done[] = $svc()->import($f);
        }

        return redirect(count($done) === 1 ? $home($done[0]->id_master_card) : $home())
            ->with('v2_flash', raw(str_replace([':n', ':cards'], [(string) count($done), collect($done)->map(fn ($c) => ($c->product_name ?? '#'.$c->id_master_card).' ('.t(ProdMasterCard::STATUS[$c->status]).')')->implode(', ')],
                t(':n master card(s) read: :cards.'))));
    })->name('upload');

    // + New Record: kartu manual (mis. dari foto) -> baris baru lalu disimpan seperti Save biasa.
    Route::post('/new', function (Request $r) use ($svc, $home) {
        $r->validate(['product_name' => 'required|string|max:200', 'card_date' => 'nullable|date', 'customer_id' => 'nullable|integer|exists:tm2mst.cust_customer,id_customer',
            'type_code' => 'nullable|exists:tm2mst.prod_type,type_code', 'artwork.*' => 'nullable|image|mimes:jpg,jpeg,png|max:20480'], [], ['product_name' => t('Item Name')]);
        $card = $svc()->fromData(['product_name' => $r->input('product_name')], 'MANUAL');
        $svc()->save($card, $r->except(['_token', '_action', 'artwork']), $r->file('artwork', []));

        return redirect($home($card->id_master_card))->with('v2_flash', t('Master card created.'));
    })->name('new');

    Route::post('/{id}', function (Request $r, int $id) use ($svc, $home) {
        $card = ProdMasterCard::findOrFail($id);
        if ($r->input('_action') === 'delete') {
            $svc()->delete($card);

            return redirect($home())->with('v2_flash', t('Master card deleted.'));
        }
        $r->validate(['card_date' => 'nullable|date', 'customer_id' => 'nullable|integer|exists:tm2mst.cust_customer,id_customer', 'type_code' => 'nullable|exists:tm2mst.prod_type,type_code',
            'artwork.*' => 'nullable|image|mimes:jpg,jpeg,png|max:20480']);
        $svc()->save($card, $r->except(['_token', '_action', 'artwork']), $r->file('artwork', []));

        return redirect($home($id))->with('v2_flash', t('Saved.'));
    })->whereNumber('id')->name('save');

    Route::post('/{id}/photo', function (Request $r, int $id) use ($svc, $home) {
        $r->validate(['photo' => 'required|image|mimes:jpg,jpeg,png|max:20480']);
        $card = ProdMasterCard::findOrFail($id);
        $f = $r->file('photo');
        $svc()->storeFile($card, 'PHOTO', file_get_contents($f->getRealPath()), $f->getClientOriginalName(), $f->getMimeType(), $card->files()->count() + 1);

        return redirect($home($id))->with('v2_flash', t('Photo added.'));
    })->whereNumber('id')->name('photo');

    Route::post('/{id}/convert', function (Request $r, int $id) use ($svc, $home) {
        $card = ProdMasterCard::findOrFail($id);
        abort_if($card->converted(), 422, t('The card has already been converted.'));
        try {
            if ($r->input('target') === 'product') {
                $p = $svc()->makeProduct($card, $r->all());

                return redirect(route('v2.screen', 'master-product').'?id='.$p->id_product)->with('v2_flash', raw(str_replace(':name', $p->product_name, t('Master Product ":name" created from the master card.'))));
            }
            $res = $svc()->makeInquiry($card, $r->all());

            return redirect($res['url'])->with('v2_flash', $res['message']);
        } catch (ValidationException $e) {
            return redirect($home($id))->withErrors($e->errors())->withInput()->with('mcard_dialog', $r->input('target'));
        }
    })->whereNumber('id')->name('convert');
});
