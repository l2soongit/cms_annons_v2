<?php

namespace App\Livewire\Cabinet;

use App\Models\Game;
use App\Models\Server;
use App\Rules\PublicAddress;
use App\Servers\ServerSubmission;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

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

    public string $description_uk = '';

    public string $description_en = '';

    public ?string $opens_at = null;

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
            $this->description_uk = (string) $server->getTranslation('description', 'uk', false);
            $this->description_en = (string) $server->getTranslation('description', 'en', false);
            $this->opens_at = $server->opens_at?->toDateString();
            $this->fields = $server->fieldValues()->with('field')->get()
                ->groupBy(fn ($v) => $v->field->key)
                ->map(fn ($values, $key) => $values->first()->field->type === 'multiselect'
                    ? $values->pluck('value')->all()
                    : $values->first()->value)
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
            'description_uk' => ['required', 'string', 'min:50', 'max:5000'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'opens_at' => ['nullable', 'date'],
            'logo' => ['nullable', 'image', 'max:1024', 'dimensions:max_width=1024,max_height=1024'],
            'banner' => ['nullable', 'image', 'max:2048'],
            'screenshots' => ['array', 'max:'.config('graj.servers.max_screenshots')],
            'screenshots.*' => ['image', 'max:3072'],
        ];

        foreach ($this->game?->fields ?? [] as $field) {
            $values = collect($field->options)->pluck('value')->all();
            $rules["fields.{$field->key}"] = match ($field->type) {
                'select' => [$field->is_required ? 'required' : 'nullable', Rule::in($values)],
                'multiselect' => [$field->is_required ? 'required' : 'nullable', 'array'],
                'number' => [$field->is_required ? 'required' : 'nullable', 'integer'],
                default => [$field->is_required ? 'required' : 'nullable', 'string', 'max:255'],
            };
        }

        return $rules;
    }

    protected function validationAttributes(): array
    {
        return collect($this->game?->fields ?? [])
            ->mapWithKeys(fn ($f) => ["fields.{$f->key}" => $f->label])
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
            'description' => array_filter(['uk' => strip_tags($this->description_uk), 'en' => strip_tags($this->description_en)]),
            'opens_at' => $this->opens_at,
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

    public function render()
    {
        return view('livewire.cabinet.server-form');
    }
}
