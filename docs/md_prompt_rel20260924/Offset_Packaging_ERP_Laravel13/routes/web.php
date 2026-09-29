<?php

declare(strict_types=1);

use App\Http\Controllers\InquiryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [InquiryController::class, 'create'])->name('inquiry.create');
Route::get('/inquiry/export-dieline-svg', [InquiryController::class, 'exportDielineSvg'])->name('inquiry.export-dieline-svg');
