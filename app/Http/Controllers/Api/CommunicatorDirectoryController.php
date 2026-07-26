<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class CommunicatorDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $viewer = $request->user();
        if (! $viewer || ! $viewer->isCommunicator()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $q = trim((string) $request->query('q', ''));
        $perPage = min(50, max(1, $request->integer('per_page', 30)));

        $query = User::query()
            ->with(['region:id,name', 'constituencyRef:id,name,region_id'])
            ->where('role', User::ROLE_COMMUNICATOR)
            ->orderBy('name');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('party_id', 'like', $like)
                    ->orWhere('constituency', 'like', $like)
                    ->orWhereHas('constituencyRef', fn ($c) => $c->where('name', 'like', $like))
                    ->orWhereHas('region', fn ($r) => $r->where('name', 'like', $like));
            });
        }

        $page = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'communicators' => collect($page->items())->map(fn (User $u) => $u->toDirectoryArray())->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, User $user)
    {
        $viewer = $request->user();
        if (! $viewer || ! $viewer->isCommunicator()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        if (! $user->isCommunicator()) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        return response()->json([
            'success' => true,
            'communicator' => $user->toPeerPublicArray(),
        ]);
    }
}
