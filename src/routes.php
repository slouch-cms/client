<?php

use Illuminate\Support\Facades\Route;

use SlouceCMS\Client\SlouchCMSController;

/**
 * TODO: we allow ANY requests here, when really we should specify GET or POST
 */

Route::middleware(['slouch-cms'])->prefix('slouch-cms')->group(function () {
    Route::any('/',  [SlouchCMSController::class, 'index']);
    Route::any('get-database-structure', [SlouchCMSControlle::class, 'getDatabaseStructure']);
    Route::any('get-records', [SlouchCMSController::class, 'getRecords']);
    Route::any('get-record', [SlouchCMSController::class, 'getRecord']);
    Route::any('put-record', [SlouchCMSController::class, 'putRecord']);
    Route::any('delete-record', [SlouchCMSController::class, 'deleteRecord']);
    Route::any('reorder-records', [SlouchCMSController::class, 'reorderRecords']);
});