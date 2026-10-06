<?php

use App\Mail\DailyDigest;
use App\Models\Item;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\Stock;
use App\Models\User;
use App\Services\Digest;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Mail;

/*
| SPEC 5.9 and criterion 23. Colorado (America/Denver) sends at 07:00 local by default.
*/

beforeEach(function () {
    // No development accounts: recipients must be exactly the users made here.
    config(['ims.seed.admin_password' => null, 'ims.seed.manager_password' => null]);
    $this->seed(DatabaseSeeder::class);
    Mail::fake();

    $this->colorado = Site::query()->where('code', 'BPC002')->sole();
    $this->georgia = Site::query()->where('code', 'BPC001')->sole();
    $this->coloradoManager = User::factory()->manager($this->colorado)->create(['notify_low_stock' => true]);
    $this->coloradoOptedOut = User::factory()->operator($this->colorado)->create(['notify_low_stock' => false]);
    $this->georgiaManager = User::factory()->manager($this->georgia)->create(['notify_low_stock' => true]);
});

function shortOfBlades(Site $site): void
{
    $blade = Item::query()->firstWhere('sku', 'SP-10001') ?? Item::factory()->create(['sku' => 'SP-10001', 'criticality' => 'HIGH']);
    app(StockService::class)->adjust($blade, $site, true, '1', ReasonCode::adjustment('FOUND'), User::factory()->admin()->create(), '412');
    Stock::query()->where('item_id', $blade->id)->where('site_id', $site->id)->update(['min_level' => 2]);
}

/** Travel to a local hour at a site; the digest command compares local time. */
function atLocalHour(string $timezone, int $hour): void
{
    test()->travelTo(now($timezone)->setTime($hour, 5)->utc());
}

test('criterion 23: nothing to report, nothing sent', function () {
    atLocalHour('America/Denver', 7);

    $this->artisan('ims:send-digests')->expectsOutputToContain('BPC002: nothing to report, nothing sent.')->assertSuccessful();

    Mail::assertNothingSent();
});

test('the Colorado digest goes at 07:00 Denver time to opted-in Colorado users only', function () {
    shortOfBlades($this->colorado);
    atLocalHour('America/Denver', 7);

    $this->artisan('ims:send-digests')->expectsOutputToContain('BPC002: sent to 1 recipient (1 item below minimum).');

    Mail::assertSent(DailyDigest::class, fn (DailyDigest $mail) => $mail->hasTo($this->coloradoManager->email)
        && $mail->digest->site->is($this->colorado));
    Mail::assertSentCount(1);
});

test('nothing is sent outside the site’s digest hour, and only once per day', function () {
    shortOfBlades($this->colorado);

    atLocalHour('America/Denver', 8);
    $this->artisan('ims:send-digests');
    Mail::assertNothingSent();

    atLocalHour('America/Denver', 7);
    $this->artisan('ims:send-digests');
    $this->artisan('ims:send-digests')->expectsOutputToContain('BPC002: already sent today.');
    Mail::assertSentCount(1);
});

test('the site hour follows the site’s own setting and time zone', function () {
    shortOfBlades($this->georgia);
    $this->georgia->update(['digest_hour' => 6]);

    atLocalHour('America/New_York', 6);
    $this->artisan('ims:send-digests');

    Mail::assertSent(DailyDigest::class, fn ($mail) => $mail->hasTo($this->georgiaManager->email));
});

test('administrators receive all sites in one message at their own hour', function () {
    shortOfBlades($this->colorado);
    shortOfBlades($this->georgia);
    $admin = User::factory()->admin()->create(['notify_low_stock' => true]);
    config(['ims.admin_digest' => ['hour' => 9, 'timezone' => 'America/New_York']]);

    atLocalHour('America/New_York', 9);
    $this->artisan('ims:send-digests');

    Mail::assertSent(DailyDigest::class, fn (DailyDigest $mail) => $mail->hasTo($admin->email)
        && $mail->digest->site === null
        && $mail->digest->sections['below']['total'] === 2);
});

test('the email lists the shortage with what is on order', function () {
    shortOfBlades($this->colorado);

    $html = (new DailyDigest(new Digest($this->colorado), 'Mon, Oct 5, 2026'))->render();

    expect($html)->toContain('BPC002 · BPC Colorado')
        ->toContain('SP-10001')
        ->toContain('1 / 2 pc')
        ->toContain('Open the dashboard');
});

test('--now sends every digest regardless of the hour', function () {
    shortOfBlades($this->colorado);
    atLocalHour('America/Denver', 15);

    $this->artisan('ims:send-digests --now');

    Mail::assertSent(DailyDigest::class, fn ($mail) => $mail->hasTo($this->coloradoManager->email));
});
