<?php

/*
 * REMARK
 * Tujuan       : Rute aksi PPIC (prefix v2/ppic, modul PROD).
 * Sumber hulu  : PpicController.
 * Input        : POST/JSON dari layar WO, Colour Sequence, Workspace, Operator.
 * Output       : Respons controller; nama rute v2.ppic.*.
 * Dipakai oleh : Layar production-wo dan layar PPIC lain.
 * Status       : Aktif.
 */

use App\Http\Controllers\V2\PpicController;
use Illuminate\Support\Facades\Route;

// PPIC v2: Production Scheduling (Gantt, Kanban, Calendar, Grid, Monitoring) and the Work Order screen.
// Planning logic = App\Services\Scheduling\Plan\*; the controller only calls it. Run the dummy site first (mockup.test).
Route::prefix('v2/ppic')->name('v2.ppic.')->middleware('module:PROD')->group(function () {
    Route::post('/run', [PpicController::class, 'run'])->name('run');
    Route::post('/confirm', [PpicController::class, 'confirm'])->name('confirm');
    Route::post('/discard', [PpicController::class, 'discard'])->name('discard');
    Route::post('/prioritize', [PpicController::class, 'prioritize'])->name('prioritize');
    Route::post('/undo-confirm', [PpicController::class, 'undoConfirm'])->name('undo-confirm');
    Route::post('/die-group', [PpicController::class, 'dieGroup'])->name('die-group');
    Route::post('/unpin', [PpicController::class, 'unpin'])->name('unpin');
    Route::post('/color/move', [PpicController::class, 'colorMove'])->name('color.move');
    Route::post('/color/request', [PpicController::class, 'colorRequest'])->name('color.request');
    Route::post('/color/answer', [PpicController::class, 'colorAnswer'])->name('color.answer');
    Route::post('/impact', [PpicController::class, 'impact'])->name('impact');
    Route::post('/move', [PpicController::class, 'moveOp'])->name('move');
    Route::post('/kanban-move', [PpicController::class, 'kanbanMove'])->name('kanban-move');
    Route::post('/sheet-save', [PpicController::class, 'sheetSave'])->name('sheet-save');
    Route::post('/wo/create', [PpicController::class, 'createWo'])->name('wo.create');
    Route::post('/output/{op}/timer', [PpicController::class, 'outputTimer'])->name('output.timer');
    Route::post('/output/{op}/result', [PpicController::class, 'outputResult'])->name('output.result');
    Route::get('/operator/{op}/problem', [PpicController::class, 'opProblem'])->name('op.problem');
    Route::post('/learn/run', [PpicController::class, 'learnRun'])->name('learn.run');
    Route::post('/learn/revert', [PpicController::class, 'learnRevert'])->name('learn.revert');
    Route::post('/wo/{wo}/delete', [PpicController::class, 'deleteWo'])->name('wo.delete');
    Route::post('/wo/{wo}/target-cells', [PpicController::class, 'targetCells'])->name('wo.target-cells');
    Route::post('/wo/{wo}/reorder', [PpicController::class, 'reorderOps'])->name('wo.reorder');
    Route::post('/wo/{wo}/earliest', [PpicController::class, 'earliestStart'])->name('wo.earliest');
    Route::post('/wo/{wo}/ops-delete', [PpicController::class, 'deleteOps'])->name('wo.ops-delete');
    Route::post('/wo/{wo}/materials-delete', [PpicController::class, 'deleteMaterials'])->name('wo.materials-delete');
    Route::post('/wo/{wo}/ops-reset', [PpicController::class, 'resetOps'])->name('wo.ops-reset');
    Route::post('/wo/{wo}/materials-reset', [PpicController::class, 'resetMaterials'])->name('wo.materials-reset');
    Route::post('/wo/{wo}/bom-cells', [PpicController::class, 'bomCells'])->name('wo.bom-cells');
    Route::post('/wo/{wo}/apply', [PpicController::class, 'applyWo'])->name('wo.apply');
    Route::post('/wo/{wo}/process', [PpicController::class, 'processWo'])->name('wo.process');
    Route::post('/apply-all', [PpicController::class, 'applyAll'])->name('apply-all');
    Route::post('/pending/discard', [PpicController::class, 'discardPending'])->name('pending.discard');
    Route::post('/queue-move', [PpicController::class, 'queueMoveOp'])->name('queue-move');
    Route::post('/wo/{wo}/refresh', [PpicController::class, 'refreshWo'])->name('wo.refresh');
});
