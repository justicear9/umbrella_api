<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'party_id' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('party_id', trim($data['party_id']))
            ->where('role', User::ROLE_COMMUNICATOR)
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'party_id' => ['Invalid Party ID or password.'],
            ]);
        }

        $token = $user->issueApiToken();

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user->toPublicArray(),
        ]);
    }

    public function me(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => $user->toPublicArray(),
        ]);
    }

    public function logout(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $user->clearApiToken();

        return response()->json(['success' => true]);
    }
}
