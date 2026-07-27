<?php

namespace App\Services;

use App\Models\Briefing;
use App\Models\PressPrepSession;
use App\Models\PressPrepTurn;
use Illuminate\Support\Arr;

class PressPrepService
{
    public function __construct(
        private OpenAIService $openai,
        private RagService $rag,
    ) {}

    public function start(PressPrepSession $session): array
    {
        $pack = $this->buildBriefingPack($session);
        $session->update([
            'status' => 'live',
            'current_question' => 1,
            'briefing_pack' => $pack,
        ]);

        $turn = $this->generateQuestion($session, isOpening: true);

        return [
            'session' => $session->fresh('turns'),
            'turn' => $turn,
            'briefing_pack' => $pack,
        ];
    }

    public function answer(PressPrepSession $session, string $userAnswer): array
    {
        $turn = $session->turns()
            ->whereNull('user_answer')
            ->whereNotNull('question')
            ->orderByDesc('turn_index')
            ->first();

        if (! $turn) {
            throw new \RuntimeException('No open question to answer.');
        }

        $followUpsUsed = (int) $session->turns()->where('is_follow_up', true)->count();
        $followUpsRemaining = max(0, 2 - $followUpsUsed);
        $isLastMainQuestion = (int) $session->current_question >= (int) $session->question_count;
        $needsNextMain = ! $isLastMainQuestion;

        // Live path: briefing pack only (no RAG embed) + one LLM call for score + next line.
        $pack = collect($session->briefing_pack ?? [])
            ->take(5)
            ->map(fn ($b) => [
                'category' => $b['category'] ?? '',
                'title' => $b['title'] ?? '',
                'summary' => mb_substr((string) ($b['summary'] ?? ''), 0, 280),
                'talking_points' => array_slice(array_values($b['talking_points'] ?? []), 0, 3),
            ])
            ->all();

        $outingLabels = config('ndc.outing_types');
        $diffLabels = config('ndc.difficulties');

        $eval = $this->openai->chatJson([
            [
                'role' => 'system',
                'content' => <<<'PROMPT'
You are a Ghanaian journalist + NDC press coach running a live interview.
Return STRICT JSON:
{
  "coach_note": "1 blunt practical sentence for debrief",
  "accuracy": 1-10,
  "message_discipline": 1-10,
  "composure": 1-10,
  "landmines": ["optional short items"],
  "best_line": "best reusable line from their answer or empty",
  "ask_follow_up": true/false,
  "follow_up": "gotcha follow-up if ask_follow_up else empty",
  "next_question": "next main interview question if needed else empty",
  "interviewer_note": "optional short in-character beat before next_question or empty"
}
Speed rules:
- Keep coach fields short. No model answer.
- ask_follow_up only if evasive / invents facts / critical hole; Soft => never; if follow_ups_remaining is 0 => never.
- If ask_follow_up is false and needs_next_main is true, you MUST fill next_question (specific, grounded in BRIEFINGS, suited to outing/difficulty). Do not invent statistics in the question.
- If needs_next_main is false, leave next_question empty.
PROMPT
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'outing_type' => $outingLabels[$session->outing_type] ?? $session->outing_type,
                    'difficulty' => $diffLabels[$session->difficulty] ?? $session->difficulty,
                    'topics' => $session->topics,
                    'hot_issues' => $session->hot_issues,
                    'question' => $turn->question,
                    'user_answer' => $userAnswer,
                    'briefings' => $pack,
                    'follow_ups_remaining' => $followUpsRemaining,
                    'needs_next_main' => $needsNextMain,
                    'question_number' => $session->current_question,
                    'total_questions' => $session->question_count,
                    'prior_questions' => $session->turns()->pluck('question')->filter()->values()->take(12),
                ], JSON_UNESCAPED_UNICODE),
            ],
        ], ['max_tokens' => 700, 'temperature' => 0.35]);

        $turn->update([
            'user_answer' => $userAnswer,
            'coach_note' => (string) ($eval['coach_note'] ?? ''),
            'model_answer' => '',
            'follow_up' => ! empty($eval['ask_follow_up']) ? (string) ($eval['follow_up'] ?? '') : null,
            'score_notes' => [
                'accuracy' => (int) ($eval['accuracy'] ?? 5),
                'message_discipline' => (int) ($eval['message_discipline'] ?? 5),
                'composure' => (int) ($eval['composure'] ?? 5),
                'landmines' => array_values($eval['landmines'] ?? []),
                'best_line' => (string) ($eval['best_line'] ?? ''),
            ],
        ]);

        $nextTurn = null;
        $completed = false;
        $closing = null;

        $canFollowUp = $followUpsRemaining > 0
            && $session->difficulty !== 'soft'
            && ! empty($eval['ask_follow_up'])
            && trim((string) ($eval['follow_up'] ?? '')) !== '';

        if ($canFollowUp) {
            $nextIndex = ((int) $session->turns()->max('turn_index')) + 1;
            $nextTurn = PressPrepTurn::create([
                'press_prep_session_id' => $session->id,
                'turn_index' => $nextIndex,
                'role' => 'interviewer',
                'question' => trim((string) $eval['follow_up']),
                'is_follow_up' => true,
            ]);
        } elseif ($isLastMainQuestion) {
            $completed = true;
            $closing = $this->pickClosing($session);
            $session->update(['status' => 'completed']);
        } else {
            $session->increment('current_question');
            $session->refresh();

            $question = trim((string) ($eval['next_question'] ?? ''));
            $note = trim((string) ($eval['interviewer_note'] ?? ''));
            if ($question === '') {
                // Rare fallback — keep interview moving without a second planned RAG round-trip.
                $question = 'On '.$this->topicFallback($session).', what is the government\'s concrete response right now?';
            }
            if ($note !== '') {
                $question = $note."\n\n".$question;
            }

            $nextIndex = ((int) $session->turns()->max('turn_index')) + 1;
            $nextTurn = PressPrepTurn::create([
                'press_prep_session_id' => $session->id,
                'turn_index' => $nextIndex,
                'role' => 'interviewer',
                'question' => $question,
                'is_follow_up' => false,
            ]);
        }

        return [
            'session' => $session->fresh('turns'),
            'answered_turn' => $turn->fresh(),
            'next_turn' => $nextTurn,
            'completed' => $completed,
            'closing' => $closing,
        ];
    }

    private function topicFallback(PressPrepSession $session): string
    {
        $topics = array_values(array_filter($session->topics ?? []));

        return $topics[0] ?? 'this issue';
    }

    public function hint(PressPrepSession $session): array
    {
        $turn = $session->turns()
            ->whereNull('user_answer')
            ->whereNotNull('question')
            ->orderByDesc('turn_index')
            ->firstOrFail();

        if ($turn->hint_text) {
            return ['hint' => $turn->hint_text, 'turn' => $turn];
        }

        $hits = $this->rag->retrieve($turn->question, 4);
        $context = collect($hits)->map(fn ($h) => $h['chunk']->content)->implode("\n\n");

        $hint = $this->openai->chat([
            [
                'role' => 'system',
                'content' => <<<'PROMPT'
You are a press coach for NDC communicators in Ghana.
Give SHORT rehearsal hints only — never write the answer for them.

Rules:
- 3 to 5 bullet points max
- Reminders, angles, and traps to avoid — not a scripted reply
- If SOURCE has a useful fact/figure they should recall, name it briefly; otherwise say "stay qualitative"
- Include one message-discipline tip (bridge, don't repeat the trap frame, land on the government action)
- No full sentences that could be read aloud as the answer
- Under 70 words total
- Plain markdown bullets only
PROMPT,
            ],
            [
                'role' => 'user',
                'content' => "QUESTION:\n{$turn->question}\n\nSOURCE:\n".mb_substr($context, 0, 8000),
            ],
        ], ['max_tokens' => 280, 'temperature' => 0.35]);

        $turn->update(['hint_text' => $hint]);

        return ['hint' => $hint, 'turn' => $turn->fresh()];
    }

    public function debrief(PressPrepSession $session): array
    {
        // Natural finish sets status to completed before debrief; End early leaves it live.
        $endedEarly = in_array($session->status, ['live', 'setup'], true);

        $answered = $session->turns()->whereNotNull('user_answer')->orderBy('turn_index')->get();
        $answeredCount = $answered->count();
        $planned = (int) $session->question_count;

        // Model answers are skipped during live Q&A for speed — fill them here.
        $this->fillMissingModelAnswers($session, $answered);
        $answered = $session->turns()->whereNotNull('user_answer')->orderBy('turn_index')->get();

        $scores = $answeredCount === 0
            ? ['accuracy' => 0, 'message_discipline' => 0, 'composure' => 0]
            : [
                'accuracy' => (int) round($answered->avg(fn ($t) => Arr::get($t->score_notes, 'accuracy', 5)) ?: 5),
                'message_discipline' => (int) round($answered->avg(fn ($t) => Arr::get($t->score_notes, 'message_discipline', 5)) ?: 5),
                'composure' => (int) round($answered->avg(fn ($t) => Arr::get($t->score_notes, 'composure', 5)) ?: 5),
            ];

        $landmines = $answered
            ->flatMap(fn ($t) => Arr::get($t->score_notes, 'landmines', []))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $bestLines = $answered
            ->map(fn ($t) => Arr::get($t->score_notes, 'best_line'))
            ->filter()
            ->values()
            ->all();

        $onePager = $answered->map(fn (PressPrepTurn $t) => [
            'question' => $t->question,
            'your_answer' => $t->user_answer,
            'model_answer' => $t->model_answer,
            'coach_note' => $t->coach_note,
        ])->all();

        $summaryPrompt = $endedEarly
            ? 'Summarize this NDC Press Prep session for the communicator in 4-6 punchy bullets. Be direct. Ghana media context. IMPORTANT: they ended the session early before finishing all planned questions — call that out clearly in the first bullet.'
            : 'Summarize this NDC Press Prep session for the communicator in 4-6 punchy bullets. Be direct. Ghana media context.';

        $summary = $this->openai->chat([
            [
                'role' => 'system',
                'content' => $summaryPrompt,
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'ended_early' => $endedEarly,
                    'answered_count' => $answeredCount,
                    'planned_questions' => $planned,
                    'scores' => $scores,
                    'landmines' => $landmines,
                    'best_lines' => $bestLines,
                    'turns' => $onePager,
                ], JSON_PRETTY_PRINT),
            ],
        ], ['max_tokens' => 700, 'temperature' => 0.3]);

        $overall = $answeredCount === 0
            ? 0
            : (int) round((array_sum($scores) / 30) * 100);

        $debrief = [
            'scores' => $scores,
            'overall' => $overall,
            'ended_early' => $endedEarly,
            'answered_count' => $answeredCount,
            'planned_questions' => $planned,
            'landmines' => $landmines,
            'best_lines' => $bestLines,
            'summary' => $summary,
            'one_pager' => $onePager,
            // Stops the API from re-running model generation on every open if some stay blank.
            'model_answers_generated' => true,
        ];

        $session->update([
            'status' => $endedEarly ? 'abandoned' : 'completed',
            'debrief' => $debrief,
        ]);

        return $debrief;
    }

    /**
     * Write model answers onto answered turns that still lack them (live path leaves them blank).
     *
     * @param  \Illuminate\Support\Collection<int, PressPrepTurn>  $answered
     */
    private function fillMissingModelAnswers(PressPrepSession $session, $answered): void
    {
        $needs = $answered
            ->filter(fn (PressPrepTurn $t) => trim((string) ($t->model_answer ?? '')) === '')
            ->values();

        if ($needs->isEmpty()) {
            return;
        }

        $pack = collect($session->briefing_pack ?? [])
            ->take(5)
            ->map(fn ($b) => [
                'category' => $b['category'] ?? '',
                'title' => $b['title'] ?? '',
                'summary' => mb_substr((string) ($b['summary'] ?? ''), 0, 280),
                'talking_points' => array_slice(array_values($b['talking_points'] ?? []), 0, 4),
            ])
            ->all();

        $payload = $needs->map(fn (PressPrepTurn $t) => [
            'turn_id' => $t->id,
            'question' => $t->question,
            'user_answer' => $t->user_answer,
            'coach_note' => $t->coach_note,
        ])->all();

        $result = $this->openai->chatJson([
            [
                'role' => 'system',
                'content' => <<<'PROMPT'
You are an NDC press coach writing MODEL answers for a Ghana communicator debrief.
Return STRICT JSON:
{
  "answers": [
    { "turn_id": 123, "model_answer": "2-4 short spoken sentences they could have used" }
  ]
}
Rules:
- One entry per turn_id provided.
- Ground claims in BRIEFINGS only — no invented statistics.
- Strong message discipline: bridge, land on government action, avoid repeating hostile frames.
- Ghana media tone: clear, confident, plain English.
- Do not lecture; write the answer they should have given on air.
PROMPT
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'briefings' => $pack,
                    'turns' => $payload,
                ], JSON_UNESCAPED_UNICODE),
            ],
        ], ['max_tokens' => 2200, 'temperature' => 0.35]);

        $byId = [];
        foreach ($result['answers'] ?? [] as $row) {
            $id = (int) ($row['turn_id'] ?? 0);
            $text = trim((string) ($row['model_answer'] ?? ''));
            if ($id > 0 && $text !== '') {
                $byId[$id] = $text;
            }
        }

        foreach ($needs as $turn) {
            $text = $byId[$turn->id] ?? null;
            if ($text) {
                $turn->update(['model_answer' => $text]);
            }
        }
    }

    private function buildBriefingPack(PressPrepSession $session): array
    {
        $topics = $session->topics ?? [];

        return Briefing::published()
            ->when($topics !== [], fn ($q) => $q->whereIn('category', $topics))
            ->latest('published_at')
            ->limit(6)
            ->get()
            ->map(fn (Briefing $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'category' => $b->category,
                'summary' => $b->summary,
                'talking_points' => $b->talking_points,
                'key_stats' => $b->key_stats,
                'watch_outs' => $b->watch_outs,
            ])
            ->all();
    }

    private function generateQuestion(PressPrepSession $session, bool $isOpening): PressPrepTurn
    {
        // Opening uses briefing pack only — skip RAG embedding latency on studio boot.
        $pack = collect($session->briefing_pack ?? [])
            ->take(6)
            ->map(fn ($b) => [
                'category' => $b['category'] ?? '',
                'title' => $b['title'] ?? '',
                'summary' => mb_substr((string) ($b['summary'] ?? ''), 0, 320),
                'talking_points' => array_slice(array_values($b['talking_points'] ?? []), 0, 4),
                'watch_outs' => array_slice(array_values($b['watch_outs'] ?? []), 0, 2),
            ])
            ->all();

        $outingLabels = config('ndc.outing_types');
        $diffLabels = config('ndc.difficulties');

        $result = $this->openai->chatJson([
            [
                'role' => 'system',
                'content' => <<<'PROMPT'
You are a Ghanaian journalist interviewing an NDC communicator.
Return JSON: {"question":"...","interviewer_note":"short in-character opener or empty"}
Make the question specific, grounded in BRIEFINGS, and suited to outing_type + difficulty.
Hostile = sharper, loaded; Soft = curious; Standard = firm but fair.
No invented statistics in the question — challenge with frames, not fake numbers.
PROMPT
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'outing_type' => $outingLabels[$session->outing_type] ?? $session->outing_type,
                    'difficulty' => $diffLabels[$session->difficulty] ?? $session->difficulty,
                    'topics' => $session->topics,
                    'hot_issues' => $session->hot_issues,
                    'question_number' => $session->current_question,
                    'total_questions' => $session->question_count,
                    'is_opening' => $isOpening,
                    'prior_questions' => $session->turns()->pluck('question')->filter()->values(),
                    'briefings' => $pack,
                ], JSON_UNESCAPED_UNICODE),
            ],
        ], ['max_tokens' => 350, 'temperature' => 0.5]);

        $question = (string) ($result['question'] ?? 'What is the government\'s response on this issue?');
        $note = (string) ($result['interviewer_note'] ?? '');
        if ($note !== '') {
            $question = $note."\n\n".$question;
        }

        $nextIndex = ((int) $session->turns()->max('turn_index')) + 1;

        return PressPrepTurn::create([
            'press_prep_session_id' => $session->id,
            'turn_index' => $nextIndex,
            'role' => 'interviewer',
            'question' => $question,
            'is_follow_up' => false,
        ]);
    }

    /**
     * Short in-character wrap-up from the interviewer after the final answer.
     */
    private function pickClosing(PressPrepSession $session): string
    {
        $outing = $session->outing_type;
        $closers = match ($outing) {
            'tv' => [
                'Thank you for coming on the programme this evening, and thank you for taking these questions. We appreciate you addressing these concerns for our viewers.',
                'Thanks for joining us in studio. Thank you for answering the questions and for speaking to the issues Ghanaians are raising.',
                'We will leave it there. Thank you for coming on, thank you for your answers, and thank you for addressing these concerns on national television.',
                'That is all the time we have. Thank you for coming through, and thank you for engaging these questions so directly.',
            ],
            'radio' => [
                'Thank you for coming on the show, and thank you for taking the callers\' concerns. We appreciate you addressing these issues for our listeners.',
                'We will wrap there. Thanks for joining us on air, thank you for answering the questions, and thank you for speaking to what people are asking.',
                'Thank you for coming through this morning. Thank you for your answers and for addressing these concerns on the programme.',
                'That is our time. Thank you for coming on, and thank you for engaging these questions for our audience.',
            ],
            'press_conference' => [
                'Thank you. That will be all for now. Thank you for taking the questions and for addressing these concerns.',
                'We will stop there. Thank you for coming to the podium, thank you for your answers, and thank you for speaking to these issues.',
                'Thank you for your time. Thank you for answering the questions and for addressing the concerns raised by the press.',
                'That concludes this round. Thank you for coming forward and for engaging these questions.',
            ],
            'town_hall' => [
                'Thank you for being here with the community. Thank you for answering the questions and for addressing these concerns tonight.',
                'We will close there. Thanks for coming to the town hall, thank you for your answers, and thank you for speaking to what residents raised.',
                'Thank you for joining us. Thank you for taking these questions and for addressing the concerns of the people here.',
                'That is all for this evening. Thank you for coming, and thank you for engaging these issues so openly.',
            ],
            'social_ambush' => [
                'Alright, thank you. Thanks for stopping to answer, and thank you for addressing these concerns on camera.',
                'We will leave it there. Thank you for taking the questions and for speaking to the issue.',
                'Thanks for your time. Thank you for answering, and thank you for addressing what people are asking online.',
                'Okay, that is it from us. Thank you for engaging — appreciate you addressing these concerns.',
            ],
            default => [
                'Thank you for coming, thank you for answering the questions, and thank you for addressing these concerns.',
                'We will end there. Thank you for your time, thank you for your answers, and thank you for speaking to these issues.',
                'Thank you for joining us. Thank you for taking the questions and for addressing the concerns raised today.',
                'That wraps our session. Thank you for coming through and for engaging these questions so directly.',
                'Thank you. Appreciate you taking the time to answer and to address these concerns.',
            ],
        };

        return $closers[array_rand($closers)];
    }
}
