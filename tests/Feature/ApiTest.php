<?php

use App\Models\ApiClient;
use App\Models\AuditLog;
use App\Models\Item;
use App\Models\Machine;
use App\Models\MachineType;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\User;
use App\Services\PurchaseOrderService;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/*
| SPEC 7a and criteria 38–45: the read-only API. Case: a reporting tool reads Colorado
| (BPC002) stock of the saw blade SP-10001 used on Truss Saw 004C.
*/

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->georgia = Site::query()->where('code', 'BPC001')->sole();
    $this->colorado = Site::query()->where('code', 'BPC002')->sole();
    $this->admin = User::factory()->admin()->create();
    $this->stock = app(StockService::class);
    $this->found = ReasonCode::adjustment('FOUND');

    $this->blade = Item::factory()->create(['sku' => 'SP-10001', 'name' => 'Saw blade 18" carbide, 72T', 'uom' => 'pc', 'criticality' => 'HIGH']);
    $this->stock->adjust($this->blade, $this->colorado, true, '1', $this->found, $this->admin, '412');
    $this->stock->adjust($this->blade, $this->georgia, true, '5', $this->found, $this->admin, '400');
    Stock::query()->where('item_id', $this->blade->id)->where('site_id', $this->colorado->id)->update(['min_level' => 2, 'bin' => 'CO-SP01']);

    $this->krakow = Supplier::factory()->create(['name' => 'Kraków warehouse']);
    SupplierItem::factory()->for($this->krakow)->for($this->blade)->create(['last_price' => '412.0000', 'pack_size' => 1]);
});

/** A client and its plain-text token. */
function apiClient(array $attributes = []): array
{
    $client = ApiClient::factory()->create($attributes);

    return [$client, $client->issueToken()];
}

function api(string $token, string $uri, array $query = [])
{
    return test()->withToken($token)->getJson('/api/v1/'.$uri.($query ? '?'.http_build_query($query) : ''));
}

test('criterion 38: no token, a wrong token or a browser session gets 401', function () {
    $this->getJson('/api/v1/items')->assertUnauthorized();
    $this->withToken('ims_not-a-token')->getJson('/api/v1/items')->assertUnauthorized();

    $this->actingAs($this->admin)->getJson('/api/v1/items')->assertUnauthorized();
});

test('criterion 39: a deactivated client or a replaced token stops working at once', function () {
    [$client, $token] = apiClient();
    api($token, 'items')->assertOk();

    $newToken = $client->issueToken();
    $this->app['auth']->forgetGuards();
    api($token, 'items')->assertUnauthorized();
    api($newToken, 'items')->assertOk();

    $client->update(['is_active' => false]);
    $this->app['auth']->forgetGuards();
    api($newToken, 'items')->assertUnauthorized();
});

test('tokens carry the ims_ prefix and only the read ability', function () {
    [$client, $token] = apiClient();

    expect($token)->toMatch('/^\d+\|ims_/')
        ->and($client->tokens()->sole()->abilities)->toBe(['read']);
});

test('criterion 40: every API route is read-only', function () {
    $api = collect(Route::getRoutes()->getRoutes())->filter(fn (RoutingRoute $r) => str_starts_with($r->uri(), 'api/'));

    expect($api)->not->toBeEmpty()
        ->and($api->every(fn (RoutingRoute $r) => array_diff($r->methods(), ['GET', 'HEAD']) === []))->toBeTrue();

    [, $token] = apiClient();
    $this->withToken($token)->postJson('/api/v1/items', ['sku' => 'X'])->assertStatus(405);
});

test('criterion 41: items carry stock per site with exact decimals as strings', function () {
    [, $token] = apiClient();

    $response = api($token, 'items', ['sku' => 'SP-10001'])->assertOk();

    $item = $response->json('data.0');
    $colorado = collect($item['stock'])->firstWhere('site', 'BPC002');
    expect($response->json('meta.total'))->toBe(1)
        ->and($item['criticality'])->toBe('HIGH')
        ->and($colorado)->toMatchArray(['qty' => '1.000', 'min_level' => '2.000', 'location' => 'CO-SP01',
            'avg_cost' => '412.0000', 'value' => '412.0000', 'status' => 'below_min', 'on_order' => '0.000']);
});

