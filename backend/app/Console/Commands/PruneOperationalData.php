<?php

namespace App\Console\Commands;

use App\Models\IdempotencyKey;
use App\Models\Media;
use App\Models\Message;
use App\Models\PushDevice;
use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class PruneOperationalData extends Command
{
    protected $signature = 'operations:prune {--force : Delete records instead of previewing them}';

    protected $description = 'Prune expired operational records and orphaned chat media';

    public function handle(): int
    {
        $expiredKeys = IdempotencyKey::query()->where('expires_at', '<=', now())->count();
        $staleDevices = PushDevice::query()
            ->where('last_seen_at', '<=', now()->subDays((int) config('lifecycle.stale_push_device_days')))
            ->count();
        $orphanMedia = $this->orphanMediaQuery()->count();
        $expiredStories = Story::query()->where('expires_at', '<=', now())->count();

        $this->table(['Data set', 'Eligible'], [
            ['Expired idempotency keys', $expiredKeys],
            ['Stale push devices', $staleDevices],
            ['Orphaned chat media', $orphanMedia],
            ['Expired stories', $expiredStories],
        ]);

        if (! $this->option('force')) {
            $this->warn('Dry run only. Re-run with --force to delete eligible data.');

            return self::SUCCESS;
        }

        IdempotencyKey::query()->where('expires_at', '<=', now())->delete();
        PushDevice::query()
            ->where('last_seen_at', '<=', now()->subDays((int) config('lifecycle.stale_push_device_days')))
            ->delete();
        $this->orphanMediaQuery()->eachById(function (Media $media): void {
            Storage::disk($media->disk)->delete($media->path);
            $media->delete();
        });
        Story::query()->where('expires_at', '<=', now())->eachById(function (Story $story): void {
            Storage::disk($story->media_disk)->delete($story->media_path);
            $story->delete();
        });

        $this->info('Operational data pruning completed.');

        return self::SUCCESS;
    }

    private function orphanMediaQuery(): Builder
    {
        return Media::query()
            ->where('mediable_type', Message::class)
            ->where('created_at', '<=', now()->subHours((int) config('lifecycle.orphan_media_grace_hours')))
            ->whereDoesntHave('mediable');
    }
}
