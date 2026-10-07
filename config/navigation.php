<?php

use App\Models\ApiClient;
use App\Models\MachineType;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\StockCount;
use App\Models\Supplier;
use App\Models\User;

/*
| Main navigation: a few sections with dropdowns, plus one action button.
|
| A link appears only when its route exists and the user passes 'can' (an ability, checked
| against 'model' when given). A section appears only when at least one of its links does.
| 'active' lists route-name patterns that highlight the link and its section.
*/

return [
    'sections' => [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => ['dashboard']],

        ['label' => 'Stock', 'links' => [
            ['label' => 'Inventory', 'route' => 'stock.index', 'active' => ['stock.index', 'items.*'],
                'description' => 'Stock on hand per site: quantities, levels, value'],
            ['label' => 'Two-bin items', 'route' => 'kanban.index', 'active' => ['kanban.*'],
                'description' => 'Consumables kept in two bins; refills first'],
            ['label' => 'Stock counts', 'route' => 'stock-counts.index', 'active' => ['stock-counts.*'],
                'can' => 'viewAny', 'model' => StockCount::class, 'description' => 'Cycle counting and items due'],
            ['label' => 'Min levels & locations', 'route' => 'stock.levels', 'active' => ['stock.levels*'],
                'can' => 'set-levels', 'description' => 'Minimum levels, shelf locations, two-bin settings'],
            ['label' => 'Categories', 'route' => 'categories.index', 'active' => ['categories.*']],
        ]],

        ['label' => 'Purchasing', 'links' => [
            ['label' => 'Purchase orders', 'route' => 'purchase-orders.index', 'active' => ['purchase-orders.*']],
            ['label' => 'Suppliers', 'route' => 'suppliers.index', 'active' => ['suppliers.*'],
                'can' => 'viewAny', 'model' => Supplier::class],
        ]],

        ['label' => 'Machines', 'links' => [
            ['label' => 'Machines', 'route' => 'machines.index', 'active' => ['machines.*'],
                'description' => 'Machines at the site, parts lists and consumption'],
            ['label' => 'Machine types', 'route' => 'machine-types.index', 'active' => ['machine-types.*'],
                'can' => 'viewAny', 'model' => MachineType::class, 'description' => 'Parts lists per type and revision'],
        ]],

        ['label' => 'Admin', 'links' => [
            ['label' => 'Users', 'route' => 'admin.users.index', 'active' => ['admin.users.*'],
                'can' => 'viewAny', 'model' => User::class, 'description' => 'Accounts, roles, sites, digest'],
            ['label' => 'Sites', 'route' => 'admin.sites.index', 'active' => ['admin.sites.*'],
                'can' => 'viewAny', 'model' => Site::class, 'description' => 'Time zones and daily digest hour'],
            ['label' => 'Reason codes', 'route' => 'admin.reason-codes.index', 'active' => ['admin.reason-codes.*'],
                'can' => 'viewAny', 'model' => ReasonCode::class],
            ['label' => 'Audit log', 'route' => 'admin.audit.index', 'active' => ['admin.audit.*'],
                'can' => 'view-audit-log', 'description' => 'Who changed which master data'],
            ['label' => 'API clients', 'route' => 'admin.api-clients.index', 'active' => ['admin.api-clients.*'],
                'can' => 'viewAny', 'model' => ApiClient::class, 'description' => 'Applications reading data through the API'],
        ]],
    ],

    // The action button: the first entry is the button itself, the rest its menu (SPEC 7, principle 1).
    'actions' => [
        ['label' => 'Issue', 'menu_label' => 'Issue stock', 'route' => 'issues.create', 'active' => ['issues.*'], 'can' => 'issue-stock'],
        ['label' => 'Transfer in', 'route' => 'transfers.create', 'active' => ['transfers.*'], 'can' => 'issue-stock'],
        ['label' => 'Adjust stock', 'route' => 'adjustments.create', 'active' => ['adjustments.*'], 'can' => 'issue-stock'],
    ],
];
