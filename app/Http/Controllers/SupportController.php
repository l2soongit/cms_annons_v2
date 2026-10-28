<?php

namespace App\Http\Controllers;

use App\Messaging\MessagingException;
use App\Messaging\Messenger;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Тікети в підтримку (ТЗ 3.8): звернення з категорією, листування в діалозі тікета.
 */
class SupportController extends Controller
{
    public function index(Request $request): View
    {
        return view('cabinet.support.index', [
            'tickets' => $request->user()->hasMany(Ticket::class)->with('conversation.participants')->latest('updated_at')->get(),
        ]);
    }

    public function store(Request $request, Messenger $messenger): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(Ticket::CATEGORIES)],
            'subject' => ['required', 'string', 'min:5', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            'attachment' => ['nullable', 'image', 'max:5120'],
        ]);

        try {
            $ticket = $messenger->openTicket($request->user(), $data['category'], $data['subject'], $data['body'], $request->file('attachment'));
        } catch (MessagingException $e) {
            return back()->withInput()->withErrors(['body' => $e->getMessage()]);
        }

        return redirect()->route('cabinet.support.show', $ticket)->with('status', __('messages.support.created', ['id' => $ticket->id]));
    }

    public function show(Request $request, Ticket $ticket): View
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);

        return view('cabinet.support.show', [
            'ticket' => $ticket->load('conversation'),
            // Список звернень зліва від чату, як у демо кабінету
            'tickets' => $request->user()->hasMany(Ticket::class)->with('conversation.participants')->latest('updated_at')->get(),
        ]);
    }
}
