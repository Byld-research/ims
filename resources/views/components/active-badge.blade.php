@props(['active'])

@if ($active)
    <span class="badge-green">{{ __('Active') }}</span>
@else
    <span class="badge-gray">{{ __('Inactive') }}</span>
@endif
