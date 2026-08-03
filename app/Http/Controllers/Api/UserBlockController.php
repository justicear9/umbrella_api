<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserBlockController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isCommunicator()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $rows = UserBlock::query()
            ->with('blocked:id,name,party_id')
            ->where('blocker_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (UserBlock $b) => [
                'id' => $b->id,
                'blocked_id' => $b->blocked_id,
                'name' => $b->blocked?->name,
                'party_id' => $b->blocked?->party_id,
                'created_at' => $b->created_at?->toIso8601String(),
            ])
            ->values();

        return response()->json(['success' => true, 'blocked' => $rows]);
    }

    public function store(Request $request, User $target)
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isCommunicator()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        if (! $target->isCommunicator()) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        if ($target->id === $user->id) {
            return response()->json(['success' => false, 'message' => 'You cannot block yourself.'], 422);
        }

        UserBlock::query()->firstOrCreate([
            'blocker_id' => $user->id,
            'blocked_id' => $target->id,
        ]);

        Log::info('User blocked', [
            'blocker_id' => $user->id,
            'blocked_id' => $target->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User blocked. Their messages are hidden from your National Chat feed.',
        ]);
    }

    public function destroy(Request $request, User $target)
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isCommunicator()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        UserBlock::query()
            ->where('blocker_id', $user->id)
            ->where('blocked_id', $target->id)
            ->delete();

        return response()->json(['success' => true]);
    }
}
