<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Mail\DailyDigest;
use App\Models\Site;
use App\Models\User;
use App\Services\Digest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Runs hourly. Sends each site's digest when its local digest hour arrives, and the
 * administrators' all-sites digest at theirs (SPEC 5.9). At most once per scope per day.
 */
#[Signature('ims:send-digests {--now : Send every digest now, ignoring the configured hours}')]
#[Description('Send the daily low-stock digests that are due this hour')]
class SendDigests extends Command
{
    public function handle(): int
    {
        foreach (Site::query()->active()->orderBy('code')->get() as $site) {
            $this->sendIfDue(
                scope: "site-{$site->id}",
                timezone: $site->timezone,
                hour: $site->digest_hour,
                digest: fn () => new Digest($site),
                recipients: fn () => User::query()->active()->where('notify_low_stock', true)
                    ->where('role', '!=', Role::Admin)->where('site_id', $site->id)->get(),
                label: $site->code,
            );
        }

        $this->sendIfDue(
            scope: 'admins',
            timezone: config('ims.admin_digest.timezone'),
            hour: config('ims.admin_digest.hour'),
            digest: fn () => new Digest(null),
            recipients: fn () => User::query()->active()->where('notify_low_stock', true)->where('role', Role::Admin)->get(),
            label: 'administrators',
        );

        return self::SUCCESS;
    }

    private function sendIfDue(string $scope, string $timezone, int $hour, callable $digest, callable $recipients, string $label): void
    {
        $local = now($timezone);

        if (! $this->option('now') && $local->hour !== $hour) {
            return;
        }

        // Once per scope and local day, even if the scheduler fires twice in the hour.
        if (! $this->option('now') && ! Cache::add("digest:{$scope}:{$local->toDateString()}", true, now()->addHours(26))) {
            $this->line("{$label}: already sent today.");

            return;
        }

        /** @var Digest $content */
        $content = $digest();

        if ($content->isEmpty()) {
            $this->line("{$label}: nothing to report, nothing sent.");

            return;
        }

        $users = $recipients();

        foreach ($users as $user) {
            Mail::to($user)->send(new DailyDigest($content, $local->toFormattedDayDateString()));
        }

        $this->info("{$label}: sent to {$users->count()} ".str('recipient')->plural($users->count()).' ('.$content->summary().').');
    }
}
