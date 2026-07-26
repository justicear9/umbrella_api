<?php

namespace App\Services;

use App\Models\DevicePushToken;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    /**
     * @param  Collection<int, User>|list<User>  $users
     * @param  array{title: string, body: string, data?: array<string, mixed>}  $message
     */
    public function sendToUsers(Collection|array $users, array $message): void
    {
        $userIds = collect($users)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($userIds === []) {
            return;
        }

        $tokens = DevicePushToken::query()
            ->whereIn('user_id', $userIds)
            ->pluck('token')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($tokens === []) {
            return;
        }

        $messages = [];
        foreach ($tokens as $token) {
            $messages[] = [
                'to' => $token,
                'sound' => 'default',
                'title' => $message['title'],
                'body' => $message['body'],
                'data' => $message['data'] ?? [],
            ];
        }

        foreach (array_chunk($messages, 100) as $chunk) {
            try {
                $response = Http::acceptJson()
                    ->asJson()
                    ->timeout(20)
                    ->post('https://exp.host/--/api/v2/push/send', $chunk);

                if (! $response->successful()) {
                    Log::warning('Expo push failed', ['status' => $response->status(), 'body' => $response->body()]);

                    continue;
                }

                $data = $response->json('data') ?? [];
                foreach ($data as $i => $ticket) {
                    $status = $ticket['status'] ?? null;
                    if ($status === 'error') {
                        $err = $ticket['details']['error'] ?? ($ticket['message'] ?? '');
                        if ($err === 'DeviceNotRegistered' && isset($chunk[$i]['to'])) {
                            DevicePushToken::query()->where('token', $chunk[$i]['to'])->delete();
                        }
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
