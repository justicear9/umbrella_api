<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Region;

class GeographyController extends Controller
{
    public function index()
    {
        $regions = Region::query()
            ->with(['constituencies:id,region_id,name'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'regions' => $regions->map(fn (Region $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'constituencies' => $r->constituencies->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                ])->values(),
            ]),
        ]);
    }
}
