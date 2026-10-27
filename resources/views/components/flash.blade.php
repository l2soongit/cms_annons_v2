@php
    // Fortify повертає службові коди статусу — показуємо їх людською мовою
    $status = session('status');
    $statusText = $status && \Illuminate\Support\Facades\Lang::has("profile.status.{$status}") ? __("profile.status.{$status}") : $status;
@endphp

@if ($statusText)
    <p {{ $attributes->class('rounded-md border border-signal/40 bg-signal/10 px-4 py-3 text-sm') }} role="status">{{ $statusText }}</p>
@endif
@if (session('error'))
    <p {{ $attributes->class('rounded-md border border-fade/40 bg-fade/10 px-4 py-3 text-sm') }} role="alert">{{ session('error') }}</p>
@endif