test('one item shows its suppliers with last price and pack size', function () {
    [, $token] = apiClient();

    api($token, 'items/'.$this->blade->id)->assertOk()
        ->assertJsonPath('data.suppliers.0.supplier', 'Kraków warehouse')
        ->assertJsonPath('data.suppliers.0.last_price', '412.0000');
});

test('stock lists rows by item and site, filters by replenishment, and shows quantity on order', function () {
    $orders = app(PurchaseOrderService::class);
    $order = $orders->create($this->krakow, $this->colorado, $this->admin);
    $orders->addLine($order, $this->blade, '3', null);
    $orders->markOrdered($order);

    [, $token] = apiClient();

    $rows = api($token, 'stock', ['sku' => 'SP-10001'])->assertOk()->json('data');
    expect(collect($rows)->pluck('site')->all())->toBe(['BPC001', 'BPC002']);

    $short = api($token, 'stock', ['needs_replenishment' => 1])->assertOk()->json('data');
    expect($short)->toHaveCount(1)
        ->and($short[0]['site'])->toBe('BPC002')
        ->and($short[0]['on_order'])->toBe('3.000');
});

test('criterion 42: a client limited to Colorado sees only Colorado and cannot ask for Georgia', function () {
    [, $token] = apiClient(['site_id' => $this->colorado->id]);

    expect(collect(api($token, 'stock')->json('data'))->pluck('site')->unique()->all())->toBe(['BPC002'])
        ->and(collect(api($token, 'sites')->json('data'))->pluck('code')->all())->toBe(['BPC002'])
        ->and(collect(api($token, 'items', ['sku' => 'SP-10001'])->json('data.0.stock'))->pluck('site')->all())->toBe(['BPC002']);

    api($token, 'stock', ['site' => 'BPC001'])->assertForbidden();

    $saw003 = Machine::query()->where('sku', '003C')->sole();
    api($token, 'machines/'.$saw003->sku)->assertNotFound();
    api($token, 'machines/004C')->assertOk();
});

test('a client limited to Colorado cannot open a Georgia purchase order', function () {
    $order = app(PurchaseOrderService::class)->create($this->krakow, $this->georgia, $this->admin);
    [, $token] = apiClient(['site_id' => $this->colorado->id]);

    api($token, 'purchase-orders/'.$order->number)->assertNotFound();
    expect(api($token, 'purchase-orders')->json('data'))->toBe([]);
});

test('a purchase order comes with its lines, outstanding quantity and total', function () {
    $orders = app(PurchaseOrderService::class);
    $order = $orders->create($this->krakow, $this->colorado, $this->admin);
    $orders->addLine($order, $this->blade, '3', null);
    $orders->markOrdered($order);
    $orders->receive($order, [$order->lines()->sole()->id => '1'], $this->admin);

    [, $token] = apiClient();

    api($token, 'purchase-orders/'.$order->number)->assertOk()
        ->assertJsonPath('data.status', 'PARTIALLY_RECEIVED')
        ->assertJsonPath('data.site', 'BPC002')
        ->assertJsonPath('data.total', '1236.0000')
        ->assertJsonPath('data.lines.0.item.sku', 'SP-10001')
        ->assertJsonPath('data.lines.0.qty_outstanding', '2.000');

    expect(api($token, 'purchase-orders', ['status' => 'open'])->json('meta.total'))->toBe(1)
        ->and(api($token, 'purchase-orders', ['status' => 'CLOSED'])->json('meta.total'))->toBe(0);
});

test('a machine comes with the parts list of its revision', function () {
    $truss = MachineType::query()->where('code', 'C')->sole();
    $truss->partsList()->create(['item_id' => $this->blade->id, 'revision' => '2.0', 'qty_per_machine' => 1]);
    [, $token] = apiClient();

    api($token, 'machines/004C')->assertOk()
        ->assertJsonPath('data.type.code', 'C')
        ->assertJsonPath('data.revision', '2.0')
        ->assertJsonPath('data.parts_list.0.item.sku', 'SP-10001');

    expect(api($token, 'machines/003C')->json('data.parts_list'))->toBe([])
        ->and(api($token, 'machine-types/C')->json('data.parts_list'))->toHaveCount(1);
});

