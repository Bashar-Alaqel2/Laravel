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
            ->where('is_read', 0)
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

        $notification->is_read = 1;
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
            ->where('is_read', 0)
            ->update(['is_read' => 1]);

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

    /**
     * Delete all read notifications.
     */
    public function deleteRead(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->user_id)
            ->where('is_read', 1)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم مسح ' . $count . ' إشعار مقروء بنجاح.'
        ]);
    }

    /**
     * Delete notifications older than a specific date.
     */
    public function archive(Request $request)
    {
        $request->validate([
            'months' => 'required|integer|in:1,3,6,12,24'
        ]);
        
        $cutoffDate = \Carbon\Carbon::now()->subMonths($request->months)->endOfDay();

        $count = Notification::where('user_id', $request->user()->user_id)
            ->where('created_at', '<=', $cutoffDate)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم مسح ' . $count . ' إشعار قديم بنجاح.'
        ]);
    }
}
