{{-- Фон-брендування форуму (рекламне місце forum_skin): картинка під сторінкою, клікабельна зверху й по боках.
     Показ рахується тут; сервер реєструється як уже показаний, щоб інші банери того ж сервера на сторінці не дублювалися --}}
@php
    $skin = \App\Models\Banner::live('forum_skin')->latest('starts_at')->first();
    if ($skin) {
        $skin->increment('impressions');
        request()->attributes->set('banner_servers', [...request()->attributes->get('banner_servers', []), $skin->server_id]);
        $skinUrl = \App\Support\HomePageData::url($skin->image);
    }
@endphp
@if ($skin)
  <a class="f-skin" href="{{ route('ads.click', $skin) }}" rel="sponsored noopener" target="_blank" tabindex="-1" aria-hidden="true" style="background-image: url('{{ $skinUrl }}')"></a>
  <a class="f-skinhead" href="{{ route('ads.click', $skin) }}" rel="sponsored noopener" target="_blank" aria-label="{{ __('billing.ads.sponsored') }}: {{ $skin->alt }}" style="background-image: url('{{ $skinUrl }}'); background-size: 0 0"><span>{{ __('community.forum.skin') }}</span></a>
@endif
