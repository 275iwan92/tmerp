<?php

use App\Models\Master\MatRaw;
use App\Models\Mnt\MntAsset;
use App\Models\Mnt\MntAssetPart;
use App\Models\Mnt\MntDocument;
use App\Models\Mnt\MntLog;
use App\Models\Mnt\MntWatchPoint;
use App\Services\Maintenance\AssetProfileService;
use App\Services\Maintenance\MaintenanceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Maintenance V2, Fase 1 (Master Data). Logika = App\Services\Maintenance\AssetProfileService.
// File route sendiri (dimuat routes/web.php sesudah v2.php). Mengubah master = hak approve modul MAINT (kepala maintenance) atau administrator.
Route::prefix('v2/maintenance')->name('v2.mnt.')->middleware('module:MAINT')->group(function () {
    $head = fn () => abort_unless(MaintenanceAccess::canManage(Auth::user()), 403, t('Only the head of maintenance (approve right on the Maintenance module) can change this.'));
    $asset = fn (int $id) => route('v2.screen', 'maintenance-profile').'?id='.$id;
    $doc = fn (int $id) => route('v2.screen', 'maintenance-documents').'?id='.$id;
    $num = fn ($v) => ($v === null || $v === '') ? null : (float) $v;

    // ------------------------------------------------------------ Asset Maintenance Profile
    Route::post('/asset/sync', function () use ($head) {
        $head();
        $r = (new AssetProfileService)->syncMachines();

        return redirect(route('v2.screen', 'maintenance-profile'))->with('v2_flash', tf(':n machines added from Master Machine, :u updated.', ['n' => $r['created'], 'u' => $r['updated']]));
    })->name('asset.sync');

    Route::post('/asset/save', function (Request $r) use ($head, $asset, $num) {
        $head();
        $action = (string) $r->input('action', 'save');
        $a = $r->filled('id') ? MntAsset::findOrFail($r->integer('id')) : null;
        $login = Auth::user()->login;

        if ($a && in_array($action, ['activate', 'deactivate'], true)) {
            $a->update(['activate' => $action === 'activate', 'updated_by' => $login]);
            MntLog::add('ASSET', $a->id_asset, $action === 'activate' ? 'Activated' : 'Deactivated');

            return redirect($asset($a->id_asset))->with('v2_flash', $action === 'activate' ? 'Asset activated.' : 'Asset deactivated.');
        }

        // Aset tertaut Master Mesin: kode, nama, kelas dan kritikalitas dibaca dari sana, tidak diubah di sini.
        $linked = $a?->isLinkedMachine() ?? false;
        $rules = ['meter_type' => ['required', Rule::in(array_keys(MntAsset::METERS))], 'location' => ['nullable', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:2000'],
            'fixed_asset_id' => ['nullable', 'integer', 'exists:tm2acc.fin_asset,asset_id']];
        if (! $linked) {
            $rules += ['asset_code' => ['required', 'string', 'max:40', 'unique:tm2mnt.mnt_asset,asset_code'.($a ? ','.$a->id_asset.',id_asset' : '')], 'asset_name' => ['required', 'string', 'max:150'],
                'asset_class' => ['required', Rule::in(['VEHICLE', 'FACILITY', 'SUPPORT', 'MACHINE'])], 'criticality' => ['nullable', Rule::in(['A', 'B', 'C'])]];
        }
        $d = $r->validate($rules, [], ['asset_code' => t('Asset code'), 'asset_name' => t('Asset name'), 'asset_class' => t('Class'), 'meter_type' => t('Meter')]);
        $d['fixed_asset_id'] = ($d['fixed_asset_id'] ?? null) ?: null;
        $isNew = ! $a;
        $a = $a ? tap($a)->update($d + ['updated_by' => $login]) : MntAsset::create($d + ['activate' => true, 'created_by' => $login, 'updated_by' => $login]);
        MntLog::add('ASSET', $a->id_asset, $isNew ? 'Created' : 'Saved');

        // Watch point: hapus yang ditandai, simpan yang berubah.
        foreach (array_keys((array) $r->input('remove', [])) as $wid) {
            $a->watchPoints()->whereKey((int) $wid)->delete();
        }
        foreach ((array) $r->input('watch', []) as $wid => $row) {
            $min = $num($row['normal_min'] ?? null);
            $max = $num($row['normal_max'] ?? null);
            if ($min !== null && $max !== null && $min > $max) {
                throw ValidationException::withMessages(['watch' => t('Normal limit: the minimum cannot be above the maximum.')]);
            }
            $a->watchPoints()->whereKey((int) $wid)->update(['component_code' => ($row['component_code'] ?? '') ?: null, 'check_text' => trim((string) ($row['check_text'] ?? '')) ?: 'Check',
                'method_text' => ($row['method_text'] ?? '') ?: null, 'normal_min' => $min, 'normal_max' => $max, 'measure_uom' => ($row['measure_uom'] ?? '') ?: null,
                'frequency' => array_key_exists($row['frequency'] ?? '', MntWatchPoint::FREQUENCIES) ? $row['frequency'] : 'DAILY',
                'checked_by_role' => array_key_exists($row['checked_by_role'] ?? '', MntWatchPoint::ROLES) ? $row['checked_by_role'] : 'OPERATOR',
                'activate' => filter_var($row['activate'] ?? false, FILTER_VALIDATE_BOOL), 'updated_by' => $login]);
        }
        foreach (array_keys((array) $r->input('remove_parts', [])) as $pid) {
            $a->parts()->whereKey((int) $pid)->delete();
        }
        foreach ((array) $r->input('parts', []) as $pid => $row) {
            $a->parts()->whereKey((int) $pid)->update(['component_code' => ($row['component_code'] ?? '') ?: null, 'qty_per_use' => max(0.001, (float) ($row['qty_per_use'] ?? 1)),
                'min_stock_suggested' => $num($row['min_stock_suggested'] ?? null), 'expected_life_days' => ($row['expected_life_days'] ?? '') === '' ? null : (int) $row['expected_life_days'],
                'is_critical' => filter_var($row['is_critical'] ?? false, FILTER_VALIDATE_BOOL), 'updated_by' => $login]);
        }
        foreach (array_keys((array) $r->input('remove_docs', [])) as $did) {
            $a->documents()->detach((int) $did);
        }

        $msg = 'Profile saved.';
        if ($action === 'add_watch' && trim((string) $r->input('new_watch')) !== '') {
            $a->watchPoints()->create(['check_text' => trim((string) $r->input('new_watch')), 'component_code' => $r->input('new_watch_component') ?: null,
                'frequency' => array_key_exists($r->input('new_watch_frequency'), MntWatchPoint::FREQUENCIES) ? $r->input('new_watch_frequency') : 'DAILY',
                'checked_by_role' => array_key_exists($r->input('new_watch_role'), MntWatchPoint::ROLES) ? $r->input('new_watch_role') : 'OPERATOR',
                'sort_order' => (int) $a->watchPoints()->max('sort_order') + 1, 'activate' => true, 'created_by' => $login, 'updated_by' => $login]);
            $msg = 'Watch point added.';
        } elseif ($action === 'copy_watch' && $r->filled('copy_from')) {
            $n = (new AssetProfileService)->copyWatchPoints(MntAsset::findOrFail($r->integer('copy_from')), $a);
            $msg = tf(':n watch points copied.', ['n' => $n]);
        } elseif ($action === 'add_part' && $r->filled('material_id')) {
            $mat = MatRaw::findOrFail($r->integer('material_id'));
            if (MntAssetPart::where('asset_id', $a->id_asset)->where('material_id', $mat->material_id)->exists()) {
                throw ValidationException::withMessages(['material_id' => t('This spare part is already on the asset.')]);
            }
            MntAssetPart::create(['asset_id' => $a->id_asset, 'material_id' => $mat->material_id, 'qty_per_use' => max(0.001, (float) $r->input('new_part_qty', 1)),
                'component_code' => $r->input('new_part_component') ?: null, 'activate' => true, 'created_by' => $login, 'updated_by' => $login]);
            MntLog::add('ASSET', $a->id_asset, 'Spare part added', $mat->material_code);
            $msg = 'Spare part added.';
        } elseif ($action === 'link_doc' && $r->filled('link_document')) {
            $a->documents()->syncWithoutDetaching([$r->integer('link_document') => ['created_by' => $login, 'updated_by' => $login]]);
            MntLog::add('ASSET', $a->id_asset, 'Document linked', MntDocument::find($r->integer('link_document'))?->doc_no);
            $msg = 'Document linked.';
        }

        return redirect($asset($a->id_asset))->with('v2_flash', $msg);
    })->name('asset.save');

    // ------------------------------------------------------------ SOP and Work Instructions
    Route::post('/document/save', function (Request $r) use ($head, $doc) {
        $head();
        $action = (string) $r->input('action', 'save');
        $m = $r->filled('id') ? MntDocument::findOrFail($r->integer('id')) : null;
        $login = Auth::user()->login;

        if ($m && in_array($action, ['activate', 'deactivate'], true)) {
            $m->update(['activate' => $action === 'activate', 'updated_by' => $login]);
            MntLog::add('DOCUMENT', $m->id_document, $action === 'activate' ? 'Activated' : 'Deactivated');

            return redirect($doc($m->id_document))->with('v2_flash', $action === 'activate' ? 'Document activated.' : 'Document deactivated.');
        }
        if ($m && $action === 'approve') {
            abort_if($m->isApproved(), 422, t('This document is already approved.'));
            $m->update(['status' => 'APPROVED', 'approved_by' => $login, 'approved_at' => now(), 'updated_by' => $login]);
            MntLog::add('DOCUMENT', $m->id_document, 'Approved', 'Revision '.$m->revision);

            return redirect($doc($m->id_document))->with('v2_flash', 'Document approved.');
        }
        if ($m && $action === 'to_draft') {
            abort_unless($m->isApproved(), 422, t('Only an approved document can go back to draft.'));
            $rev = str_pad((string) ((int) $m->revision + 1), 2, '0', STR_PAD_LEFT);
            $m->update(['status' => 'DRAFT', 'revision' => $rev, 'approved_by' => null, 'approved_at' => null, 'updated_by' => $login]);
            MntLog::add('DOCUMENT', $m->id_document, 'Back to draft', 'New revision '.$rev);

            return redirect($doc($m->id_document))->with('v2_flash', 'Document is a draft again (new revision).');
        }
        abort_if($m && $m->isApproved(), 422, t('An approved document is locked. Use Back to Draft first.'));

        $d = $r->validate(['doc_no' => ['required', 'string', 'max:40', 'unique:tm2mnt.mnt_document,doc_no'.($m ? ','.$m->id_document.',id_document' : '')],
            'kind' => ['required', Rule::in(array_keys(MntDocument::KINDS))], 'doc_set' => ['nullable', 'string', 'max:10'], 'title' => ['required', 'string', 'max:200'],
            'file_location' => ['nullable', 'string', 'max:255']], [], ['doc_no' => t('Document no.'), 'title' => t('Title')]);
        $isNew = ! $m;
        $m = $m ? tap($m)->update($d + ['updated_by' => $login]) : MntDocument::create($d + ['revision' => '00', 'status' => 'DRAFT', 'activate' => true, 'created_by' => $login, 'updated_by' => $login]);
        MntLog::add('DOCUMENT', $m->id_document, $isNew ? 'Created' : 'Saved');

        foreach (array_keys((array) $r->input('remove', [])) as $lid) {
            $m->lines()->whereKey((int) $lid)->delete();
        }
        foreach ((array) $r->input('lines', []) as $lid => $row) {
            $m->lines()->whereKey((int) $lid)->update(['section' => trim((string) ($row['section'] ?? '')) ?: 'Steps', 'line_text' => trim((string) ($row['line_text'] ?? '')) ?: '-', 'updated_by' => $login]);
        }
        $msg = 'Document saved.';
        if ($action === 'add_line' && trim((string) $r->input('new_line')) !== '') {
            $m->lines()->create(['seq_no' => (int) $m->lines()->max('seq_no') + 1, 'section' => trim((string) $r->input('new_section', 'Steps')) ?: 'Steps',
                'line_text' => trim((string) $r->input('new_line')), 'created_by' => $login, 'updated_by' => $login]);
            $msg = 'Line added.';
        }

        return redirect($doc($m->id_document))->with('v2_flash', $msg);
    })->name('document.save');
});
