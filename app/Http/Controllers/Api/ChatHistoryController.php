<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use App\Services\RagService;
use Illuminate\Http\Request;

class ChatHistoryController extends Controller
{
    public function threads(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $threads = $user->chatThreads()
            ->with(['messages' => fn ($q) => $q->orderByDesc('id')->limit(1)])
            ->limit(40)
            ->get()
            ->map(fn (ChatThread $t) => [
                'id' => $t->id,
                'title' => $this->summarizeTitle($t->title ?: 'Chat'),
                'last_message_at' => $t->last_message_at?->toIso8601String(),
                'preview' => $t->messages->first()?->content,
            ]);

        return response()->json(['success' => true, 'threads' => $threads]);
    }

    public function show(Request $request, ChatThread $thread)
    {
        $this->authorizeThread($request, $thread);

        return response()->json([
            'success' => true,
            'thread' => [
                'id' => $thread->id,
                'title' => $thread->title,
                'messages' => $thread->messages()->get()->map(fn (ChatMessage $m) => [
                    'id' => $m->id,
                    'role' => $m->role,
                    'content' => $m->content,
                    'citations' => $m->citations,
                    'chart' => $m->chart,
                    'footnotes' => $m->footnotes,
                    'created_at' => $m->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $thread = ChatThread::create([
            'user_id' => $user->id,
            'title' => 'New chat',
            'last_message_at' => now(),
        ]);

        return response()->json(['success' => true, 'thread' => ['id' => $thread->id, 'title' => $thread->title]], 201);
    }

    public function destroy(Request $request, ChatThread $thread)
    {
        $this->authorizeThread($request, $thread);
        $thread->delete();

        return response()->json(['success' => true]);
    }

    public function send(Request $request, RagService $rag)
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'thread_id' => ['nullable', 'integer', 'exists:chat_threads,id'],
        ]);

        $thread = null;
        if (! empty($data['thread_id'])) {
            $thread = ChatThread::query()->findOrFail($data['thread_id']);
            $this->authorizeThread($request, $thread);
        } else {
            $thread = ChatThread::create([
                'user_id' => $user->id,
                'title' => $this->summarizeTitle($data['message']),
                'last_message_at' => now(),
            ]);
        }

        ChatMessage::create([
            'chat_thread_id' => $thread->id,
            'role' => 'user',
            'content' => $data['message'],
        ]);

        if (! $thread->title || $thread->title === 'New chat') {
            $thread->title = $this->summarizeTitle($data['message']);
        }

        $history = $thread->messages()
            ->orderBy('id')
            ->get()
            ->map(fn (ChatMessage $m) => ['role' => $m->role, 'content' => $m->content])
            ->all();
        // Drop the just-added user message from history for RAG (answer() will include current message)
        array_pop($history);

        $result = $rag->answer($data['message'], $history);

        ChatMessage::create([
            'chat_thread_id' => $thread->id,
            'role' => 'assistant',
            'content' => $result['response'],
            'citations' => $result['citations'],
            'chart' => $result['chart'] ?? null,
            'footnotes' => $result['footnotes'] ?? [],
        ]);

        $thread->update(['last_message_at' => now(), 'title' => $thread->title]);

        return response()->json([
            'success' => true,
            'thread_id' => $thread->id,
            'response' => $result['response'],
            'citations' => $result['citations'],
            'chart' => $result['chart'] ?? null,
            'footnotes' => $result['footnotes'] ?? [],
        ]);
    }

    private function authorizeThread(Request $request, ChatThread $thread): void
    {
        if ((int) $thread->user_id !== (int) $request->user()->id) {
            abort(404);
        }
    }

    /** Short drawer label: first 4 words of the opener. */
    private function summarizeTitle(string $text): string
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $slice = array_slice($words, 0, 4);

        return $slice === [] ? 'Chat' : implode(' ', $slice);
    }
}
