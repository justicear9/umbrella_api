<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NationalChatService;
use App\Services\RoomMentionParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class NationalChatController extends Controller
{
    public function messages(Request $request, NationalChatService $chat)
    {
        $user = $request->user();
        if (! $user || ! $user->isCommunicator()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $afterId = $request->integer('after_id') ?: null;
        $limit = min(100, max(1, $request->integer('limit', 50)));

        return response()->json([
            'success' => true,
            'messages' => $chat->messagesAfter($afterId > 0 ? $afterId : null, $limit),
        ]);
    }

    public function store(Request $request, NationalChatService $chat)
    {
        $user = $request->user();
        if (! $user || ! $user->isCommunicator()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $key = 'national-chat:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return response()->json(['success' => false, 'message' => 'Slow down — try again shortly.'], 429);
        }
        RateLimiter::hit($key, 60);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $body = trim(preg_replace("/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/u", '', $data['body']) ?? $data['body']);
        if ($body === '') {
            return response()->json(['success' => false, 'message' => 'Message cannot be empty.'], 422);
        }

        $result = $chat->post($user, $body);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'ai_message' => $result['ai_message'],
        ]);
    }

    public function mentionSuggestions(Request $request, RoomMentionParser $parser)
    {
        $user = $request->user();
        if (! $user || ! $user->isCommunicator()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $q = (string) $request->query('q', '');

        return response()->json([
            'success' => true,
            'suggestions' => $parser->suggestions($q, 25),
        ]);
    }
}
