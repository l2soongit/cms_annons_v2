<?php

namespace App\Servers;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use App\Support\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Створення та редагування сервера власником.
 * Нові сервери йдуть на модерацію; зміна ключових полів опублікованого сервера
 * зберігається в pending_changes і застосовується лише після схвалення.
 */
class ServerSubmission
{
    /**
     * @param  array<string, mixed>  $attributes  поля таблиці servers
     * @param  array<string, string>  $fields  значення полів гри: key => value
     */
    public function create(User $user, array $attributes, array $fields): Server
    {
        return DB::transaction(function () use ($user, $attributes, $fields) {
            $server = new Server($attributes);
            $server->user_id = $user->id;
            $server->slug = $this->uniqueSlug($attributes['name']);
            $server->status = ServerStatus::Pending;
            $server->callback_secret = Str::random(40);
            $server->verify_token = Str::lower(Str::random(16));
            $server->save();

            $this->syncFields($server, $fields);

            if (! $user->hasRole('owner')) {
                $user->assignRole('owner');
            }

            return $server;
        });
    }

    /**
     * @return bool true, якщо зміни чекають модерації
     */
    public function update(Server $server, array $attributes, array $fields): bool
    {
        return DB::transaction(function () use ($server, $attributes, $fields) {
            $moderated = config('graj.servers.moderated_fields');
            $keyChanges = collect($attributes)
                ->only($moderated)
                ->filter(fn ($value, $key) => (string) $value !== (string) $server->getAttribute($key))
                ->all();

            $server->fill(collect($attributes)->except($moderated)->all());

            $needsReview = false;

            if ($server->status === ServerStatus::Published) {
                if ($keyChanges) {
                    $server->pending_changes = array_merge($server->pending_changes ?? [], $keyChanges);
                    $needsReview = true;
                }
            } else {
                // Чернетка, на модерації чи відхилений: правимо напряму і повертаємо в чергу
                $server->fill($keyChanges);
                if (in_array($server->status, [ServerStatus::Draft, ServerStatus::Rejected], true)) {
                    $server->status = ServerStatus::Pending;
                    $server->rejection_reason = null;
                }
                $needsReview = $server->status === ServerStatus::Pending;
            }

            $server->save();

            if (! isset($keyChanges['game_id']) || $server->status !== ServerStatus::Published) {
                $this->syncFields($server, $fields);
            }

            return $needsReview;
        });
    }

    /**
     * Застосувати відкладені зміни (викликає модератор).
     */
    public function applyPendingChanges(Server $server): void
    {
        if (! $server->pending_changes) {
            return;
        }

        $server->fill($server->pending_changes);
        $server->pending_changes = null;

        if ($server->isDirty(['host', 'port'])) {
            $server->verified_at = null; // нова адреса — власність треба підтвердити знову
        }

        $server->save();
    }

    /**
     * @param  array<string, string>  $fields
     */
    public function syncFields(Server $server, array $fields): void
    {
        $game = Game::with('fields')->find($server->game_id);
        $server->fieldValues()->delete();
        $driver = null;

        foreach ($game->fields as $field) {
            $value = $fields[$field->key] ?? null;
            if (blank($value)) {
                continue;
            }

            $values = $field->type === 'multiselect' ? (array) $value : [$value];
            foreach ($values as $v) {
                $server->fieldValues()->create(['game_field_id' => $field->id, 'value' => (string) $v]);
            }

            // Варіант поля може задавати протокол моніторингу (GTA: SA-MP / FiveM)
            $option = collect($field->options)->firstWhere('value', $value);
            $driver ??= $option['driver'] ?? null;
        }

        $server->forceFill(['query_driver' => $driver])->saveQuietly();
    }

    public function storeMedia(Server $server, string $type, UploadedFile $file): void
    {
        if (in_array($type, ['logo', 'banner'], true)) {
            foreach ($server->media()->where('type', $type)->get() as $old) {
                Storage::disk('public')->delete($old->path);
                $old->delete();
            }
        }

        $path = ImageOptimizer::store($file, "servers/{$server->id}", $type);

        $server->media()->create([
            'type' => $type,
            'path' => $path,
            'sort' => (int) $server->media()->where('type', $type)->max('sort') + 1,
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'server';
        $slug = $base;
        $i = 2;

        while (Server::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
