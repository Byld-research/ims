<?php

use App\Models\Site;
use App\Models\User;
use App\Support\CurrentSite;

beforeEach(function () {
    $this->georgia = Site::factory()->create(['code' => 'BPC001', 'name' => 'BPC Georgia']);
    $this->colorado = Site::factory()->create(['code' => 'BPC002', 'name' => 'BPC Colorado']);
});

test('a manager works at their own site', function () {
    $manager = User::factory()->manager($this->georgia)->create();

    $this->actingAs($manager)->get('/')
        ->assertOk()
        ->assertSee('BPC001 · BPC Georgia')
        ->assertDontSee('name="site"', false);

    expect(app(CurrentSite::class)->id())->toBe($this->georgia->id);
});

test('a manager cannot switch site', function () {
    $manager = User::factory()->manager($this->georgia)->create();

    $this->actingAs($manager)
        ->post(route('site.select'), ['site' => $this->colorado->id])
        ->assertForbidden();
});

test('an operator cannot switch site', function () {
    $operator = User::factory()->operator($this->colorado)->create();

    $this->actingAs($operator)
        ->post(route('site.select'), ['site' => $this->georgia->id])
        ->assertForbidden();
});

test('an administrator defaults to the first active site by code', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/')->assertOk()->assertSee('name="site"', false);

    expect(app(CurrentSite::class)->id())->toBe($this->georgia->id);
});

test('an administrator can switch to another site and to the consolidated view', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from('/')
        ->post(route('site.select'), ['site' => $this->colorado->id])
        ->assertRedirect('/');

    $this->get('/')->assertSee('BPC Colorado');
    expect(session(CurrentSite::SESSION_KEY))->toBe($this->colorado->id);

    $this->post(route('site.select'), ['site' => CurrentSite::ALL]);

    $this->get('/')->assertSee('All sites');
    expect(session(CurrentSite::SESSION_KEY))->toBe(CurrentSite::ALL);
});

test('an administrator cannot select an inactive or unknown site', function () {
    $admin = User::factory()->admin()->create();
    $closed = Site::factory()->create(['is_active' => false]);

    $this->actingAs($admin)
        ->post(route('site.select'), ['site' => $closed->id])
        ->assertSessionHasErrors('site');

    $this->post(route('site.select'), ['site' => 999999])
        ->assertSessionHasErrors('site');
});
