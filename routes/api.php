<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BriefingController;
use App\Http\Controllers\Api\ChatHistoryController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PressPrepController;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [BriefingController::class, 'categories']);
Route::get('/briefings', [BriefingController::class, 'index']);
Route::get('/briefings/{briefing}', [BriefingController::class, 'show']);
Route::post('/chat', [BriefingController::class, 'chat']); // legacy anonymous

Route::post('/tts', [MediaController::class, 'tts']);
Route::get('/media/tts/{file}', [MediaController::class, 'audio'])->where('file', '[A-Za-z0-9_-]+\.(wav|mp3|m4a)');
Route::post('/stt', [MediaController::class, 'stt']);

Route::get('/documents/{document}/file', [DocumentController::class, 'file']);
Route::post('/documents/{document}/replace-file', [DocumentController::class, 'replaceFile']);

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('api.token')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/chat/threads', [ChatHistoryController::class, 'threads']);
    Route::post('/chat/threads', [ChatHistoryController::class, 'store']);
    Route::get('/chat/threads/{thread}', [ChatHistoryController::class, 'show']);
    Route::delete('/chat/threads/{thread}', [ChatHistoryController::class, 'destroy']);
    Route::post('/chat/send', [ChatHistoryController::class, 'send']);

    Route::post('/documents/{document}/signed-url', [DocumentController::class, 'signedUrl']);

    Route::get('/press-prep/mine', [PressPrepController::class, 'mine']);
    Route::post('/press-prep/sessions', [PressPrepController::class, 'store']);
    Route::get('/press-prep/sessions/{session}', [PressPrepController::class, 'show']);
    Route::post('/press-prep/sessions/{session}/start', [PressPrepController::class, 'start']);
    Route::post('/press-prep/sessions/{session}/answer', [PressPrepController::class, 'answer']);
    Route::post('/press-prep/sessions/{session}/hint', [PressPrepController::class, 'hint']);
    Route::post('/press-prep/sessions/{session}/debrief', [PressPrepController::class, 'debrief']);
});
