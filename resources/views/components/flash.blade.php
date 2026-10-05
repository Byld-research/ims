@if (session('success'))
    <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800" role="status">
        {{ session('success') }}
    </div>
@endif
@if (session('error'))
    <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800" role="alert">
        {{ session('error') }}
    </div>
@endif
@if (session('warning'))
    <div class="rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800" role="alert">
        {{ session('warning') }}
    </div>
@endif
