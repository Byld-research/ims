<?php

use App\Models\MachineType;
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
            ['label' => 'Stock list', 'route' => 'stock.index', 'active' => ['stock.index', 'items.*'],
                'description' => 'Quantities, levels and value per site'],
            ['label' => 'Kanban', 'route' => 'kanban.index', 'active' => ['kanban.*'],
                'description' => 'Two-bin items, refills first'],
            ['label' => 'Counts', 'route' => 'stock-counts.index', 'active' => ['stock-counts.*'],
                'can' => 'viewAny', 'model' => StockCount::class, 'description' => 'Cycle counts and items due'],
            ['label' => 'Min levels & bins', 'route' => 'stock.levels', 'active' => ['stock.levels*'],
                'can' => 'set-levels', 'description' => 'Minimums, shelves and kanban per site'],
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

        ['label' => 'Admin', 'route' => 'admin.users.index', 'active' => ['admin.*'],
            'can' => 'viewAny', 'model' => User::class],
    ],

    // The action button: the first entry is the button itself, the rest its menu (SPEC 7, principle 1).
    'actions' => [
        ['label' => 'Issue', 'menu_label' => 'Issue stock', 'route' => 'issues.create', 'active' => ['issues.*'], 'can' => 'issue-stock'],
        ['label' => 'Transfer in', 'route' => 'transfers.create', 'active' => ['transfers.*'], 'can' => 'issue-stock'],
        ['label' => 'Adjust stock', 'route' => 'adjustments.create', 'active' => ['adjustments.*'], 'can' => 'issue-stock'],
    ],
];
