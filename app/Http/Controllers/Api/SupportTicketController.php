<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Smart Scoping:
        // ScreenOwner (role_id = 8) only gets their own tickets.
        // Admin, Maintenance, or others get all tickets.
        if ($user->role_id === 8 || ($user->role && $user->role->role_name === 'ScreenOwner')) {
            $tickets = SupportTicket::with('screen')->where('user_id', $user->user_id)
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $tickets = SupportTicket::with(['screen', 'user'])->orderBy('created_at', 'desc')->get();
        }
            
        return response()->json($tickets);
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:120',
            'category' => 'required|string|max:50',
            'priority' => 'required|in:low,medium,high,urgent',
            'description' => 'required|string',
            'screen_id' => 'nullable|exists:screens,screen_id'
        ]);

        $ticket = SupportTicket::create([
            'user_id' => $request->user()->user_id,
            'screen_id' => $request->screen_id,
            'subject' => $request->subject,
            'category' => $request->category,
            'priority' => $request->priority,
            'description' => $request->description,
            'status' => 'open'
        ]);

        event(new \App\Events\TicketUpdated($ticket, $ticket->user_id));

        return response()->json([
            'message' => 'تم إنشاء التذكرة بنجاح',
            'ticket' => $ticket
        ], 201);
    }

    public function show($id, Request $request)
    {
        $user = $request->user();
        
        if ($user->role_id === 8 || ($user->role && $user->role->role_name === 'ScreenOwner')) {
            $ticket = SupportTicket::with('screen')->where('id', $id)
                ->where('user_id', $user->user_id)
                ->firstOrFail();
        } else {
            $ticket = SupportTicket::with(['screen', 'user'])->findOrFail($id);
        }
            
        return response()->json($ticket);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        
        $ticket = SupportTicket::findOrFail($id);
        
        if ($user->role_id === 8 || ($user->role && $user->role->role_name === 'ScreenOwner')) {
            // Screen Owners should not update the admin_reply or status directly except maybe to close it.
            // But we can let them close it.
            if ($ticket->user_id !== $user->user_id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }
        
        $request->validate([
            'status' => 'nullable|in:open,in_progress,resolved,closed',
            'admin_reply' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,urgent'
        ]);

        if ($request->has('status')) $ticket->status = $request->status;
        if ($request->has('admin_reply')) $ticket->admin_reply = $request->admin_reply;
        if ($request->has('priority')) $ticket->priority = $request->priority;
        
        $ticket->save();

        event(new \App\Events\TicketUpdated($ticket, $ticket->user_id));

        return response()->json([
            'message' => 'تم تحديث التذكرة بنجاح',
            'ticket' => $ticket
        ], 200);
    }
}
