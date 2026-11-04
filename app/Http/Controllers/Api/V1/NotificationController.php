<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;

class NotificationController
{
    public function index(Request $request)
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->paginate(min((int) $request->input('per_page', 20), 50));

        return response()->json([
            'data' => collect($notifications->items())->map(fn ($notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Notification',
                'detail' => $notification->data['detail'] ?? '',
                'type' => $notification->data['type'] ?? 'info',
                'href' => $notification->data['href'] ?? '/notifications',
                'read_at' => $notification->read_at?->toISOString(),
                'created_at' => $notification->created_at?->toISOString(),
            ])->values(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
                'unread' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    public function read(Request $request, string $notification)
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
