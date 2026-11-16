<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('marketplace.notifications', ['notifications' => $request->user()->notifications()->paginate(30)]);
    }

    /** Mark read and follow the link. Only the user's own notifications are reachable. */
    public function open(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? route('notifications.index');

        // Only follow links into this application.
        return redirect()->to(str_starts_with($url, url('/')) ? $url : route('notifications.index'));
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
