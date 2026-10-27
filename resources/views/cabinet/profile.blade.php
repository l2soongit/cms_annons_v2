<x-layouts.app :title="__('profile.nav.profile').' | graj.club'">
    <x-cabinet-nav />
    <x-flash class="mb-6" />

    <div class="grid max-w-4xl gap-x-12 gap-y-12 lg:grid-cols-2">
        <section aria-labelledby="p-info">
            <h2 id="p-info" class="font-display text-lg font-medium">{{ __('profile.info_title') }}</h2>
            <form method="POST" action="{{ route('user-profile-information.update') }}" class="mt-5 space-y-5">
                @csrf @method('PUT')
                <x-field name="name" :label="__('auth.ui.name')" :value="$user->name" required />
                <x-field name="email" type="email" :label="__('auth.ui.email')" :value="$user->email" :hint="__('profile.email_hint')" required />
                <div>
                    <label for="locale" class="mb-1.5 block text-sm font-medium">{{ __('profile.locale') }}</label>
                    <select id="locale" name="locale" class="w-full rounded-md border border-seam bg-slab px-3 py-2.5 text-bone">
                        <option value="uk" @selected($user->locale === 'uk')>Українська</option>
                        <option value="en" @selected($user->locale === 'en')>English</option>
                    </select>
                    <p class="mt-1.5 text-xs text-moss">{{ __('profile.locale_hint') }}</p>
                </div>
                @foreach ($errors->updateProfileInformation->all() as $message)
                    <p class="text-sm text-fade">{{ $message }}</p>
                @endforeach
                <x-button>{{ __('profile.save') }}</x-button>
            </form>
        </section>

        <section aria-labelledby="p-password">
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

        <section aria-labelledby="p-social">
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

        <section aria-labelledby="p-delete">
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
</x-layouts.app>
