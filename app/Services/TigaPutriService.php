<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Tenants\IntegrasiAPI;

class TigaPutriService
{
    protected string $type = '1';
    protected $credential;
    protected $baseurl;

    public function __construct()
    {
        $this->credential = IntegrasiAPI::where('type', $this->type)->first();
        $this->baseurl = $this->credential?->base_url ?? '';
    }

    private function createSignatureTrx(
        string $username,
        string $trxid,
        string $kodeproduk,
        string $tujuan,
        string $pin,
        string $password,
        string $jam
    ): string {
        $tujuanReversed = strrev($tujuan);
        $authReversed = strrev($pin . $password);
        $preSign = "TP256[{$username}|{$trxid}|{$kodeproduk}|{$tujuanReversed}|{$authReversed}|{$jam}]";
        $hash = hash('sha256', $preSign, true);
        $signature = rtrim(strtr(base64_encode($hash), '+/', '_-'), '=');
        return $signature;
    }

    private function createSignatureNonTrx(
        string $username,
        string $pin,
        string $password,
        string $jam
    ): string {
        $authReversed = strrev($pin . $password);
        $preSign = "TigaPutri256,[{$username}|{$authReversed}|{$jam}]";
        $hash = hash('sha256', $preSign, true);
        $signature = rtrim(strtr(base64_encode($hash), '+/', 'XZ'), '=');
        return $signature;
    }

    public function commandTransaction(
        ?string $resellerId,
        string $trxid,
        ?string $pin,
        ?string $password,
        string $transactionNumber,
        string $transactionProduct,
        string $jam
    ) {
        if (!$this->credential) {
            return 'Credential TigaPutri belum diatur';
        }
        $resellerId = $resellerId ?? $this->credential->username;
        $pin = $pin ?? $this->credential->pin;
        $password = $password ?? $this->credential->password;
        $baseurl = $this->credential->base_url;

        $signature = $this->createSignatureTrx(
            $resellerId,
            $trxid,
            $transactionProduct,
            $transactionNumber,
            $pin,
            $password,
            $jam
        );
        $body = [
            'req' => 'topup',
            'kodereseller' => $resellerId,
            'produk' => $transactionProduct,
            'msisdn' => $transactionNumber,
            'reffid' => $trxid,
            'time' => $jam,
            'signature' => $signature,
        ];
        try {
            $response = Http::timeout(120)->post($baseurl, $body);
            return $response->json('msg');
        } catch (\Exception $e) {
            Log::error('Error from Tiga Putri:', ['error' => $e->getMessage()]);
            return 'Error Server, mohon hubungi CS';
        }
    }

    public function commandNonTransaction(
        string $message,
        string $jam
    ) {
        if (!$this->credential) {
            return 'Credential TigaPutri belum diatur';
        }
        $resellerId = strtoupper($this->credential->username);
        $pin = $this->credential->pin;
        $password = $this->credential->password;
        $baseurl = $this->credential->base_url;

        $signature = $this->createSignatureNonTrx(
            $resellerId,
            $pin,
            $password,
            $jam
        );
        $body = [
            'req' => 'cmd',
            'kodereseller' => $resellerId,
            'perintah' => $message,
            'time' => $jam,
            'signature' => $signature,
        ];
        try {
            $response = Http::timeout(120)->post($baseurl, $body);
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['status_code']) && $data['status_code'] === '0') {
                    return $data;
                } else {
                    Log::error('Failed to send command', [
                        'message' => $message,
                        'response' => $data,
                    ]);
                    return null;
                }
            } else {
                Log::error('HTTP request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Error from Tiga Putri:', ['error' => $e->getMessage()]);
            return null;
        }
    }
}