<?php

namespace App\Services;

use App\Models\ChatRoom;
use App\Models\RoomMessage;
use App\Models\RoomMessageMention;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NationalChatService
{
    public function __construct(
        private RoomMentionParser $mentions,
        private RagService $rag,
        private ExpoPushService $push,
    ) {}

    public function room(): ChatRoom
    {
        return ChatRoom::national();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messagesAfter(?int $afterId = null, int $limit = 50): array
    {
        $room = $this->room();
        $query = RoomMessage::query()
            ->with(['user.region', 'user.constituencyRef', 'mentions'])
            ->where('chat_room_id', $room->id)
            ->orderBy('id');

        if ($afterId !== null && $afterId > 0) {
            $query->where('id', '>', $afterId);
        } else {
            // Latest page: fetch newest then reverse for chronological UI.
            $rows = RoomMessage::query()
                ->with(['user.region', 'user.constituencyRef', 'mentions'])
                ->where('chat_room_id', $room->id)
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->sortBy('id')
                ->values();

            return $rows->map(fn (RoomMessage $m) => $this->serialize($m))->all();
        }

        return $query->limit($limit)->get()->map(fn (RoomMessage $m) => $this->serialize($m))->all();
    }

    /**
     * @return array{message: array<string, mixed>, ai_message: array<string, mixed>|null}
     */
    public function post(User $author, string $body): array
    {
        $body = trim($body);
        $room = $this->room();
        $parsed = $this->mentions->parse($body);

        $message = RoomMessage::create([
            'chat_room_id' => $room->id,
            'user_id' => $author->id,
            'kind' => RoomMessage::KIND_USER,
            'body' => $body,
        ]);

        foreach ($this->mentions->toMentionRows($parsed) as $row) {
            RoomMessageMention::create([
                'room_message_id' => $message->id,
                'mention_type' => $row['mention_type'],
                'constituency_id' => $row['constituency_id'],
            ]);
        }

        $message->load(['user.region', 'user.constituencyRef', 'mentions']);

        $this->notifyConstituencyMentions($author, $message, $parsed['constituencies']);

        // Return the user message immediately; Comrade AI replies after the HTTP response
        // so the mobile send button does not hang on RAG.
        if ($parsed['has_comrade']) {
            $authorId = (int) $author->id;
            $messageId = (int) $message->id;
            dispatch(function () use ($authorId, $messageId) {
                $author = User::query()->find($authorId);
                $userMessage = RoomMessage::query()->find($messageId);
                if (! $author || ! $userMessage) {
                    return;
                }
                app(self::class)->generateComradeReply($author, $userMessage);
            })->afterResponse();
        }

        return [
            'message' => $this->serialize($message),
            'ai_message' => null,
        ];
    }

    /**
     * @param  list<array{id: int, name: string}>  $constituencies
     */
    private function notifyConstituencyMentions(User $author, RoomMessage $message, array $constituencies): void
    {
        if ($constituencies === []) {
            return;
        }

        $ids = array_map(fn ($c) => $c['id'], $constituencies);
        $targets = User::query()
            ->where('role', User::ROLE_COMMUNICATOR)
            ->whereIn('constituency_id', $ids)
            ->where('id', '!=', $author->id)
            ->get();

        if ($targets->isEmpty()) {
            return;
        }

        $label = collect($constituencies)->pluck('name')->join(', ');
        $snippet = Str::limit($message->body, 120);

        $this->push->sendToUsers($targets, [
            'title' => 'Mentioned in National Chat',
            'body' => $snippet !== '' ? $snippet : ('@'.$label),
            'data' => [
                'type' => 'national_chat',
                'screen' => 'NationalChat',
                'message_id' => $message->id,
            ],
        ]);
    }

    public function generateComradeReply(User $author, RoomMessage $userMessage): ?array
    {
        return $this->replyAsComrade($author, $userMessage);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function replyAsComrade(User $author, RoomMessage $userMessage): ?array
    {
        $question = $this->mentions->stripComradeMention($userMessage->body);
        if ($question === '') {
            $question = 'Give a short on-message briefing tip for NDC communicators.';
        }

        $roomContext = $this->recentRoomTranscript($userMessage, 20);
        $prompt = $this->buildComradePrompt($author, $question, $roomContext);

        $citations = [];
        $footnotes = [];

        try {
            // RagService returns `response` (same shape as Ask / Briefing).
            $result = $this->rag->answer($prompt, []);
            $answer = trim((string) ($result['response'] ?? ''));
            $citations = is_array($result['citations'] ?? null) ? $result['citations'] : [];
            $footnotes = is_array($result['footnotes'] ?? null) ? $result['footnotes'] : [];
            if ($answer === '') {
                $answer = 'I could not form a reply from the digested sources just now. Try asking again with a bit more detail.';
            }
        } catch (\Throwable $e) {
            Log::warning('National chat @comrade failed', ['error' => $e->getMessage()]);
            $answer = 'I hit a snag pulling sources just now. Please try @comrade again in a moment.';
        }

        $ai = RoomMessage::create([
            'chat_room_id' => $userMessage->chat_room_id,
            'user_id' => null,
            'kind' => RoomMessage::KIND_AI,
            'body' => $answer,
            'citations' => $citations !== [] ? $citations : null,
            'footnotes' => $footnotes !== [] ? $footnotes : null,
        ]);
        $ai->load(['mentions']);

        // Notify all communicators including the asker (they may be backgrounded while polling stops).
        $recipients = User::query()
            ->where('role', User::ROLE_COMMUNICATOR)
            ->get();

        $this->push->sendToUsers($recipients, [
            'title' => 'Comrade AI',
            'body' => Str::limit($answer, 140),
            'data' => [
                'type' => 'national_chat',
                'screen' => 'NationalChat',
                'message_id' => $ai->id,
            ],
        ]);

        return $this->serialize($ai);
    }

    /**
     * Recent room lines so Comrade can verify / clarify claims made in the group.
     *
     * @return list<string>
     */
    private function recentRoomTranscript(RoomMessage $trigger, int $limit = 20): array
    {
        $rows = RoomMessage::query()
            ->with('user')
            ->where('chat_room_id', $trigger->chat_room_id)
            ->where('id', '<', $trigger->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->sortBy('id')
            ->values();

        $lines = [];
        foreach ($rows as $row) {
            if ($row->kind === RoomMessage::KIND_AI) {
                $who = 'Comrade AI';
            } else {
                $who = $this->firstName($row->user?->name);
            }
            $body = trim((string) $row->body);
            if ($body === '') {
                continue;
            }
            $lines[] = $who.': '.$body;
        }

        return $lines;
    }

    /**
     * @param  list<string>  $roomLines
     */
    private function buildComradePrompt(User $author, string $question, array $roomLines): string
    {
        $asker = $this->firstName($author->name);
        $transcript = $roomLines === []
            ? '(No earlier messages in this room.)'
            : implode("\n", $roomLines);

        return <<<PROMPT
You are answering inside the National Chatroom for NDC communicators.

Your job here:
- Brief, verify, or clarify what communicators are discussing.
- When they ask you to verify a claim, use the RECENT ROOM DISCUSSION plus SOURCE CONTEXT: say clearly whether it holds, correct it if wrong, and give a short radio/TV-ready clarification.
- Quote the disputed claim briefly before you rule on it.
- Stay concise and useful for the whole room — not a private chat.

RECENT ROOM DISCUSSION (oldest → newest):
{$transcript}

REQUEST FROM {$asker} (after @comrade):
{$question}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(RoomMessage $message): array
    {
        $author = null;
        if ($message->kind === RoomMessage::KIND_AI) {
            $author = [
                'id' => null,
                'first_name' => 'Comrade AI',
                'tag' => 'AI',
                'is_ai' => true,
            ];
        } elseif ($message->user) {
            $author = [
                'id' => $message->user->id,
                'first_name' => $this->firstName($message->user->name),
                'tag' => $this->authorTag($message->user),
                'is_ai' => false,
            ];
        }

        return [
            'id' => $message->id,
            'kind' => $message->kind,
            'body' => $message->body,
            'created_at' => $message->created_at?->toIso8601String(),
            'author' => $author,
            'citations' => $message->kind === RoomMessage::KIND_AI
                ? array_values($message->citations ?? [])
                : [],
            'footnotes' => $message->kind === RoomMessage::KIND_AI
                ? array_values($message->footnotes ?? [])
                : [],
            'mentions' => $message->mentions->map(fn (RoomMessageMention $m) => [
                'type' => $m->mention_type,
                'constituency_id' => $m->constituency_id,
            ])->values()->all(),
        ];
    }

    private function firstName(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];

        return $parts[0] !== '' ? $parts[0] : 'Communicator';
    }

    private function authorTag(User $user): string
    {
        if ($user->comms_level === 'national') {
            return 'National';
        }

        $user->loadMissing('constituencyRef');

        return $user->constituencyRef?->name
            ?? (string) ($user->constituency ?: 'Constituency');
    }
}
