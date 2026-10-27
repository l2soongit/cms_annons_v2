@if (session('status'))
    <p {{ $attributes->class('rounded-md border border-signal/40 bg-signal/10 px-4 py-3 text-sm') }} role="status">{{ session('status') }}</p>
@endif
@if (session('error'))
    <p {{ $attributes->class('rounded-md border border-fade/40 bg-fade/10 px-4 py-3 text-sm') }} role="alert">{{ session('error') }}</p>
@endif
