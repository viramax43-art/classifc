<?php

use App\Http\Controllers\Okpd2Controller;
use App\Http\Controllers\TnvedController;
use Illuminate\Support\Facades\Route;

Route::get('/', [Okpd2Controller::class, 'index'])->name('okpd2.index');
Route::get('/tnved', [TnvedController::class, 'index'])->name('tnved.index');

Route::prefix('api/okpd2')->group(function () {
    Route::get('/sections', [Okpd2Controller::class, 'sections']);
    Route::get('/children', [Okpd2Controller::class, 'children']);
    Route::get('/search', [Okpd2Controller::class, 'search']);
    Route::get('/meta', [Okpd2Controller::class, 'meta']);
    Route::get('/{code}', [Okpd2Controller::class, 'show'])->where('code', '[0-9A-Za-z\.\-]+');
});

Route::prefix('api/tnved')->group(function () {
    Route::get('/tree', [TnvedController::class, 'treeRoot']);
    Route::get('/tree/search', [TnvedController::class, 'treeSearch']);
    Route::get('/tree/{nodeId}', [TnvedController::class, 'treeBranch'])->where('nodeId', '[0-9]+');
    Route::get('/sections', [TnvedController::class, 'sections']);
    Route::get('/children', [TnvedController::class, 'children']);
    Route::get('/search', [TnvedController::class, 'search']);
    Route::get('/meta', [TnvedController::class, 'meta']);
    Route::get('/{code}', [TnvedController::class, 'show'])->where('code', '[0-9]+');
});
