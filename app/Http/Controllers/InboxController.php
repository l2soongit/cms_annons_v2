<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Сповіщення на сайті: список подій (модерація, послуги, повідомлення, відгуки, офлайн), відкриття позначає прочитаним.
 */
class InboxController extends Controller
{
    public function index(Request $request): View
    {
        return view('cabinet.inbox', [
            'notifications' => $request->user()->notifications()->paginate(30),
        ]);
    }

    /** Відкрити сповіщення: позначити прочитаним і перейти туди, куди воно веде */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        // Ведемо лише на сторінки цього сайту
        return is_string($url) && str_starts_with($url, url('/')) ? redirect()->to($url) : redirect()->route('cabinet.inbox');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', __('notifications.inbox.all_read'));
    }
}
