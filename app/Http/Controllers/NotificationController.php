<?php

namespace App\Http\Controllers;

use App\Services\ActivityNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private readonly ActivityNotificationService $notifications)
    {
    }

    public function index(Request $request): View
    {
        $activities = $this->notifications->allForUser($request->user(), 100);
        $unreadCount = $activities->where('read', false)->count();

        return view('pages.notifications.index', [
            'title' => 'Notifikasi',
            'activities' => $activities,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $markedCount = $this->notifications->markAllAsRead($request->user());

        return back()->with('status', $markedCount > 0
            ? number_format($markedCount, 0, ',', '.').' notifikasi ditandai sudah dibaca.'
            : 'Semua notifikasi sudah dibaca.'
        );
    }
}
