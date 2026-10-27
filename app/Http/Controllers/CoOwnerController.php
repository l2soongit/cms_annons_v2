<?php

namespace App\Http\Controllers;

use App\Models\ModerationLog;
use App\Models\Server;
use App\Models\ServerInvitation;
use App\Models\User;
use App\Notifications\CoOwnerInvited;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Співвласники сервера: запрошення за email, видалення, передача прав головного власника.
 * Керує списком лише головний власник.
 */
class CoOwnerController extends Controller
{
    public function invite(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeOwner($request, $server);

        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = Str::lower($data['email']);

        if ($email === Str::lower($server->owner->email) || $server->coOwners()->where('email', $email)->exists()) {
            return back()->with('error', __('owners.already'));
        }

        if ($server->coOwners()->count() + ServerInvitation::where('server_id', $server->id)->count() >= 10) {
            return back()->with('error', __('owners.limit'));
        }

        $token = Str::random(48);
        $invitation = ServerInvitation::updateOrCreate(
            ['server_id' => $server->id, 'email' => $email],
            ['invited_by' => $request->user()->id, 'token' => hash('sha256', $token), 'expires_at' => now()->addDays(7)],
        );

        Notification::route('mail', $email)->notify(new CoOwnerInvited($invitation, $token));

        return back()->with('status', __('owners.invited', ['email' => $email]));
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = ServerInvitation::with('server')->where('token', hash('sha256', $token))->first();

        if (! $invitation || $invitation->isExpired()) {
            return to_route('cabinet')->with('error', __('owners.invalid'));
        }

        if (Str::lower($request->user()->email) !== $invitation->email) {
            return to_route('cabinet')->with('error', __('owners.wrong_account', ['email' => $invitation->email]));
        }

        $invitation->server->coOwners()->syncWithoutDetaching([$request->user()->id]);
        $invitation->delete();

        if (! $request->user()->hasRole('owner')) {
            $request->user()->assignRole('owner');
        }

        return to_route('cabinet.servers.show', $invitation->server)->with('status', __('owners.accepted'));
    }

    public function remove(Request $request, Server $server, User $user): RedirectResponse
    {
        $this->authorizeOwner($request, $server);

        $server->coOwners()->detach($user->id);

        return back()->with('status', __('owners.removed'));
    }

    public function cancelInvitation(Request $request, Server $server, ServerInvitation $invitation): RedirectResponse
    {
        $this->authorizeOwner($request, $server);
        abort_unless($invitation->server_id === $server->id, 404);

        $invitation->delete();

        return back()->with('status', __('owners.cancelled'));
    }

    public function transfer(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeOwner($request, $server);

        $request->validate([
            'user_id' => ['required', 'integer'],
            'password' => ['required', 'current_password'],
        ]);

        $newOwner = $server->coOwners()->whereKey($request->integer('user_id'))->firstOrFail();
        $oldOwner = $request->user();

        DB::transaction(function () use ($server, $newOwner, $oldOwner) {
            $server->coOwners()->detach($newOwner->id);
            $server->coOwners()->syncWithoutDetaching([$oldOwner->id]);
            $server->forceFill(['user_id' => $newOwner->id])->save();
        });

        ModerationLog::create([
            'moderator_id' => null,
            'subject_type' => $server->getMorphClass(),
            'subject_id' => $server->id,
            'action' => 'ownership_transfer',
            'changes' => ['from' => $oldOwner->id, 'to' => $newOwner->id],
        ]);

        return to_route('cabinet.servers.show', $server)->with('status', __('owners.transferred', ['name' => $newOwner->name]));
    }

    private function authorizeOwner(Request $request, Server $server): void
    {
        abort_unless($server->user_id === $request->user()->id, 403);
    }
}
