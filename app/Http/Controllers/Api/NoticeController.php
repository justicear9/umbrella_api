<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\NoticeUser;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $rows = NoticeUser::query()
            ->where('user_id', $user->id)
            ->with(['notice' => fn ($q) => $q->where('status', 'published')])
            ->latest('id')
            ->limit(100)
            ->get()
            ->filter(fn (NoticeUser $nu) => $nu->notice !== null)
            ->values();

        return response()->json([
            'success' => true,
            'notices' => $rows->map(fn (NoticeUser $nu) => $this->serialize($nu))->all(),
        ]);
    }

    public function unreadCount(Request $request)
    {
        $count = NoticeUser::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->whereHas('notice', fn ($q) => $q->where('status', 'published'))
            ->count();

        return response()->json(['success' => true, 'count' => $count]);
    }

    public function show(Request $request, Notice $notice)
    {
        $nu = NoticeUser::query()
            ->where('notice_id', $notice->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($notice->status !== 'published') {
            abort(404);
        }

        if (! $nu->read_at) {
            $nu->update(['read_at' => now()]);
        }

        $nu->setRelation('notice', $notice);

        return response()->json([
            'success' => true,
            'notice' => $this->serialize($nu),
        ]);
    }

    public function markRead(Request $request, Notice $notice)
    {
        $nu = NoticeUser::query()
            ->where('notice_id', $notice->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (! $nu->read_at) {
            $nu->update(['read_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request)
    {
        $updated = NoticeUser::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->whereHas('notice', fn ($q) => $q->where('status', 'published'))
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'updated' => $updated,
        ]);
    }

    private function serialize(NoticeUser $nu): array
    {
        $n = $nu->notice;

        return [
            'id' => $n->id,
            'title' => $n->title,
            'body' => $n->body,
            'link_url' => $n->link_url,
            'priority' => $n->priority,
            'published_at' => $n->published_at?->toIso8601String(),
            'read_at' => $nu->read_at?->toIso8601String(),
            'unread' => $nu->read_at === null,
        ];
    }
}
