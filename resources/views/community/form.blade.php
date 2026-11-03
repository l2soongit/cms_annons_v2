@php
    $editing = $topic->exists;
    $poll = $topic->poll;
    $pollLocked = $poll && $poll->exists && $poll->votes()->exists();
    $options = old('poll_options', $poll?->options ?? ['', '', '']);
@endphp
<x-layouts.home :title="($editing ? __('community.form.edit_title') : __('community.form.create_title')).' | graj.club'" :assets="['resources/css/home.css', 'resources/css/community.css', 'resources/js/community.js']">
<div class="wrap cm cm-formpage">
  <nav class="cm-crumbs" aria-label="{{ __('ui.breadcrumbs') }}"><a href="{{ route('community') }}">{{ __('community.nav') }}</a><span aria-hidden="true">/</span><span>{{ $editing ? __('community.form.edit_title') : __('community.form.create_title') }}</span></nav>
  <h1 class="cm-h1">{{ $editing ? __('community.form.edit_title') : __('community.form.create_title') }}</h1>
  <p class="cm-meta cm-modnote">🛡 {{ __('community.form.moderation_note') }}</p>

  <form class="cm-card cm-form" method="post" enctype="multipart/form-data"
        action="{{ $editing ? route('community.update', $topic) : route('community.store') }}" data-types='@json($types)'>
    @csrf
    @if ($editing) @method('put') @endif

    <div class="grid2">
      <div class="field">
        <label for="f-category">{{ __('community.form.category') }}</label>
        <select id="f-category" name="category_id" required>
          <option value="">—</option>
          @foreach ($categories->groupBy('group') as $group => $items)
            <optgroup label="{{ __('community.groups.'.$group) }}">
              @foreach ($items as $c)<option value="{{ $c->id }}" @selected((int) old('category_id', $topic->category_id) === $c->id)>{{ $c->name }}</option>@endforeach
            </optgroup>
          @endforeach
        </select>
        @error('category_id')<p class="cm-error">{{ $message }}</p>@enderror
      </div>
      <div class="field">
        <label for="f-type">{{ __('community.form.type') }}</label>
        <select id="f-type" name="type" required>
          @foreach (array_keys(\App\Models\ForumTopic::TYPES) as $t)<option value="{{ $t }}" @selected(old('type', $topic->type) === $t)>{{ __('community.types.'.$t) }}</option>@endforeach
        </select>
        @error('type')<p class="cm-error">{{ $message }}</p>@enderror
      </div>
    </div>

    <div class="field" data-show-for="server_news">
      <label for="f-server">{{ __('community.form.server') }}</label>
      <select id="f-server" name="server_id">
        <option value="">—</option>
        @foreach ($servers as $s)<option value="{{ $s->id }}" @selected((int) old('server_id', $topic->server_id) === $s->id)>{{ $s->name }}</option>@endforeach
      </select>
      <p class="cm-meta">{{ $servers->isEmpty() ? __('community.form.no_servers') : __('community.form.server_help') }}</p>
      @error('server_id')<p class="cm-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
      <label for="f-title">{{ __('community.form.title_label') }}</label>
      <input id="f-title" name="title" required minlength="8" maxlength="160" value="{{ old('title', $topic->title) }}">
      @error('title')<p class="cm-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
      <label for="f-body">{{ __('community.form.body') }}</label>
      @include('community.partials.toolbar', ['for' => 'f-body', 'headings' => true])
      <textarea id="f-body" name="body" rows="14" required minlength="30" maxlength="20000">{{ old('body', $source) }}</textarea>
      <p class="cm-meta">{{ __('community.form.body_help') }}</p>
      @error('body')<p class="cm-error">{{ $message }}</p>@enderror
    </div>

    <fieldset class="field" data-show-for="clan">
      <legend>{{ __('community.form.clan') }}</legend>
      <div class="grid4">
        @foreach (['server', 'level', 'prime', 'language'] as $k)
          <div><label for="f-clan-{{ $k }}">{{ __('community.clan.'.$k) }}</label><input id="f-clan-{{ $k }}" name="clan_{{ $k }}" maxlength="80" value="{{ old('clan_'.$k, $topic->meta[$k] ?? '') }}"></div>
        @endforeach
      </div>
    </fieldset>

    <div class="field">
      <label for="f-cover">{{ __('community.form.cover') }}</label>
      <input id="f-cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp">
      <p class="cm-meta">{{ __('community.form.cover_help') }}</p>
      @error('cover')<p class="cm-error">{{ $message }}</p>@enderror
    </div>

    <details class="field cm-pollform" @if (old('poll_question', $poll?->question)) open @endif>
      <summary>📊 {{ __('community.form.poll') }}</summary>
      <label for="f-poll-q">{{ __('community.form.poll_question') }}</label>
      <input id="f-poll-q" name="poll_question" maxlength="200" value="{{ old('poll_question', $poll?->question) }}">
      @if ($pollLocked)<p class="cm-meta">{{ __('community.form.poll_locked') }}</p>@endif
      <div class="cm-polloptions" data-poll-options>
        @foreach ($options as $i => $opt)
          <input name="poll_options[]" maxlength="100" value="{{ $opt }}" aria-label="{{ __('community.form.poll_option', ['n' => $i + 1]) }}" placeholder="{{ __('community.form.poll_option', ['n' => $i + 1]) }}" @disabled($pollLocked)>
        @endforeach
      </div>
      @unless ($pollLocked)
        <button type="button" class="btn btn-ghost btn-sm" data-add-option data-label="{{ __('community.form.poll_option', ['n' => ':n']) }}">+ {{ __('community.form.poll_add') }}</button>
        <div class="grid2">
          <label class="check"><input type="checkbox" name="poll_multiple" value="1" @checked(old('poll_multiple', $poll?->multiple))> {{ __('community.form.poll_multiple') }}</label>
          <div><label for="f-poll-days">{{ __('community.form.poll_days') }}</label><input id="f-poll-days" type="number" name="poll_days" min="1" max="60" value="{{ old('poll_days') }}"></div>
        </div>
      @endunless
    </details>

    <div class="row">
      <a class="btn btn-ghost" href="{{ $editing ? route('community.topic', $topic) : route('community') }}">{{ __('community.cancel') }}</a>
      <button class="btn btn-hot">{{ $editing ? __('community.save') : __('community.form.submit') }}</button>
    </div>
  </form>
</div>
@include('community.partials.mobilebar')
</x-layouts.home>
