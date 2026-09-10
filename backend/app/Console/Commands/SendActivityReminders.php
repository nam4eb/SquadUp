<?php

namespace App\Console\Commands;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\ActivityNotificationDispatch;
use App\Notifications\ActivityStartingSoon;
use Illuminate\Console\Command;

class SendActivityReminders extends Command
{
    protected $signature = 'activities:send-reminders {--minutes=30}';

    protected $description = 'Send one starting-soon reminder per activity participant';

    public function handle(): int
    {
        $minutes = max(1, min(1440, (int) $this->option('minutes')));
        $sent = 0;
        Activity::query()
            ->whereIn('status', [ActivityStatus::Open, ActivityStatus::Full, ActivityStatus::Locked])
            ->whereBetween('starts_at', [now(), now()->addMinutes($minutes)])
            ->with(['host', 'participants' => fn ($query) => $query->where('status', 'joined')->with('user')])
            ->chunkById(100, function ($activities) use (&$sent): void {
                foreach ($activities as $activity) {
                    $users = $activity->participants->pluck('user')->push($activity->host)->filter()->unique('id');
                    foreach ($users as $user) {
                        $dispatch = ActivityNotificationDispatch::firstOrCreate(
                            ['activity_id' => $activity->id, 'user_id' => $user->id, 'type' => 'starting_soon'],
                            ['sent_at' => now()],
                        );
                        if (! $dispatch->wasRecentlyCreated) {
                            continue;
                        }
                        $user->notify(new ActivityStartingSoon($activity));
                        $sent++;
                    }
                }
            });
        $this->info("Sent {$sent} activity reminder(s).");

        return self::SUCCESS;
    }
}
