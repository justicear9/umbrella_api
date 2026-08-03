<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class TermsController extends Controller
{
    public function status(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'terms_accepted' => $user->terms_accepted_at !== null,
            'terms_accepted_at' => $user->terms_accepted_at?->toIso8601String(),
            'terms_url' => url('/terms'),
            'privacy_url' => url('/privacy'),
            'support_url' => url('/support'),
        ]);
    }

    public function accept(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->terms_accepted_at === null) {
            $user->forceFill(['terms_accepted_at' => now()])->save();
        }

        return response()->json([
            'success' => true,
            'user' => $user->fresh()->toPublicArray(),
        ]);
    }
}
