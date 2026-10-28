<x-layouts.app :title="$page->title.' | graj.club'" :description="\Illuminate\Support\Str::limit(strip_tags($body), 160)">
    <article class="max-w-3xl">
        <h1 class="font-display text-3xl font-bold tracking-tight sm:text-4xl">{{ $page->title }}</h1>
        <div class="mt-6 space-y-4 leading-relaxed text-bone/90 [&_a]:text-amber [&_a]:underline [&_h2]:mt-8 [&_h2]:font-display [&_h2]:text-xl [&_h2]:font-bold [&_li]:ml-5 [&_ol]:list-decimal [&_ul]:list-disc">
            {!! $body !!}
        </div>
        <p class="mt-10 text-xs text-moss">{{ __('ui.page_updated', ['date' => $page->updated_at->translatedFormat('j F Y')]) }}</p>
    </article>
</x-layouts.app>
