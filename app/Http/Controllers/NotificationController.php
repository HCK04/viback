<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->orderBy('created_at', 'desc')->get();
        
        // If you're not using Laravel's native notifications table, you can return an empty array
        return response()->json($notifications);
    }
    
    /**
     * Mark a notification as read
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function markAsRead($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();
        
        return response()->json(['message' => 'Notification marked as read']);
    }
    
    /**
     * Mark all notifications as read
     *
     * @return \Illuminate\Http\Response
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();
        
        return response()->json(['message' => 'All notifications marked as read']);
    }
}
