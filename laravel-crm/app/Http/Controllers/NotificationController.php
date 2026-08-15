<?php

namespace App\Http\Controllers;

use App\Services\CrmNotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, CrmNotificationService $notifications)
    {
        $notifications->syncDueActivityNotificationsFor($request->user());

        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->latest()->paginate(15),
        ]);
    }

    public function markRead(Request $request, string $notification)
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return back()->with('status', 'Notification marked as read.');
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }
}
