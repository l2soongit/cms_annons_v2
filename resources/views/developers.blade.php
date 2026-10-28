@php
    $base = url('/api/v1');
    $codeClass = 'mt-3 overflow-x-auto rounded-md border border-seam bg-slab p-4 text-xs leading-relaxed text-bone';
    $endpoints = collect($spec['paths'])->flatMap(fn ($ops, $path) => collect($ops)->map(fn ($op, $method) => [
        'method' => strtoupper($method), 'path' => $path, 'summary' => $op['summary'], 'key' => isset($op['security']),
    ])->values())->values();
@endphp
<x-layouts.app :title="__('developers.title').' | graj.club'" :description="__('developers.meta')">
    <h1 class="font-display text-3xl font-bold tracking-tight sm:text-4xl">{{ __('developers.title') }}</h1>
    <p class="mt-3 max-w-3xl text-moss">{{ __('developers.lead') }}</p>

    <nav class="mt-6 flex flex-wrap gap-2 text-sm" aria-label="{{ __('developers.title') }}">
        @foreach (__('developers.nav') as $anchor => $label)
            <a href="#{{ $anchor }}" class="rounded-md border border-seam px-3 py-1.5 text-moss hover:border-amber hover:text-bone">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="mt-10 max-w-4xl space-y-14">
        <section id="callback" aria-labelledby="h-callback">
            <h2 id="h-callback" class="font-display text-2xl font-bold">{{ __('developers.callback.title') }}</h2>
            <p class="mt-3 text-moss">{{ __('developers.callback.lead') }}</p>
            <pre class="{{ $codeClass }}"><code>POST https://your-server.example/vote
Content-Type: application/json
X-Graj-Signature: 5d41402abc4b2a76b9719d911017c592…

{"server_id": 42, "vote_id": 18731, "nickname": "Steve", "ip": "203.0.113.7", "timestamp": {{ now()->timestamp }}}</code></pre>
            <p class="mt-4 text-moss">{{ __('developers.callback.signature') }}</p>
            <pre class="{{ $codeClass }}"><code>&lt;?php
$secret = 'КЛЮЧ_СЕРВЕРА';
$body = file_get_contents('php://input');
$expected = hash_hmac('sha256', $body, $secret);

if (! hash_equals($expected, $_SERVER['HTTP_X_GRAJ_SIGNATURE'] ?? '')) {
    http_response_code(403);
    exit;
}

$vote = json_decode($body, true);
give_reward($vote['nickname']); // ваша видача бонусу
http_response_code(200);</code></pre>
            <p class="mt-3 text-sm text-moss">{{ __('developers.callback.interval', ['n' => $interval]) }}</p>
        </section>

        <section id="api" aria-labelledby="h-api">
            <h2 id="h-api" class="font-display text-2xl font-bold">{{ __('developers.api.title') }}</h2>
            <p class="mt-3 text-moss">{{ __('developers.api.lead') }}</p>
            <p class="mt-3"><code class="rounded bg-slab px-2 py-1 text-sm">{{ $base }}</code></p>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[36rem] text-sm">
                    <thead><tr class="border-b border-bone/80 text-left text-moss">
                        <th class="py-2 pr-4 font-normal">{{ __('developers.api.method') }}</th><th class="py-2 pr-4 font-normal">{{ __('developers.api.path') }}</th>
                        <th class="py-2 font-normal">{{ __('developers.api.about') }}</th><th class="py-2 font-normal">{{ __('developers.api.key') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($endpoints as $e)
                            <tr class="border-b border-seam">
                                <td class="py-3 pr-4 font-mono text-xs font-bold {{ $e['method'] === 'GET' ? 'text-signal' : 'text-amber' }}">{{ $e['method'] }}</td>
                                <td class="py-3 pr-4 font-mono text-xs whitespace-nowrap">{{ $e['path'] }}</td>
                                <td class="py-3">{{ $e['summary'] }}</td>
                                <td class="py-3 text-moss">{{ $e['key'] ? __('developers.yes') : __('developers.no') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <pre class="{{ $codeClass }}"><code>curl {{ $base }}/servers/42</code></pre>
        </section>

        <section id="check" aria-labelledby="h-check">
            <h2 id="h-check" class="font-display text-2xl font-bold">{{ __('developers.check.title') }}</h2>
            <p class="mt-3 text-moss">{{ __('developers.check.lead', ['n' => $interval]) }}</p>
            <pre class="{{ $codeClass }}"><code>curl -H "Authorization: Bearer КЛЮЧ_СЕРВЕРА" \
  "{{ $base }}/servers/42/votes/check?nickname=Steve"

{"voted": true, "vote_id": 18731, "voted_at": "{{ now()->subHour()->toIso8601String() }}"}</code></pre>
        </section>

        <section id="online" aria-labelledby="h-online">
            <h2 id="h-online" class="font-display text-2xl font-bold">{{ __('developers.online.title') }}</h2>
            <p class="mt-3 text-moss">{{ __('developers.online.lead') }}</p>
            <pre class="{{ $codeClass }}"><code>curl -X POST -H "Authorization: Bearer КЛЮЧ_СЕРВЕРА" -H "Content-Type: application/json" \
  -d '{"online": true, "players": 312, "max_players": 1000}' \
  {{ $base }}/servers/42/online</code></pre>
        </section>

        <section id="widgets" aria-labelledby="h-widgets">
            <h2 id="h-widgets" class="font-display text-2xl font-bold">{{ __('developers.widgets.title') }}</h2>
            <p class="mt-3 text-moss">{{ __('developers.widgets.lead') }}</p>
            @if ($sample)
                <div class="mt-5 grid gap-6 sm:grid-cols-2">
                    <figure>
                        <figcaption class="text-sm font-medium">{{ __('developers.widgets.vote') }}</figcaption>
                        <div class="mt-3 flex flex-wrap items-end gap-3">
                            @foreach (array_keys(\App\Http\Controllers\WidgetController::VOTE_SIZES) as $size)
                                <img src="{{ route('widgets.vote', ['server' => $sample->id, 'size' => $size]) }}" alt="{{ $size }}" loading="lazy">
                            @endforeach
                        </div>
                    </figure>
                    <figure>
                        <figcaption class="text-sm font-medium">{{ __('developers.widgets.status') }}</figcaption>
                        <div class="mt-3 space-y-3">
                            @foreach (array_keys(\App\Http\Controllers\WidgetController::STATUS_SIZES) as $size)
                                <img src="{{ route('widgets.status', ['server' => $sample->id, 'size' => $size]) }}" alt="{{ $size }}" class="max-w-full" loading="lazy">
                            @endforeach
                        </div>
                    </figure>
                </div>
            @endif
            <pre class="{{ $codeClass }}"><code>&lt;a href="{{ url('/server/your-server/vote') }}"&gt;&lt;img src="{{ url('/widget/42/vote.svg?size=120x40') }}" alt="Голосувати" width="120" height="40"&gt;&lt;/a&gt;</code></pre>
        </section>

        <section id="openapi" aria-labelledby="h-openapi">
            <h2 id="h-openapi" class="font-display text-2xl font-bold">{{ __('developers.openapi.title') }}</h2>
            <p class="mt-3 text-moss">{{ __('developers.openapi.lead') }}</p>
            <x-button class="mt-4" variant="secondary" :href="route('developers.openapi')">{{ __('developers.openapi.download') }}</x-button>
        </section>
    </div>
</x-layouts.app>
