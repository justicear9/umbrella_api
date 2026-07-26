<?php

namespace App\Services;

use App\Mail\MediaPublishedMail;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Mail;

class MediaPublishService
{
    public function __construct(
        private AudienceResolver $audience,
        private ExpoPushService $push,
    ) {}

    /**
     * @return array{recipients: int, emailed: int}
     */
    public function publish(MediaAsset $asset): array
    {
        $asset->load('targets');
        $users = $this->audience->usersFor($asset->audience_mode, $asset->targetIds());

        $emailed = 0;
        foreach ($users as $user) {
            if ($user->hasRealEmail()) {
                try {
                    Mail::to($user->email)->send(new MediaPublishedMail($user, $asset));
                    $emailed++;
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        $this->push->sendToUsers($users, [
            'title' => 'New media',
            'body' => $asset->title,
            'data' => [
                'type' => 'media',
                'media_id' => $asset->id,
            ],
        ]);

        $asset->update([
            'status' => 'published',
            'published_at' => $asset->published_at ?? now(),
        ]);

        return [
            'recipients' => $users->count(),
            'emailed' => $emailed,
        ];
    }
}