test('criterion 43: stock movements are a feed, oldest first, continued with after_id', function () {
    [, $token] = apiClient();

    $first = api($token, 'stock-movements')->assertOk()->json('data');
    expect($first)->toHaveCount(2)
        ->and($first[0]['type'])->toBe('ADJUSTMENT')
        ->and($first[0]['qty_delta'])->toBe('1.000')
        ->and($first[0]['reason'])->toBe('FOUND');

    $this->stock->issueToMachine($this->blade, Machine::query()->where('sku', '004C')->sole(), '1', $this->admin);

    $new = api($token, 'stock-movements', ['after_id' => $first[1]['id']])->json('data');
    expect($new)->toHaveCount(1)
        ->and($new[0])->toMatchArray(['type' => 'ISSUE_MACHINE', 'machine' => '004C', 'qty_delta' => '-1.000', 'site' => 'BPC002']);

    expect(api($token, 'stock-movements', ['type' => 'ISSUE_MACHINE', 'machine' => '004C'])->json('meta.total'))->toBe(1)
        ->and(StockTransaction::query()->count())->toBe(3);
});

test('paging is limited to 200 per page and bad filters are answered with 422', function () {
    [, $token] = apiClient();

    api($token, 'items', ['per_page' => 201])->assertUnprocessable();
    api($token, 'items', ['criticality' => 'URGENT'])->assertUnprocessable()->assertJsonValidationErrors('criticality');
    api($token, 'stock', ['site' => 'NOPE'])->assertNotFound();
});

test('criterion 44: more requests per minute than allowed get 429', function () {
    config(['ims.api.per_minute' => 2]);
    [, $token] = apiClient();

    api($token, 'sites')->assertOk();
    api($token, 'sites')->assertOk();
    api($token, 'sites')->assertStatus(429)->assertHeader('Retry-After');
});

test('the OpenAPI description is public', function () {
    $this->get('/api/v1/openapi.yaml')->assertOk()->assertSee('BPC Inventory System API');
});

test('criterion 45: an administrator creates a client and sees its token once; the change is audited', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.api-clients.store'), ['name' => 'Power BI reports', 'site_id' => $this->colorado->id, 'notes' => 'Finance team'])
        ->assertRedirect();

    $client = ApiClient::query()->sole();
    $token = session('api_token');

    expect($client->site_id)->toBe($this->colorado->id)
        ->and($client->created_by)->toBe($this->admin->id)
        ->and($token)->toContain('|ims_')
        ->and(AuditLog::query()->where('entity', 'api_client')->where('entity_id', $client->id)->exists())->toBeTrue();

    $this->get(route('admin.api-clients.show', $client))->assertOk()->assertSee($token);
    $this->get(route('admin.api-clients.show', $client))->assertOk()->assertDontSee($token);

    api($token, 'stock')->assertOk();
});

test('an administrator replaces a token; the old one stops working', function () {
    [$client, $old] = apiClient();

    $this->actingAs($this->admin)->post(route('admin.api-clients.token', $client))->assertRedirect(route('admin.api-clients.show', $client));
    $new = session('api_token');
    $this->app['auth']->forgetGuards();

    expect($new)->not->toBe($old);
    api($old, 'sites')->assertUnauthorized();
    api($new, 'sites')->assertOk();
});

test('managers and operators cannot manage API clients', function () {
    $manager = User::factory()->manager($this->colorado)->create();
    [$client] = apiClient();

    $this->actingAs($manager)->get(route('admin.api-clients.index'))->assertForbidden();
    $this->actingAs($manager)->post(route('admin.api-clients.token', $client))->assertForbidden();
    $this->actingAs($manager)->post(route('admin.api-clients.store'), ['name' => 'Mine'])->assertForbidden();
    $this->actingAs($manager)->get('/')->assertDontSee('API clients');
});
