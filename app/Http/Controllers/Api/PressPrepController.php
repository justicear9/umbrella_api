<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PressPrepSession;
use App\Services\GeminiTtsService;
use App\Services\PressPrepService;
use App\Services\PressPrepTtsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PressPrepController extends Controller
{
    public function mine(Request $request)
    {
        $sessions = PressPrepSession::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(40)
            ->get()
            ->map(fn (PressPrepSession $s) => $this->serializeSession($s));

        return response()->json(['success' => true, 'sessions' => $sessions]);
    }

    public function store(Request $request)
    {
        $categoryQueries = collect(config('ndc.categories'))
            ->pluck('query')
            ->filter()
            ->values()
            ->all();

        $data = $request->validate([
            'outing_type' => ['required', Rule::in(array_keys(config('ndc.outing_types')))],
            'difficulty' => ['required', Rule::in(array_keys(config('ndc.difficulties')))],
            'interview_mode' => ['required', Rule::in(['text', 'voice'])],
            'voice_preset' => ['nullable', Rule::in(array_keys(GeminiTtsService::VOICE_PRESETS))],
            'topics' => ['required', 'array', 'min:1'],
            'topics.*' => ['string', Rule::in($categoryQueries)],
            'hot_issues' => ['nullable', 'string', 'max:1000'],
            'question_count' => ['required', Rule::in([5, 10, 15])],
        ]);

        $mode = $data['interview_mode'];
        $voice = $mode === 'voice'
            ? ($data['voice_preset'] ?? GeminiTtsService::VOICE_GHANAIAN)
            : null;

        $session = PressPrepSession::create([
            'user_id' => $request->user()->id,
            'outing_type' => $data['outing_type'],
            'difficulty' => $data['difficulty'],
            'interview_mode' => $mode,
            'voice_preset' => $voice,
            'topics' => $data['topics'],
            'hot_issues' => $data['hot_issues'] ?? null,
            'question_count' => $data['question_count'],
            'status' => 'setup',
            'current_question' => 0,
        ]);

        return response()->json([
            'success' => true,
            'session' => $session,
        ], 201);
    }

    public function show(Request $request, PressPrepSession $session)
    {
        $this->authorizeSession($request, $session);

        return response()->json([
            'success' => true,
            'session' => $session->load('turns'),
        ]);
    }

    public function start(Request $request, PressPrepSession $session, PressPrepService $service, PressPrepTtsService $tts)
    {
        $this->authorizeSession($request, $session);
        set_time_limit(300);
        $result = $service->start($session);
        $audio = $this->maybeSpeak($session, $tts, $result['turn']->id, (string) ($result['turn']->question ?? ''));

        return response()->json([
            'success' => true,
            ...$result,
            'audio_url' => $audio['url'] ?? null,
            'audio_engine' => $audio['engine'] ?? null,
        ]);
    }

    public function answer(Request $request, PressPrepSession $session, PressPrepService $service, PressPrepTtsService $tts)
    {
        $this->authorizeSession($request, $session);
        $data = $request->validate([
            'answer' => ['required', 'string', 'max:5000'],
        ]);

        set_time_limit(120);
        $result = $service->answer($session, $data['answer']);
        $audio = null;

        if (! empty($result['next_turn']?->question)) {
            $audio = $this->maybeSpeak(
                $session,
                $tts,
                $result['next_turn']->id,
                (string) $result['next_turn']->question
            );
        } elseif (! empty($result['closing'])) {
            $audio = $this->maybeSpeak(
                $session,
                $tts,
                'closing_'.$session->id,
                (string) $result['closing']
            );
        }

        return response()->json([
            'success' => true,
            ...$result,
            'audio_url' => $audio['url'] ?? null,
            'audio_engine' => $audio['engine'] ?? null,
            'voice_preset' => $session->voice_preset,
            'interview_mode' => $session->interview_mode,
        ]);
    }

    public function hint(Request $request, PressPrepSession $session, PressPrepService $service)
    {
        $this->authorizeSession($request, $session);
        set_time_limit(120);
        $result = $service->hint($session);

        return response()->json([
            'success' => true,
            ...$result,
        ]);
    }

    public function debrief(Request $request, PressPrepSession $session, PressPrepService $service)
    {
        $this->authorizeSession($request, $session);
        set_time_limit(180);

        $force = $request->boolean('force');
        if ($force) {
            $session->update(['debrief' => null]);
            $session->refresh();
        }

        $cached = (! $force && is_array($session->debrief)) ? $session->debrief : null;
        // Older cached debriefs often lack model answers (live path left them blank).
        $needsRebuild = $cached === null || $this->debriefMissingModelAnswers($cached);
        $debrief = $needsRebuild ? $service->debrief($session) : $cached;

        return response()->json([
            'success' => true,
            'debrief' => $debrief,
            'session' => $session->fresh('turns'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $debrief
     */
    private function debriefMissingModelAnswers(array $debrief): bool
    {
        // Already attempted generation (even if some rows stayed blank).
        if (! empty($debrief['model_answers_generated'])) {
            return false;
        }

        $rows = $debrief['one_pager'] ?? null;
        if (! is_array($rows) || $rows === []) {
            return false;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (trim((string) ($row['model_answer'] ?? '')) === '') {
                return true;
            }
        }

        return false;
    }

    private function authorizeSession(Request $request, PressPrepSession $session): void
    {
        if ((int) $session->user_id !== (int) $request->user()->id) {
            abort(404);
        }
    }

    private function serializeSession(PressPrepSession $s): array
    {
        $overall = $s->debrief['overall'] ?? null;

        return [
            'id' => $s->id,
            'outing_type' => $s->outing_type,
            'difficulty' => $s->difficulty,
            'status' => $s->status,
            'question_count' => $s->question_count,
            'current_question' => $s->current_question,
            'assigned' => $s->assigned_at !== null || $s->assigned_by !== null,
            'assignment_note' => $s->assignment_note,
            'assigned_at' => $s->assigned_at,
            'interview_mode' => $s->interview_mode,
            'voice_preset' => $s->voice_preset,
            'topics' => $s->topics ?? [],
            'hot_issues' => $s->hot_issues,
            'overall' => $overall,
            'created_at' => $s->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{url: string, engine: string, cached: bool}|null
     */
    private function maybeSpeak(PressPrepSession $session, PressPrepTtsService $tts, int|string $_turnId, string $question): ?array
    {
        if ($session->interview_mode !== 'voice' || trim($question) === '') {
            return null;
        }

        $voice = $session->voice_preset ?? GeminiTtsService::VOICE_GHANAIAN;
        $cacheKey = 'qvoice_'.substr(hash('sha256', mb_strtolower(trim($question)).'|'.$voice), 0, 28);

        return $tts->speak($cacheKey, $question, $voice);
    }
}
