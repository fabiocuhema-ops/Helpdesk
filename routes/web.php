<?php

use App\Http\Controllers\MissionPublicaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MissionPublicaController::class, 'app'])->name('mission.app');
Route::get('/sw.js', fn () => response()->file(public_path('sw.js')))->name('mission.sw');

Route::prefix('api')->group(function (): void {
    Route::get('/bootstrap', [MissionPublicaController::class, 'bootstrap']);
    Route::post('/login-demo', [MissionPublicaController::class, 'login']);
    Route::get('/demands', [MissionPublicaController::class, 'demands']);
    Route::post('/demands', [MissionPublicaController::class, 'storeDemand']);
    Route::post('/demands/{demand}/transition', [MissionPublicaController::class, 'transition']);
    Route::post('/legacy/tickets', [MissionPublicaController::class, 'legacyCreate']);
    Route::post('/legacy/import', [MissionPublicaController::class, 'importLegacy']);
    Route::post('/legacy/tickets/{ticket}/toggle-failure', [MissionPublicaController::class, 'legacyToggleFailure']);
    Route::get('/integration', [MissionPublicaController::class, 'integration']);
    Route::post('/ai/suggest', [MissionPublicaController::class, 'aiSuggest']);
    Route::post('/push/subscribe', [MissionPublicaController::class, 'pushSubscribe']);
});
