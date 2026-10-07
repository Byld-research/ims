<nav x-data="{ open: false }" class="bg-white border-b border-gray-200" aria-label="{{ __('Main') }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    {{-- Company logo, with the application name as a small tagline --}}
                    <a href="{{ route('dashboard') }}" class="flex flex-col items-start justify-center gap-1" aria-label="{{ __('BPC Inventory System, dashboard') }}">
                        <img src="{{ asset('images/byld-logo.png') }}" alt="BYLD" class="w-20">
                        <span class="text-[10px] font-medium uppercase leading-none tracking-wider text-gray-500">BPC Inventory System</span>
                    </a>
                </div>

                {{-- Sections --}}
                <div class="hidden md:flex md:ms-8 md:gap-1">
                    @foreach ($sections as $section)
                        @if ($section['links'] === [])
                            <a href="{{ $section['url'] }}"
                               @class(['inline-flex items-center px-3 border-b-2 text-sm font-medium transition',
                                   'border-indigo-500 text-gray-900' => $section['active'],
                                   'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300' => ! $section['active']])
                               @if ($section['active']) aria-current="page" @endif>
                                {{ __($section['label']) }}
                            </a>
                        @else
                            <div class="relative flex" x-data="{ menu: false }" @keydown.escape.window="menu = false" @click.outside="menu = false">
                                <button type="button" @click="menu = ! menu" :aria-expanded="menu" aria-haspopup="true"
                                        @class(['inline-flex items-center gap-1 px-3 border-b-2 text-sm font-medium transition',
                                            'border-indigo-500 text-gray-900' => $section['active'],
                                            'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300' => ! $section['active']])>
                                    {{ __($section['label']) }}
                                    <svg class="h-4 w-4 text-gray-400 transition" :class="menu && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                <div x-show="menu" x-cloak x-transition.opacity.duration.100ms
                                     class="absolute left-0 top-full z-30 mt-1 w-72 rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5">
                                    @foreach ($section['links'] as $link)
                                        <a href="{{ $link['url'] }}" @class(['block px-4 py-2 hover:bg-gray-50', 'bg-indigo-50/60' => $link['active']])
                                           @if ($link['active']) aria-current="page" @endif>
                                            <span @class(['block text-sm', 'font-semibold text-indigo-700' => $link['active'], 'text-gray-800' => ! $link['active']])>{{ __($link['label']) }}</span>
                                            @if (! empty($link['description']))
                                                <span class="block text-xs text-gray-500">{{ __($link['description']) }}</span>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="hidden md:flex md:items-center md:gap-3 md:ms-6">
                {{-- Action button: Issue, with the other stock movements in its menu --}}
                @if ($actions !== [])
                    @php($primary = $actions[0])
                    <div class="relative inline-flex rounded-md shadow-sm" x-data="{ menu: false }" @keydown.escape.window="menu = false" @click.outside="menu = false">
                        <a href="{{ $primary['url'] }}" class="btn-primary rounded-e-none">{{ __($primary['label']) }}</a>
                        @if (count($actions) > 1)
                            <button type="button" @click="menu = ! menu" :aria-expanded="menu" aria-haspopup="true" aria-label="{{ __('More stock movements') }}"
                                    class="btn-primary rounded-s-none border-s border-gray-600 px-2">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="menu" x-cloak x-transition.opacity.duration.100ms
                                 class="absolute right-0 top-full z-30 mt-1 w-48 rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5">
                                @foreach ($actions as $action)
                                    <a href="{{ $action['url'] }}" class="block px-4 py-2 text-sm text-gray-800 hover:bg-gray-50">{{ __($action['menu_label'] ?? $action['label']) }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                @include('layouts.partials.site-selector')

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-2 py-2 text-sm font-medium rounded-md text-gray-500 hover:text-gray-700 focus:outline-none transition">
                            <div>{{ Auth::user()->name }}</div>
                            <svg class="ms-1 fill-current h-4 w-4" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <div class="px-4 py-2 text-xs text-gray-500">{{ Auth::user()->role->label() }}</div>
                        <x-dropdown-link :href="route('profile.edit')">{{ __('Profile') }}</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">{{ __('Log Out') }}</x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            {{-- Mobile: action first, then the menu toggle --}}
            <div class="-me-2 flex items-center gap-2 md:hidden">
                @if ($actions !== [])
                    <a href="{{ $actions[0]['url'] }}" class="btn-primary btn-sm">{{ __($actions[0]['label']) }}</a>
                @endif
                <button @click="open = ! open" :aria-expanded="open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none" aria-label="{{ __('Menu') }}">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div x-show="open" x-cloak class="md:hidden border-t border-gray-200">
        <div class="px-4 py-3">@include('layouts.partials.site-selector')</div>
        @foreach ($sections as $section)
            @if ($section['links'] === [])
                <x-responsive-nav-link :href="$section['url']" :active="$section['active']">{{ __($section['label']) }}</x-responsive-nav-link>
            @else
                <div class="px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __($section['label']) }}</div>
                @foreach ($section['links'] as $link)
                    <x-responsive-nav-link :href="$link['url']" :active="$link['active']">{{ __($link['label']) }}</x-responsive-nav-link>
                @endforeach
            @endif
        @endforeach
        @if (count($actions) > 1)
            <div class="px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Stock movements') }}</div>
            @foreach ($actions as $action)
                <x-responsive-nav-link :href="$action['url']" :active="$action['active']">{{ __($action['menu_label'] ?? $action['label']) }}</x-responsive-nav-link>
            @endforeach
        @endif
        <div class="pt-3 pb-2 mt-2 border-t border-gray-200">
            <div class="px-4 text-sm font-medium text-gray-800">{{ Auth::user()->name }} <span class="text-gray-500">· {{ Auth::user()->role->label() }}</span></div>
            <x-responsive-nav-link :href="route('profile.edit')">{{ __('Profile') }}</x-responsive-nav-link>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">{{ __('Log Out') }}</x-responsive-nav-link>
            </form>
        </div>
    </div>
</nav>
