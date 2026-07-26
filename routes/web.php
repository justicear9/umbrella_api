<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Middleware\AdminKeyMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('admin.dashboard'));

Route::get('/admin/login', [AdminController::class, 'loginForm'])->name('admin.login');
Route::post('/admin/login', [AdminController::class, 'login'])->name('admin.login.submit');

Route::middleware(AdminKeyMiddleware::class)->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::post('/logout', [AdminController::class, 'logout'])->name('admin.logout');
    Route::post('/upload', [AdminController::class, 'upload'])->name('admin.upload');
    Route::post('/ai-keys', [AdminController::class, 'updateAiKeys'])->name('admin.ai-keys');
    Route::post('/documents/{document}/digest', [AdminController::class, 'digest'])->name('admin.digest');
    Route::delete('/documents/{document}', [AdminController::class, 'destroyDocument'])->name('admin.documents.destroy');
    Route::put('/briefings/{briefing}', [AdminController::class, 'updateBriefing'])->name('admin.briefings.update');
    Route::post('/communicators', [AdminController::class, 'storeCommunicator'])->name('admin.communicators.store');
    Route::post('/admins', [AdminController::class, 'storeAdmin'])->name('admin.admins.store');
    Route::post('/press-prep/assign', [AdminController::class, 'assignPressPrep'])->name('admin.press-prep.assign');
    Route::get('/press-prep/{session}', [AdminController::class, 'pressPrepTranscript'])->name('admin.press-prep.show');
    Route::get('/press-prep/{session}/transcript.txt', [AdminController::class, 'pressPrepTranscriptTxt'])->name('admin.press-prep.transcript.txt');
    Route::get('/press-prep/{session}/transcript.pdf', [AdminController::class, 'pressPrepTranscriptPdf'])->name('admin.press-prep.transcript.pdf');

    Route::post('/notices', [AdminController::class, 'storeNotice'])->name('admin.notices.store');
    Route::post('/notices/{notice}/publish', [AdminController::class, 'publishNotice'])->name('admin.notices.publish');
    Route::post('/notices/{notice}/unpublish', [AdminController::class, 'unpublishNotice'])->name('admin.notices.unpublish');
    Route::delete('/notices/{notice}', [AdminController::class, 'destroyNotice'])->name('admin.notices.destroy');

    Route::post('/media', [AdminController::class, 'storeMedia'])->name('admin.media.store');
    Route::post('/media/{mediaAsset}/publish', [AdminController::class, 'publishMedia'])->name('admin.media.publish');
    Route::post('/media/{mediaAsset}/unpublish', [AdminController::class, 'unpublishMedia'])->name('admin.media.unpublish');
    Route::delete('/media/{mediaAsset}', [AdminController::class, 'destroyMedia'])->name('admin.media.destroy');
});
