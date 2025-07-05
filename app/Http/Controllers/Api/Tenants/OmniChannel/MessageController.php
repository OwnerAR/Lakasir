<?php

namespace App\Http\Controllers\Api\Tenants\OmniChannel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Tenants\OmniChannel\MessageService;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    protected MessageService $messageService;

    public function __construct(MessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    /**
     * API endpoint untuk menerima pesan dari user (omni channel)
     * POST /api/tenants/omni/messages
     * Body: { "user_id": int, "message": string }
     */
    public function receive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'message' => 'required|string',
        ]);

        $ticket = $this->messageService->handleIncomingMessage($validated['user_id'], $validated['message']);

        return response()->json([
            'success' => true,
            'ticket_id' => $ticket->id,
            'message' => 'Pesan diterima dan diteruskan ke agent.'
        ]);
    }
}
