<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\Machine;
use App\Models\MachineTypeItem;
use App\Models\PurchaseOrderLine;
use App\Models\Site;
use App\Models\StockCount;
use App\Models\SupplierItem;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/*
| SPEC 13, criterion 22: an operator receives a 403 on every write route.
| Every non-GET route behind the auth middleware is checked, so new routes are covered automatically.
| A route with an unknown parameter fails the test until a fixture is added below.
*/

const OPERATOR_ALLOWED_WRITES = ['logout', 'password', 'confirm-password', 'profile', 'site'];

test('an operator is refused on every write route', function () {
    $site = Site::factory()->create();
    $operator = User::factory()->operator($site)->create();

    $supplierItem = SupplierItem::factory()->create();
    $machineTypeItem = MachineTypeItem::factory()->create();

    $fixtures = [
        'item' => Item::factory()->create()->id,
        'category' => Category::factory()->create()->id,
        'supplier' => $supplierItem->supplier_id,
        'supplierItem' => $supplierItem->id,
        'machineType' => $machineTypeItem->machine_type_id,
        'machineTypeItem' => $machineTypeItem->id,
        'machine' => Machine::factory()->create(['site_id' => $site->id])->id,
        'purchaseOrder' => ($line = PurchaseOrderLine::factory()->create())->purchase_order_id,
        'purchaseOrderLine' => $line->id,
        'stockCount' => ($count = StockCount::factory()->create(['site_id' => $site->id]))->id,
        'stockCountLine' => $count->lines()->create(['item_id' => $supplierItem->item_id, 'qty_expected' => 0])->id,
    ];

    $writeRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => array_diff($route->methods(), ['GET', 'HEAD']) !== [])
        ->reject(fn (RoutingRoute $route) => in_array($route->uri(), OPERATOR_ALLOWED_WRITES, true))
        ->filter(fn (RoutingRoute $route) => in_array('auth', $route->gatherMiddleware(), true));

    expect($writeRoutes)->not->toBeEmpty();

    foreach ($writeRoutes as $route) {
        $uri = $route->uri();
        foreach ($route->parameterNames() as $name) {
            expect(array_key_exists($name, $fixtures))->toBeTrue("Add a fixture for route parameter {{$name}} ({$uri}).");
            $uri = str_replace('{'.$name.'}', (string) $fixtures[$name], $uri);
        }

        $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();

        $response = $this->actingAs($operator)->call($method, '/'.$uri);

        expect($response->getStatusCode())->toBe(403, "{$method} /{$uri} should be forbidden for an operator.");
    }
});
