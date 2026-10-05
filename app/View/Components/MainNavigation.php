<?php

namespace App\View\Components;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Builds the menu from config/navigation.php for the current user and route.
 */
class MainNavigation extends Component
{
    /** @var list<array{label: string, route: ?string, active: bool, links: list<array<string, mixed>>}> */
    public array $sections;

    /** @var list<array<string, mixed>> */
    public array $actions;

    public function __construct()
    {
        $this->sections = collect(config('navigation.sections'))
            ->map(function (array $section) {
                if (isset($section['links'])) {
                    $links = collect($section['links'])->filter(fn ($link) => $this->visible($link))->map(fn ($link) => $this->withState($link))->values()->all();

                    return $links === [] ? null : [
                        'label' => $section['label'],
                        'route' => null,
                        'links' => $links,
                        'active' => collect($links)->contains('active', true),
                    ];
                }

                return $this->visible($section) ? [...$this->withState($section), 'links' => []] : null;
            })
            ->filter()
            ->values()
            ->all();

        $this->actions = collect(config('navigation.actions'))
            ->filter(fn ($action) => $this->visible($action))
            ->map(fn ($action) => $this->withState($action))
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('layouts.navigation');
    }

    private function visible(array $link): bool
    {
        return Route::has($link['route'])
            && (! isset($link['can']) || Gate::allows($link['can'], $link['model'] ?? []));
    }

    private function withState(array $link): array
    {
        return [
            ...$link,
            'url' => route($link['route']),
            'active' => request()->routeIs(...($link['active'] ?? [$link['route']])),
        ];
    }
}
