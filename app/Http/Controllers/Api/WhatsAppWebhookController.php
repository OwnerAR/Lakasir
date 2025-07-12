<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenants\OmniChannel\Message;
use App\Jobs\ProcessMessageQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handle incoming WhatsApp webhook request
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @throws \Illuminate\Validation\ValidationException
     * 
     * Required Headers:
     * - x-api-key: Your API key for authentication
     */
    public function handle(Request $request)
    {
        try {
            // Log incoming request for debugging
            Log::info('WhatsApp webhook request:', [
                'host' => $request->getHost(),
                'data' => $request->all()
            ]);

            // Validate the incoming webhook
            $request->validate([
                'number' => 'required|string',
                'name' => 'nullable|string',
                'message' => 'required|string',
                'message_type' => 'required|in:text,image,file',
                'media_url' => 'nullable|url',
            ]);

            // Create a new message
            $message = Message::create([
                'whatsapp_number' => $request->number,
                'customer_name' => $request->name,
                'message' => $request->message,
                'message_type' => $request->message_type,
                'media_url' => $request->media_url,
                'direction' => 'inbound',
                'status' => 'queued',
            ]);

            // Check if there's an existing conversation
            $existingChat = Message::where('whatsapp_number', $request->number)
                ->where('status', 'in_progress')
                ->first();

            if ($existingChat) {
                // Assign to the same agent
                $message->update([
                    'status' => 'in_progress',
                    'assigned_to' => $existingChat->assigned_to
                ]);
            } else {
                // Process the queue to assign to available agent
                ProcessMessageQueue::dispatch();
            }

            return response()->json([
                'success' => true,
                'message' => 'Message received successfully',
                'message_id' => $message->id
            ]);
        } catch (\Exception $e) {
            Log::error('WhatsApp webhook error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing message: ' . $e->getMessage()
            ], 500);
        }
    }
} 