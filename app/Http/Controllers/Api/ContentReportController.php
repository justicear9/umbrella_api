<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use App\Models\RoomMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ContentReportController extends Controller
{
    public function store(Request $request, RoomMessage $message)
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isCommunicator()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        if ($message->trashed()) {
            return response()->json(['success' => false, 'message' => 'Message already removed.'], 422);
        }

        if ($message->kind === RoomMessage::KIND_AI) {
            return response()->json(['success' => false, 'message' => 'AI messages cannot be reported.'], 422);
        }

        if ((int) $message->user_id === (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'You cannot report your own message.'], 422);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', Rule::in(array_keys(ContentReport::REASONS))],
        ]);

        $report = ContentReport::query()->updateOrCreate(
            [
                'reporter_id' => $user->id,
                'room_message_id' => $message->id,
                'status' => ContentReport::STATUS_OPEN,
            ],
            [
                'reported_user_id' => $message->user_id,
                'reason' => $data['reason'],
            ]
        );

        Log::warning('Content report filed', [
            'report_id' => $report->id,
            'reporter_id' => $user->id,
            'message_id' => $message->id,
            'reported_user_id' => $message->user_id,
            'reason' => $data['reason'],
            'reason_label' => ContentReport::reasonLabel($data['reason']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report submitted. Moderators will review within 24 hours.',
            'report_id' => $report->id,
            'reason' => $data['reason'],
        ]);
    }
}
