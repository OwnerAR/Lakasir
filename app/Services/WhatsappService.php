<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;


class WhatsappService
{
    protected $baseUrl;
    protected $token;

    public function __construct()
    {
        $this->baseUrl = config('services.whatsapp.base_url'); // e.g., https://api.whatsapp.com
        $this->token = config('services.whatsapp.token');
    }

    /**
     * Get WhatsApp API status.
     *
     * @return array
     */
    public function getStatus()
    {
        $response = Http::withToken($this->token)
            ->get("{$this->baseUrl}/api/status");

        return $response->json();
    }

    /**
     * Send a WhatsApp message.
     *
     * @param string $to
     * @param string $message
     * @return array
     */
    public function sendMessage(string $to, string $message)
    {
        $payload = [
            'to' => $to,
            'message' => $message,
        ];

        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/api/send", $payload);

        return $response->json();
    }
}