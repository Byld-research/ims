<?php

use App\Models\MachineType;
use App\Models\StockCount;
use App\Models\Supplier;
use App\Models\User;

/*
| Main navigation. An entry appears only once its route exists, so screens from later
| build stages show up automatically. 'can' is an ability checked against 'model'.
*/

return [
    ['route' => 'dashboard', 'label' => 'Dashboard'],
    ['route' => 'issues.create', 'label' => 'Issue', 'can' => 'issue-stock'],
    ['route' => 'stock.index', 'label' => 'Stock'],
    ['route' => 'kanban.index', 'label' => 'Kanban'],
    ['route' => 'purchase-orders.index', 'label' => 'Purchase orders'],
    ['route' => 'stock-counts.index', 'label' => 'Counts', 'can' => 'viewAny', 'model' => StockCount::class],
    ['route' => 'machines.index', 'label' => 'Machines'],
    ['route' => 'machine-types.index', 'label' => 'Machine types', 'can' => 'viewAny', 'model' => MachineType::class],
    ['route' => 'suppliers.index', 'label' => 'Suppliers', 'can' => 'viewAny', 'model' => Supplier::class],
    ['route' => 'admin.users.index', 'label' => 'Admin', 'can' => 'viewAny', 'model' => User::class],
];
