<?php

namespace App\Livewire\Cabinet;

use App\Models\Game;
use App\Models\Server;
use App\Rules\PublicAddress;
use App\Servers\ServerSubmission;
use App\Support\HtmlSanitizer;
use App\Support\Locales;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * @property-read Collection<int, Game> $games
 * @property-read Game|null $game
 */
#[Title('Сервер | graj.club')]
class ServerForm extends Component
{
    use WithFileUploads;

    public ?Server $server = null;

    public ?int $game_id = null;

    public string $name = '';

    public string $host = '';

    public ?int $port = null;

    public string $website = '';

    public string $discord = '';

    public string $youtube = '';

    /** Опис мовами сайту: код мови => HTML */
    public array $descriptions = [];

    public ?string $opens_at = null;

    /** @var array<int, string> чекбокси Livewire можуть залишати пропуски в ключах */
    public array $languages = [];

    public ?string $country = null;

    /** @var array<string, string|array> */
    public array $fields = [];

    public $logo;

    public $banner;

    public array $screenshots = [];

    public function mount(?Server $server = null): void
    {
        if ($server?->exists) {
            abort_unless(auth()->user()->canManage($server), 403);

            $this->server = $server;
            $pending = $server->pending_changes ?? [];
            $this->game_id = $pending['game_id'] ?? $server->game_id;
            $this->name = $pending['name'] ?? $server->name;
            $this->host = $pending['host'] ?? $server->host;
            $this->port = $pending['port'] ?? $server->port;
            $this->website = (string) $server->website;
            $this->discord = (string) $server->discord;
            $this->youtube = (string) $server->youtube;
            foreach (array_keys(Locales::all()) as $locale) {
                $this->descriptions[$locale] = (string) $server->getTranslation('description', $locale, false);
            }
            $this->opens_at = $server->opens_at?->toDateString();
            $this->languages = $server->languages ?? [];
            $this->country = $server->country;
            $this->fields = $server->fieldValues()->with('field')->get()
                ->groupBy(fn ($v) => $v->field->key)
                ->map(fn ($values, $key) => match ($values->first()->field->type) {
                    'multiselect' => $values->pluck('value')->all(),
                    'boolean' => $values->first()->value === '1',
                    default => $values->first()->value,
                })
                ->all();
        } else {
            $this->game_id = Game::where('slug', request('game'))->value('id');
        }
    }

    #[Computed]
    public function games(): Collection
    {
        return Game::active()->get();
    }

    #[Computed]
    public function game(): ?Game
    {
        return $this->game_id ? Game::with('fields')->find($this->game_id) : null;
    }

    public function updatedGameId(): void
    {
        $this->fields = [];
        $this->port = $this->game?->default_port;
    }

