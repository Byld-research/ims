<?php

namespace App\View\Components;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;
use Illuminate\View\View;

class MainNavigation extends Component
{
    /** @var list<array{route: string, label: string, active: bool}> */
    public array $links;

    public function __construct()
    {
        $this->links = collect(config('navigation'))
            ->filter(fn (array $link) => Route::has($link['route']))
            ->filter(fn (array $link) => ! isset($link['can']) || Gate::allows($link['can'], $link['model']))
            ->map(fn (array $link) => [
                'route' => $link['route'],
                'label' => $link['label'],
                'active' => request()->routeIs(preg_replace('/\.index$/', '', $link['route']).'*'),
            ])
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('layouts.navigation');
    }
}
