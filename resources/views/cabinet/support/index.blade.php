<x-layouts.cabinet :heading="__('messages.support.title')" :subtitle="__('messages.support.lead')">
    <x-flash class="mb-6" />

    <div class="c-cols-side">
        <div class="c-card c-inbox" style="grid-template-columns:minmax(0,1fr);min-height:0">
            @include('cabinet.support._tickets', ['tickets' => $tickets])
        </div>

        <section class="c-card" id="t-new" aria-labelledby="t-new-title" style="scroll-margin-top:16px">
            <h2 id="t-new-title">{{ __('messages.support.new') }}</h2>
            <form method="POST" action="{{ route('cabinet.support.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="c-field">
                    <label for="t-category">{{ __('messages.support.category') }}</label>
                    <div class="c-inp"><select id="t-category" name="category" required>
                        @foreach (\App\Models\Ticket::CATEGORIES as $category)
                            <option value="{{ $category }}" @selected(old('category') === $category)>{{ __("messages.support.categories.{$category}") }}</option>
                        @endforeach
                    </select></div>
                </div>
                <div class="c-field">
                    <label for="t-subject">{{ __('messages.support.subject') }}</label>
                    <div class="c-inp"><input id="t-subject" name="subject" maxlength="120" value="{{ old('subject') }}" required></div>
                    @error('subject')<p class="c-err">{{ $message }}</p>@enderror
                </div>
                <div class="c-field">
                    <label for="t-body">{{ __('messages.support.body') }}</label>
                    <div class="c-inp"><textarea id="t-body" name="body" rows="6" maxlength="2000" required style="flex:1;min-width:0;border:0;background:none;color:inherit;outline:none;resize:vertical;font:inherit">{{ old('body') }}</textarea></div>
                    @error('body')<p class="c-err">{{ $message }}</p>@enderror
                </div>
                <label class="c-drop" data-drop>
                    <img alt="" hidden data-preview>
                    <b>{{ __('messages.attach') }}</b><br><span class="c-faint">{{ __('messages.attach_hint') }}</span>
                    <input type="file" name="attachment" accept="image/*" data-file>
                </label>
                @error('attachment')<p class="c-err" style="margin-top:8px">{{ $message }}</p>@enderror
                <button class="c-btn is-go" style="width:100%;margin-top:14px">{{ __('messages.support.submit') }}</button>
            </form>
        </section>
    </div>

    <x-slot:scripts>
        <script>
            // Прев'ю вкладеного зображення
            document.querySelector('[data-file]')?.addEventListener('change', (e) => {
                const img = document.querySelector('[data-preview]'), file = e.target.files[0];
                img.hidden = !file; if (file) img.src = URL.createObjectURL(file);
            });
        </script>
    </x-slot:scripts>
</x-layouts.cabinet>
