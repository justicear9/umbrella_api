<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Briefing;
use App\Services\RagService;
use Illuminate\Http\Request;

class BriefingController extends Controller
{
    public function categories()
    {
        return response()->json([
            'success' => true,
            'categories' => config('ndc.categories'),
            'outing_types' => config('ndc.outing_types'),
            'difficulties' => config('ndc.difficulties'),
        ]);
    }

    public function index(Request $request)
    {
        $category = $request->query('category');

        $query = Briefing::published()->with('document:id,title')->latest('published_at');

        if ($category && $category !== 'All') {
            $query->where('category', $category);
        }

        $briefings = $query->get()->map(fn (Briefing $b) => $this->serialize($b));

        return response()->json([
            'success' => true,
            'briefings' => $briefings,
        ]);
    }

    public function show(Briefing $briefing)
    {
        if (! $briefing->published_at) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $briefing->load('document:id,title');

        return response()->json([
            'success' => true,
            'briefing' => $this->serialize($briefing, true),
        ]);
    }

    public function chat(Request $request, RagService $rag)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'history' => ['nullable', 'array'],
            'history.*.role' => ['required_with:history', 'string'],
            'history.*.content' => ['required_with:history', 'string'],
        ]);

        $result = $rag->answer($data['message'], $data['history'] ?? []);

        return response()->json([
            'success' => true,
            'response' => $result['response'],
            'citations' => $result['citations'],
            'chart' => $result['chart'] ?? null,
            'footnotes' => $result['footnotes'] ?? [],
        ]);
    }

    private function serialize(Briefing $b, bool $full = false): array
    {
        $base = [
            'id' => $b->id,
            'title' => $b->title,
            'category' => $b->category,
            'summary' => $b->summary,
            'talking_points' => $b->talking_points ?? [],
            'key_stats' => $b->key_stats ?? [],
            'watch_outs' => $b->watch_outs ?? [],
            'source' => $b->document?->title,
            'published_at' => optional($b->published_at)->toIso8601String(),
        ];

        if ($full) {
            $base['citations'] = $b->citations ?? [];
        }

        return $base;
    }
}
