<?php

namespace App\View\Components\header;

use App\Services\ActivityNotificationService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class NotificationDropdown extends Component
{
    public array $activities;

    public int $attentionCount;

    public function __construct(ActivityNotificationService $notifications)
    {
        $user = auth()->user();
        $activities = $user ? $notifications->allForUser($user, 12) : collect();

        $this->activities = $activities->all();
        $this->attentionCount = $user ? $notifications->unreadCountForUser($user) : 0;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.header.notification-dropdown');
    }
}
