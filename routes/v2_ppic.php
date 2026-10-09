<?php

use App\Http\Controllers\V2\PpicController;
use Illuminate\Support\Facades\Route;

// PPIC v2: Production Scheduling (Gantt, Kanban, Calendar, Grid, Monitoring) and the Work Order screen.
// Planning logic = App\Services\Scheduling\Plan\*; the controller only calls it. Run the dummy site first (mockup.test).
Route::prefix('v2/ppic')->name('v2.ppic.')->middleware('module:PROD')->group(function () {
    Route::post('/run', [PpicController::class, 'run'])->name('run');
    Route::post('/confirm', [PpicController::class, 'confirm'])->name('confirm');
    Route::post('/discard', [PpicController::class, 'discard'])->name('discard');
    Route::post('/prioritize', [PpicController::class, 'prioritize'])->name('prioritize');
    Route::post('/unpin', [PpicController::class, 'unpin'])->name('unpin');
    Route::post('/impact', [PpicController::class, 'impact'])->name('impact');
    Route::post('/move', [PpicController::class, 'moveOp'])->name('move');
    Route::post('/kanban-move', [PpicController::class, 'kanbanMove'])->name('kanban-move');
    Route::post('/sheet-save', [PpicController::class, 'sheetSave'])->name('sheet-save');
    Route::post('/wo/create', [PpicController::class, 'createWo'])->name('wo.create');
    Route::post('/operator/{op}/play', [PpicController::class, 'opPlay'])->name('op.play');
    Route::post('/operator/{op}/pause', [PpicController::class, 'opPause'])->name('op.pause');
    Route::post('/operator/{op}/stop', [PpicController::class, 'opStop'])->name('op.stop');
    Route::post('/operator/{op}/quantity', [PpicController::class, 'opQuantity'])->name('op.quantity');
    Route::get('/operator/{op}/problem', [PpicController::class, 'opProblem'])->name('op.problem');
    Route::post('/learn/run', [PpicController::class, 'learnRun'])->name('learn.run');
    Route::post('/learn/revert', [PpicController::class, 'learnRevert'])->name('learn.revert');
    Route::post('/wo/{wo}/refresh', [PpicController::class, 'refreshWo'])->name('wo.refresh');
    Route::post('/wo/{wo}/output', [PpicController::class, 'output'])->name('wo.output');
});
