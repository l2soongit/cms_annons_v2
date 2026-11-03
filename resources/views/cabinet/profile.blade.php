<x-layouts.cabinet :title="__('profile.nav.profile').' | graj.club'" :heading="__('profile.nav.profile')" :subtitle="__('profile.lead')">
    <x-flash class="mb-6" />

    <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
        <section class="c-card" aria-labelledby="p-info">
            <h2 id="p-info" class="font-display text-lg font-medium">{{ __('profile.info_title') }}</h2>
            <form method="POST" action="{{ route('user-profile-information.update') }}" class="mt-5 space-y-5">
                @csrf @method('PUT')
                <x-field name="name" :label="__('auth.ui.name')" :value="$user->name" required />
                <x-field name="email" type="email" :label="__('auth.ui.email')" :value="$user->email" :hint="__('profile.email_hint')" required />
                <div>
                    <label for="locale" class="mb-1.5 block text-sm font-medium">{{ __('profile.locale') }}</label>
                    <select id="locale" name="locale" class="w-full rounded-md border border-seam bg-slab px-3 py-2.5 text-bone">
                        @foreach (\App\Support\Locales::all() as $code => $name)
                            <option value="{{ $code }}" @selected($user->locale === $code)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-moss">{{ __('profile.locale_hint') }}</p>
                </div>
                @foreach ($errors->updateProfileInformation->all() as $message)
                    <p class="text-sm text-fade">{{ $message }}</p>
                @endforeach
                <x-button>{{ __('profile.save') }}</x-button>
            </form>
        </section>

        @php
            $sigPlus = \App\Community\Perks::has($user, 'signature_plus');
            $gifOk = \App\Community\Perks::has($user, 'gif_avatar');
            $avatarUrl = $user->avatar ? (str_starts_with($user->avatar, 'http') ? $user->avatar : \App\Support\HomePageData::url($user->avatar)) : null;
            $perkKeys = ['title', 'name_effect', 'name_color', 'frame', 'badge', 'card_bg', 'signature_plus', 'gif_avatar'];
            $activePerks = collect($perkKeys)->filter(fn ($k) => \App\Community\Perks::has($user, $k));
        @endphp
        <section class="c-card" aria-labelledby="p-forum">
            <h2 id="p-forum" class="font-display text-lg font-medium">{{ __('profile.forum.title') }}</h2>
            <p class="mt-1 text-sm text-moss">{{ __('profile.forum.lead') }}</p>
            <form method="POST" action="{{ route('cabinet.profile.forum') }}" enctype="multipart/form-data" class="mt-5 space-y-5">
                @csrf @method('PUT')
                <div>
                    <label for="avatar" class="mb-1.5 block text-sm font-medium">{{ __('profile.forum.avatar') }}</label>
                    <div class="flex items-center gap-4">
                        @if ($avatarUrl)<img src="{{ $avatarUrl }}" alt="" width="64" height="64" style="width:64px;height:64px;border-radius:50%;object-fit:cover">@endif
                        <input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" class="text-sm">
                    </div>
                    <p class="mt-1.5 text-xs text-moss">{{ $gifOk ? __('profile.forum.avatar_hint_gif') : __('profile.forum.avatar_hint') }}</p>
                    @if ($user->avatar)<label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_avatar" value="1"> {{ __('profile.forum.remove') }}</label>@endif
                    @error('avatar')<p class="mt-1 text-sm text-fade">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="signature" class="mb-1.5 block text-sm font-medium">{{ __('profile.forum.signature') }}</label>
                    <textarea id="signature" name="signature" rows="3" maxlength="{{ $sigPlus ? 500 : 200 }}" class="w-full rounded-md border border-seam bg-slab px-3 py-2.5 text-bone">{{ old('signature', $user->signature) }}</textarea>
                    <p class="mt-1.5 text-xs text-moss">{{ $sigPlus ? __('profile.forum.signature_hint_plus') : __('profile.forum.signature_hint') }}</p>
                    @error('signature')<p class="mt-1 text-sm text-fade">{{ $message }}</p>@enderror
                </div>
                @if ($sigPlus)
                    <div>
                        <label for="signature_image" class="mb-1.5 block text-sm font-medium">{{ __('profile.forum.signature_image') }}</label>
                        @if ($user->signature_image)<img src="{{ \App\Support\HomePageData::url($user->signature_image) }}" alt="" style="max-height:60px;border-radius:6px;margin-bottom:6px">@endif
                        <input id="signature_image" type="file" name="signature_image" accept="image/jpeg,image/png,image/webp,image/gif" class="text-sm">
                        <p class="mt-1.5 text-xs text-moss">{{ __('profile.forum.signature_image_hint') }}</p>
                        @if ($user->signature_image)<label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_signature_image" value="1"> {{ __('profile.forum.remove') }}</label>@endif
                        @error('signature_image')<p class="mt-1 text-sm text-fade">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div class="text-sm">
                    <b>{{ __('profile.forum.perks') }}:</b>
                    @if ($activePerks->isEmpty())<span class="text-moss">{{ __('profile.forum.no_perks') }}</span>
                    @else<span class="text-moss">{{ $activePerks->map(fn ($k) => __('profile.forum.perk_'.$k))->join(', ') }}</span>@endif
                    · <a href="{{ route('cabinet.bonus') }}" style="color:#fbbf24;font-weight:700">{{ __('profile.forum.shop') }} →</a>
                </div>
                <x-button>{{ __('profile.save') }}</x-button>
            </form>
        </section>

        <section class="c-card" aria-labelledby="p-password">
            <h2 id="p-password" class="font-display text-lg font-medium">{{ __('profile.password_title') }}</h2>
            <form method="POST" action="{{ route('user-password.update') }}" class="mt-5 space-y-5">
                @csrf @method('PUT')
                <x-field name="current_password" type="password" :label="__('profile.current_password')" autocomplete="current-password" required />
                <x-field name="password" type="password" :label="__('auth.ui.new_password')" :hint="__('auth.ui.password_hint')" autocomplete="new-password" required />
                <x-field name="password_confirmation" type="password" :label="__('auth.ui.password_confirm')" autocomplete="new-password" required />
                @foreach ($errors->updatePassword->all() as $message)
                    <p class="text-sm text-fade">{{ $message }}</p>
                @endforeach
                <x-button variant="secondary">{{ __('profile.change_password') }}</x-button>
            </form>
        </section>

        <section class="c-card" aria-labelledby="p-social">
            <h2 id="p-social" class="font-display text-lg font-medium">{{ __('profile.social_title') }}</h2>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach (['google' => 'Google', 'discord' => 'Discord'] as $provider => $label)
                    <li class="flex justify-between gap-4 border-b border-seam py-2">
                        <span>{{ $label }}</span>
                        <span class="text-moss">{{ $user->{$provider.'_id'} ? __('profile.social_linked') : __('profile.social_not_linked') }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-moss">{{ __('profile.social_hint') }}</p>
        </section>

        <section class="c-card" aria-labelledby="p-delete">
            <h2 id="p-delete" class="font-display text-lg font-medium text-fade">{{ __('profile.delete_title') }}</h2>
            <p class="mt-3 text-sm text-moss">{{ __('profile.delete_lead') }}</p>
            <form method="POST" action="{{ route('cabinet.profile.destroy') }}" class="mt-4 space-y-4" onsubmit="return confirm(@js(__('profile.delete_confirm')))">
                @csrf @method('DELETE')
                <x-field name="password" id="delete-password" type="password" :label="__('auth.ui.password')" autocomplete="current-password" required />
                @foreach ($errors->deleteAccount->all() as $message)
                    <p class="text-sm text-fade">{{ $message }}</p>
                @endforeach
                <x-button variant="danger">{{ __('profile.delete') }}</x-button>
            </form>
        </section>
    </div>
</x-layouts.cabinet>