    protected function rules(): array
    {
        $rules = [
            'game_id' => ['required', Rule::exists('games', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'min:3', 'max:64'],
            'host' => ['required', 'string', 'max:253', new PublicAddress],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'website' => ['nullable', 'url:http,https', 'max:255', new PublicAddress(url: true)],
            'discord' => ['nullable', 'url:https', 'max:255', 'regex:#^https://(discord\.gg|discord\.com/invite)/#'],
            'youtube' => ['nullable', 'url:https', 'max:255', 'regex:#^https://(www\.)?(youtube\.com|youtu\.be)/#'],
            // Опис основною мовою обов’язковий, іншими — за бажанням
            'descriptions.'.Locales::default() => ['required', 'string', 'max:20000', $this->visibleLength(50, 5000)],
            'descriptions.*' => ['nullable', 'string', 'max:20000', $this->visibleLength(0, 5000)],
            'opens_at' => ['nullable', 'date'],
            'languages' => ['array', 'max:6'],
            'languages.*' => [Rule::in(array_keys(self::LANGUAGES))],
            'country' => ['nullable', Rule::in(array_keys(self::COUNTRIES))],
            'logo' => ['nullable', 'image', 'max:1024', 'dimensions:max_width=1024,max_height=1024'],
            'banner' => ['nullable', 'image', 'max:2048'],
            'screenshots' => ['array', 'max:'.config('graj.servers.max_screenshots')],
            'screenshots.*' => ['image', 'max:3072'],
        ];

        foreach ($this->game->fields ?? [] as $field) {
            $values = collect($field->options)->pluck('value')->all();
            if ($field->type === 'multiselect') {
                $rules["fields.{$field->key}.*"] = [Rule::in($values)];
            }
            $rules["fields.{$field->key}"] = match ($field->type) {
                'select' => [$field->is_required ? 'required' : 'nullable', Rule::in($values)],
                'multiselect' => [$field->is_required ? 'required' : 'nullable', 'array'],
                'boolean' => ['nullable', 'boolean'],
                'number' => [$field->is_required ? 'required' : 'nullable', 'integer'],
                default => [$field->is_required ? 'required' : 'nullable', 'string', 'max:255'],
            };
        }

        return $rules;
    }

    protected function validationAttributes(): array
    {
        return collect($this->game->fields ?? [])
            ->mapWithKeys(fn ($f) => ["fields.{$f->key}" => $f->label])
            ->merge(collect(Locales::all())->mapWithKeys(fn ($language, $code) => ["descriptions.{$code}" => __('cabinet.form.description_in', ['language' => $language])]))
            ->all();
    }

    public function save(ServerSubmission $submission)
    {
        $this->validate();

        $attributes = [
            'game_id' => $this->game_id,
            'name' => trim($this->name),
            'host' => strtolower(trim($this->host)),
            'port' => $this->port,
            'website' => $this->website ?: null,
            'discord' => $this->discord ?: null,
            'youtube' => $this->youtube ?: null,
            'description' => array_filter(collect(Locales::all())
                ->mapWithKeys(fn ($name, $locale) => [$locale => HtmlSanitizer::clean((string) ($this->descriptions[$locale] ?? ''))])
                ->all()),
            'opens_at' => $this->opens_at,
            'languages' => array_values($this->languages) ?: null,
            'country' => $this->country ?: null,
        ];

        if ($this->server) {
            $needsReview = $submission->update($this->server, $attributes, $this->fields);
            $server = $this->server;
        } else {
            $server = $submission->create(auth()->user(), $attributes, $this->fields);
            $needsReview = true;
        }

        if ($this->logo) {
            $submission->storeMedia($server, 'logo', $this->logo);
        }
        if ($this->banner) {
            $submission->storeMedia($server, 'banner', $this->banner);
        }
        foreach ($this->screenshots as $shot) {
            if ($server->media()->where('type', 'screenshot')->count() < config('graj.servers.max_screenshots')) {
                $submission->storeMedia($server, 'screenshot', $shot);
            }
        }

        session()->flash('status', $needsReview ? __('cabinet.saved_review') : __('cabinet.saved'));

        return $this->redirectRoute('cabinet.servers.show', $server);
    }

    public function deleteMedia(int $mediaId): void
    {
        abort_unless($this->server && auth()->user()->canManage($this->server), 403);

        $media = $this->server->media()->findOrFail($mediaId);
        Storage::disk('public')->delete($media->path);
        $media->delete();
    }

    /**
     * Довжина видимого тексту опису (без HTML-розмітки редактора).
     */
    private function visibleLength(int $min, int $max): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($min, $max) {
            $length = mb_strlen(HtmlSanitizer::text($value));
            if ($length < $min) {
                $fail(__('validation.min.string', ['attribute' => __('cabinet.form.description'), 'min' => $min]));
            } elseif ($length > $max) {
                $fail(__('validation.max.string', ['attribute' => __('cabinet.form.description'), 'max' => $max]));
            }
        };
    }

    public const LANGUAGES = ['uk' => 'Українська', 'en' => 'English', 'pl' => 'Polski', 'ru' => 'Русский', 'de' => 'Deutsch', 'cs' => 'Čeština', 'ro' => 'Română', 'kk' => 'Қазақша'];

    public const COUNTRIES = ['UA' => 'Україна', 'PL' => 'Польща', 'DE' => 'Німеччина', 'NL' => 'Нідерланди', 'FR' => 'Франція', 'FI' => 'Фінляндія', 'CZ' => 'Чехія', 'RO' => 'Румунія', 'US' => 'США', 'KZ' => 'Казахстан', 'other' => 'Інша'];

    public function render()
    {
        return view('livewire.cabinet.server-form');
    }
}
