<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of the notifications for the authenticated user.
     */
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->user_id)
            ->orderBy('created_at', 'desc')
            ->get();

        $unreadCount = Notification::where('user_id', $request->user()->user_id)
            ->where('is_read', 'false')
            ->count();

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unread_count' => $unreadCount
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        $notification = Notification::where('user_id', $request->user()->user_id)
            ->where('notification_id', $id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'الإشعار غير موجود.'
            ], 404);
        }

        $notification->is_read = 'true';
        $notification->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديد الإشعار كمقروء.',
            'data' => $notification
        ]);
    }

    /**
     * Mark all notifications as read for the user.
     */
    public function markAllAsRead(Request $request)
    {
        Notification::where('user_id', $request->user()->user_id)
            ->where('is_read', 'false')
            ->update(['is_read' => 'true']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديد جميع الإشعارات كمقروءة.'
        ]);
    }

    /**
     * Remove the specified notification from storage.
     */
    public function destroy(Request $request, $id)
    {
        $notification = Notification::where('user_id', $request->user()->user_id)
            ->where('notification_id', $id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'الإشعار غير موجود.'
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الإشعار بنجاح.'
        ]);
    }
}
