<?php

namespace App\Services;

use App\Mail\NoticePublishedMail;
use App\Models\Notice;
use App\Models\NoticeUser;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class NoticePublishService
{
    public function __construct(
        private AudienceResolver $audience,
        private ExpoPushService $push,
    ) {}

    /**
     * @return array{recipients: int, emailed: int}
     */
    public function publish(Notice $notice): array
    {
        $notice->load('targets');
        $users = $this->audience->usersFor($notice->audience_mode, $notice->targetIds());

        $emailed = 0;
        foreach ($users as $user) {
            NoticeUser::query()->updateOrCreate(
                [
                    'notice_id' => $notice->id,
                    'user_id' => $user->id,
                ],
                [
                    'delivered_at' => now(),
                ]
            );

            if ($user->hasRealEmail()) {
                try {
                    Mail::to($user->email)->send(new NoticePublishedMail($user, $notice));
                    $emailed++;
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        $this->push->sendToUsers($users, [
            'title' => $notice->priority === 'urgent' ? 'Urgent notice' : 'New notice',
            'body' => $notice->title,
            'data' => [
                'type' => 'notice',
                'notice_id' => $notice->id,
            ],
        ]);

        $notice->update([
            'status' => 'published',
            'published_at' => $notice->published_at ?? now(),
        ]);

        return [
            'recipients' => $users->count(),
            'emailed' => $emailed,
        ];
    }
}
