<?php

namespace App\Livewire\Messages;

use App\Messaging\MessagingException;
use App\Messaging\Messenger;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Report;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Стрічка діалогу (приватного чи тікета): нові повідомлення підтягуються опитуванням, відкриття позначає прочитаним.
 *
 * @property-read Conversation $conversation
 * @property-read Collection<int, Message> $thread
 */
class Thread extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $conversationId;

    public string $body = '';

    /** @var TemporaryUploadedFile|null */
    public $attachment = null;

    public string $reportReason = 'spam';

    public function mount(Conversation $conversation): void
    {
        abort_unless($conversation->hasParticipant(auth()->user()), 403);
        $this->conversationId = $conversation->id;
    }

    #[Computed]
    public function conversation(): Conversation
    {
        return Conversation::with(['participants', 'ticket', 'server'])->findOrFail($this->conversationId);
    }

    /** @return Collection<int, Message> */
    #[Computed]
    public function thread(): Collection
    {
        return $this->conversation->messages()->with('user')->get();
    }

    public function send(Messenger $messenger): void
    {
        $this->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'image', 'max:5120'],
        ]);

        try {
            $messenger->send($this->conversation, auth()->user(), $this->body, $this->attachment);
        } catch (MessagingException $e) {
            $this->addError('body', $e->getMessage());

            return;
        }

        $this->reset('body', 'attachment');
        unset($this->conversation, $this->thread);
    }

    public function toggleBlock(Messenger $messenger): void
    {
        $other = $this->conversation->other(auth()->user());
        abort_if(! $other || $this->conversation->ticket, 403);

        auth()->user()->hasBlocked($other) ? $messenger->unblock(auth()->user(), $other) : $messenger->block(auth()->user(), $other);
    }

    public function report(int $messageId): void
    {
        $this->validate(['reportReason' => ['required', Rule::in(Report::REASONS)]]);
        $message = $this->conversation->messages()->whereKey($messageId)->where('user_id', '!=', auth()->id())->firstOrFail();

        $message->reports()->firstOrCreate(['user_id' => auth()->id(), 'status' => 'open'], ['reason' => $this->reportReason]);
        session()->flash('status', __('messages.report_sent'));
    }

    public function render()
    {
        // Кожне опитування — це перегляд: позначаємо прочитаним
        app(Messenger::class)->markRead($this->conversation, auth()->user());

        return view('livewire.messages.thread', [
            'me' => auth()->user(),
            'other' => $this->conversation->other(auth()->user()),
        ]);
    }
}
