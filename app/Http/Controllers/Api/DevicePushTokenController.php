<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DevicePushToken;
use Illuminate\Http\Request;

class DevicePushTokenController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'in:ios,android,unknown'],
        ]);

        $token = DevicePushToken::query()->updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'] ?? 'unknown',
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['success' => true, 'id' => $token->id]);
    }
}
