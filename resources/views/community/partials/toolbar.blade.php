{{-- Кнопки розмітки над полем: вставляють Markdown навколо виділеного тексту --}}
<div class="cm-tb" data-toolbar="{{ $for }}" role="toolbar" aria-label="{{ __('community.toolbar.aria') }}">
  <button type="button" data-wrap="**" title="{{ __('community.toolbar.bold') }}"><b>B</b></button>
  <button type="button" data-wrap="*" title="{{ __('community.toolbar.italic') }}"><i>I</i></button>
  <button type="button" data-line="> " title="{{ __('community.toolbar.quote') }}">❝</button>
  <button type="button" data-line="- " title="{{ __('community.toolbar.list') }}">•</button>
  @if ($headings ?? false)<button type="button" data-line="## " title="{{ __('community.toolbar.heading') }}">H</button>@endif
  <button type="button" data-link title="{{ __('community.toolbar.link') }}">🔗</button>
</div>
