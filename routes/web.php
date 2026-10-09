<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MissionPublicaController;
use Illuminate\Support\Facades\Route;

Route::get('/sw.js', fn () => response()->file(public_path('sw.js')))->name('mission.sw');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1');
});

Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/', [MissionPublicaController::class, 'app'])->name('dashboard');
    Route::get('/visao-geral', [MissionPublicaController::class, 'app'])->name('overview');
    Route::get('/demandas', [MissionPublicaController::class, 'app'])->name('demands');
    Route::get('/demandas/nova', [MissionPublicaController::class, 'app'])->name('demands.create');
    Route::get('/demandas/{demand}', [MissionPublicaController::class, 'app'])->name('demands.show');
    Route::get('/minhas-missoes', [MissionPublicaController::class, 'app'])->name('missions.mine');
    Route::get('/minhas-solicitacoes', [MissionPublicaController::class, 'app'])->name('requests.mine');
    Route::get('/equipes', [MissionPublicaController::class, 'app'])->name('teams');
    Route::get('/indicadores', [MissionPublicaController::class, 'app'])->name('indicators');
    Route::get('/reconhecimento', [MissionPublicaController::class, 'app'])->name('recognition');
    Route::get('/integracoes', [MissionPublicaController::class, 'app'])->name('integrations');
    Route::get('/administracao', [MissionPublicaController::class, 'app'])->name('admin');
});

Route::prefix('api')->middleware('auth')->group(function (): void {
    Route::get('/bootstrap', [MissionPublicaController::class, 'bootstrap']);
    Route::get('/demands', [MissionPublicaController::class, 'demands']);
    Route::post('/demands', [MissionPublicaController::class, 'storeDemand']);
    Route::post('/demands/{demand}/transition', [MissionPublicaController::class, 'transition']);
    Route::post('/demands/{demand}/evidences', [MissionPublicaController::class, 'storeEvidence']);
    Route::get('/evidences/{evidence}', [MissionPublicaController::class, 'downloadEvidence']);
    Route::post('/legacy/tickets', [MissionPublicaController::class, 'legacyCreate']);
    Route::post('/legacy/import', [MissionPublicaController::class, 'importLegacy']);
    Route::post('/legacy/tickets/{ticket}/toggle-failure', [MissionPublicaController::class, 'legacyToggleFailure']);
    Route::get('/integration', [MissionPublicaController::class, 'integration']);
    Route::post('/ai/suggest', [MissionPublicaController::class, 'aiSuggest']);
    Route::post('/push/subscribe', [MissionPublicaController::class, 'pushSubscribe']);
});
