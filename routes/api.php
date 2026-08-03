<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BriefingController;
use App\Http\Controllers\Api\ChatHistoryController;
use App\Http\Controllers\Api\CommunicatorDirectoryController;
use App\Http\Controllers\Api\ContentReportController;
use App\Http\Controllers\Api\DevicePushTokenController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\GeographyController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MediaLibraryController;
use App\Http\Controllers\Api\NationalChatController;
use App\Http\Controllers\Api\NoticeController;
use App\Http\Controllers\Api\PressPrepController;
use App\Http\Controllers\Api\TermsController;
use App\Http\Controllers\Api\UserBlockController;
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
Route::get('/media-assets/{mediaAsset}/file', [MediaLibraryController::class, 'file']);

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

    Route::get('/geography', [GeographyController::class, 'index']);
    Route::post('/device/push-token', [DevicePushTokenController::class, 'store']);

    Route::get('/chat/national/messages', [NationalChatController::class, 'messages']);
    Route::post('/chat/national/messages', [NationalChatController::class, 'store']);
    Route::post('/chat/national/messages/{message}/report', [ContentReportController::class, 'store']);
    Route::get('/chat/mention-suggestions', [NationalChatController::class, 'mentionSuggestions']);

    Route::get('/terms/status', [TermsController::class, 'status']);
    Route::post('/terms/accept', [TermsController::class, 'accept']);

    Route::get('/users/blocked', [UserBlockController::class, 'index']);
    Route::post('/users/{user}/block', [UserBlockController::class, 'store']);
    Route::delete('/users/{user}/block', [UserBlockController::class, 'destroy']);

    Route::get('/communicators', [CommunicatorDirectoryController::class, 'index']);
    Route::get('/communicators/{user}', [CommunicatorDirectoryController::class, 'show']);

    Route::get('/notices/unread-count', [NoticeController::class, 'unreadCount']);
    Route::post('/notices/read-all', [NoticeController::class, 'markAllRead']);
    Route::get('/notices', [NoticeController::class, 'index']);
    Route::get('/notices/{notice}', [NoticeController::class, 'show']);
    Route::post('/notices/{notice}/read', [NoticeController::class, 'markRead']);

    Route::get('/media-assets', [MediaLibraryController::class, 'index']);
    Route::get('/media-assets/{mediaAsset}', [MediaLibraryController::class, 'show']);
    Route::post('/media-assets/{mediaAsset}/signed-url', [MediaLibraryController::class, 'signedUrl']);

    Route::get('/press-prep/mine', [PressPrepController::class, 'mine']);
    Route::post('/press-prep/sessions', [PressPrepController::class, 'store']);
    Route::get('/press-prep/sessions/{session}', [PressPrepController::class, 'show']);
    Route::post('/press-prep/sessions/{session}/start', [PressPrepController::class, 'start']);
    Route::post('/press-prep/sessions/{session}/answer', [PressPrepController::class, 'answer']);
    Route::post('/press-prep/sessions/{session}/hint', [PressPrepController::class, 'hint']);
    Route::post('/press-prep/sessions/{session}/debrief', [PressPrepController::class, 'debrief']);
});
