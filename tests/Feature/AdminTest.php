<?php

use App\Enums\Role;
use App\Models\Machine;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->georgia = Site::query()->where('code', 'BPC001')->sole();
    $this->colorado = Site::query()->where('code', 'BPC002')->sole();
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('only administrators reach the admin screens', function () {
    foreach ([User::factory()->manager($this->colorado)->create(), User::factory()->operator($this->colorado)->create()] as $user) {
        $this->actingAs($user);
        foreach (['admin.users.index', 'admin.sites.index', 'admin.reason-codes.index', 'admin.audit.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }
});

test('an administrator creates a manager who can then log in', function () {
    $this->post(route('admin.users.store'), [
        'name' => 'Dana Reyes', 'email' => 'Dana.Reyes@BPC.test', 'role' => 'MANAGER', 'site_id' => $this->colorado->id,
        'password' => 'correct-horse-9', 'password_confirmation' => 'correct-horse-9', 'notify_low_stock' => 1,
    ])->assertRedirect(route('admin.users.index'));

    $user = User::query()->where('email', 'dana.reyes@bpc.test')->sole();
    expect($user)->role->toBe(Role::Manager)->site_id->toBe($this->colorado->id)->notify_low_stock->toBeTrue()->is_active->toBeTrue();

    auth()->logout();
    $this->post('/login', ['email' => 'dana.reyes@bpc.test', 'password' => 'correct-horse-9']);
    $this->assertAuthenticatedAs($user);
});

test('managers and operators need a site; administrators never keep one', function () {
    $this->post(route('admin.users.store'), ['name' => 'X', 'email' => 'x@bpc.test', 'role' => 'OPERATOR',
        'password' => 'correct-horse-9', 'password_confirmation' => 'correct-horse-9'])->assertSessionHasErrors('site_id');

    $this->post(route('admin.users.store'), ['name' => 'Y', 'email' => 'y@bpc.test', 'role' => 'ADMIN', 'site_id' => $this->colorado->id,
        'password' => 'correct-horse-9', 'password_confirmation' => 'correct-horse-9'])->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'y@bpc.test')->value('site_id'))->toBeNull();
});

test('an administrator cannot lock themselves out, and the last administrator stays', function () {
    $this->put(route('admin.users.update', $this->admin), ['name' => $this->admin->name, 'email' => $this->admin->email,
        'role' => 'MANAGER', 'site_id' => $this->colorado->id, 'is_active' => 1])->assertSessionHasErrors('role');
    $this->put(route('admin.users.update', $this->admin), ['name' => $this->admin->name, 'email' => $this->admin->email,
        'role' => 'ADMIN', 'is_active' => 0])->assertSessionHasErrors('role');

    // Another admin may demote this one only while a third administrator remains active.
    $other = User::factory()->admin()->create();
    User::query()->where('role', 'ADMIN')->whereKeyNot([$this->admin->id, $other->id])->update(['is_active' => false]);
    $this->actingAs($other)->put(route('admin.users.update', $this->admin), ['name' => 'A', 'email' => $this->admin->email,
        'role' => 'MANAGER', 'site_id' => $this->colorado->id, 'is_active' => 1])->assertSessionHasNoErrors();

    expect($this->admin->fresh()->role)->toBe(Role::Manager);
});

test('a password reset link can be emailed; leaving the password empty keeps it', function () {
    Notification::fake();
    $user = User::factory()->manager($this->colorado)->create();
    $hash = $user->password;

    $this->post(route('admin.users.reset-link', $user))->assertSessionHas('success');
    Notification::assertSentTo($user, ResetPassword::class);

    $this->put(route('admin.users.update', $user), ['name' => 'Renamed', 'email' => $user->email, 'role' => 'MANAGER',
        'site_id' => $this->colorado->id, 'is_active' => 1, 'password' => ''])->assertSessionHasNoErrors();
    expect($user->fresh())->name->toBe('Renamed')->password->toBe($hash);
});

test('reason codes: new codes are added; COUNT and OPENING keep their code and stay active', function () {
    $this->post(route('admin.reason-codes.store'), ['applies_to' => 'ADJUSTMENT', 'code' => 'water_damage', 'label' => 'Water damage'])
        ->assertSessionHasNoErrors();
    expect(ReasonCode::query()->where('code', 'WATER_DAMAGE')->exists())->toBeTrue();

    $count = ReasonCode::adjustment(ReasonCode::COUNT);
    $this->put(route('admin.reason-codes.update', $count), ['applies_to' => 'ADJUSTMENT', 'code' => 'RECOUNT', 'label' => 'x'])->assertSessionHasErrors('code');
    $this->put(route('admin.reason-codes.update', $count), ['applies_to' => 'ADJUSTMENT', 'code' => 'COUNT', 'label' => 'x', 'is_active' => 0])->assertSessionHasErrors('is_active');
    $this->put(route('admin.reason-codes.update', $count), ['applies_to' => 'ADJUSTMENT', 'code' => 'COUNT', 'label' => 'Stock count correction'])->assertSessionHasNoErrors();

    expect($count->fresh())->label->toBe('Stock count correction')->is_active->toBeTrue();
});

test('sites: the digest hour and time zone are editable, the code is fixed', function () {
    $this->put(route('admin.sites.update', $this->colorado), ['code' => 'BPC002', 'name' => 'BPC Colorado', 'state' => 'CO',
        'timezone' => 'America/Denver', 'digest_hour' => 6, 'is_active' => 1])->assertSessionHasNoErrors();
    expect($this->colorado->fresh()->digest_hour)->toBe(6);

    $this->put(route('admin.sites.update', $this->colorado), ['code' => 'BPC099', 'name' => 'x', 'state' => 'CO',
        'timezone' => 'America/Denver', 'digest_hour' => 6])->assertSessionHasErrors('code');
    $this->put(route('admin.sites.update', $this->colorado), ['code' => 'BPC002', 'name' => 'x', 'state' => 'CO',
        'timezone' => 'Mars/Olympus', 'digest_hour' => 25])->assertSessionHasErrors(['timezone', 'digest_hour']);
});

test('a third site can be added, and a site with machines cannot be deactivated', function () {
    $this->post(route('admin.sites.store'), ['code' => 'bpc003', 'name' => 'BPC Texas', 'state' => 'tx', 'timezone' => 'America/Chicago', 'digest_hour' => 7])
        ->assertSessionHasNoErrors();
    expect(Site::query()->where('code', 'BPC003')->value('state'))->toBe('TX');

    expect(Machine::query()->where('site_id', $this->colorado->id)->exists())->toBeTrue();
    $this->put(route('admin.sites.update', $this->colorado), ['code' => 'BPC002', 'name' => 'BPC Colorado', 'state' => 'CO',
        'timezone' => 'America/Denver', 'digest_hour' => 7, 'is_active' => 0])
        ->assertSessionHasErrors('is_active');

    expect(session('errors')->first('is_active'))->toContain('active machines');
});

test('admin lists export CSV', function () {
    expect($this->get(route('admin.users.index', ['export' => 'csv']))->streamedContent())->toContain($this->admin->email)
        ->and($this->get(route('admin.reason-codes.index', ['export' => 'csv']))->streamedContent())->toContain('OPENING');
});
