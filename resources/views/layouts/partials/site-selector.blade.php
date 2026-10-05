@if ($currentSite->canSwitch())
    <form method="POST" action="{{ route('site.select') }}">
        @csrf
        <select name="site" aria-label="{{ __('Site') }}" onchange="this.form.submit()"
                class="rounded-md border-gray-300 py-1.5 text-sm font-medium shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach ($currentSite->options() as $option)
                <option value="{{ $option->id }}" @selected($currentSite->id() === $option->id)>
                    {{ $option->code }} · {{ $option->name }}
                </option>
            @endforeach
            <option value="{{ \App\Support\CurrentSite::ALL }}" @selected($currentSite->isConsolidated())>{{ __('All sites') }}</option>
        </select>
        <noscript><button type="submit" class="ms-1 text-sm underline">{{ __('Switch') }}</button></noscript>
    </form>
@elseif ($currentSite->get())
    <span class="inline-flex items-center rounded-md bg-indigo-50 px-2.5 py-1 text-sm font-medium text-indigo-700"
          title="{{ $currentSite->get()->name }}">
        {{ $currentSite->get()->code }} · {{ $currentSite->get()->name }}
    </span>
@endif
