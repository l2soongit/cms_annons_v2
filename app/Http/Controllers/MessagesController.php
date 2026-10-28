<?php

namespace App\Http\Controllers;

use App\Messaging\MessagingException;
use App\Messaging\Messenger;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Приватні повідомлення в кабінеті: список діалогів, діалог, «Написати власнику» з картки сервера, вкладення.
 */
class MessagesController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('cabinet.messages.index', [
            'user' => $user,
            'conversations' => Conversation::directFor($user)->with(['participants', 'server', 'messages' => fn ($q) => $q->reorder('id', 'desc')->limit(1)])->paginate(30),
        ]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $conversation->load(['participants', 'server', 'ticket']);
        abort_unless($conversation->participants->contains($request->user()) && ! $conversation->ticket, 404);

        return view('cabinet.messages.show', [
            'conversation' => $conversation,
            'other' => $conversation->other($request->user()),
            'user' => $request->user(),
            // Список діалогів зліва від чату, як у демо кабінету
            'conversations' => Conversation::directFor($request->user())->with(['participants', 'server', 'messages' => fn ($q) => $q->reorder('id', 'desc')->limit(1)])->limit(30)->get(),
        ]);
    }

    public function start(Request $request, Server $server, Messenger $messenger): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'attachment' => ['nullable', 'image', 'max:5120'],
        ]);

        try {
            $conversation = $messenger->start($request->user(), $server, $data['body'], $request->file('attachment'));
        } catch (MessagingException $e) {
            return back()->withInput()->withErrors(['body' => $e->getMessage()], 'message');
        }

        return redirect()->route('cabinet.messages.show', $conversation);
    }

    /** Вкладення зберігаються приватно: віддаємо лише учасникам діалогу й підтримці (для тікетів) */
    public function attachment(Request $request, Message $message): StreamedResponse
    {
        $conversation = $message->conversation()->with(['participants', 'ticket'])->first();
        $user = $request->user();
        $allowed = $conversation->participants->contains($user)
            || ($conversation->ticket && $user->can('tickets.manage'))
            || ($user->can('reports.moderate') && $message->reports()->exists());
        abort_unless($allowed && $message->attachment && Storage::disk('local')->exists($message->attachment), 404);

        return Storage::disk('local')->response($message->attachment, null, ['Cache-Control' => 'private, max-age=86400']);
    }
}
