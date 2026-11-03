{{-- Підпис під повідомленням: текст — усім, картинка й посилання — з «плюшкою» магазину балів --}}
@php
    $sig = trim((string) $author->signature);
    $plus = \App\Community\Perks::has($author, 'signature_plus');
    $sigImg = $plus && $author->signature_image ? \App\Support\HomePageData::url($author->signature_image) : null;
@endphp
@if ($sig !== '' || $sigImg)
  <div class="f-sig">
    @if ($sigImg)<img src="{{ $sigImg }}" alt="" loading="lazy">@endif
    @if ($sig !== '')<span>@if ($plus){!! strip_tags(\App\Community\Markup::toHtml($sig), '<a><b><strong><i><em>') !!}@else{{ $sig }}@endif</span>@endif
  </div>
@endif
