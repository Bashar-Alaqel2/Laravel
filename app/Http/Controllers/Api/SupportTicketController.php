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
        
        // If the user is maintenance or admin, they might see all tickets.
        // For now, we return tickets for the authenticated user (ScreenOwner).
        $tickets = SupportTicket::where('user_id', $user->user_id)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($tickets);
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:120',
            'category' => 'required|string|max:50',
            'priority' => 'required|in:low,medium,high,urgent',
            'description' => 'required|string',
        ]);

        $ticket = SupportTicket::create([
            'user_id' => $request->user()->user_id,
            'subject' => $request->subject,
            'category' => $request->category,
            'priority' => $request->priority,
            'description' => $request->description,
            'status' => 'open'
        ]);

        return response()->json([
            'message' => 'تم إنشاء التذكرة بنجاح',
            'ticket' => $ticket
        ], 201);
    }

    public function show($id, Request $request)
    {
        $ticket = SupportTicket::where('id', $id)
            ->where('user_id', $request->user()->user_id)
            ->firstOrFail();
            
        return response()->json($ticket);
    }
}
