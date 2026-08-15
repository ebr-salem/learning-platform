<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsMisrService
{
    /**
     * Send an Arabic SMS through the SMSMisr gateway.
     */
    public function send(string $mobile, string $message): bool
    {
        $username = config('services.smsmisr.username');
        $password = config('services.smsmisr.password');
        $sender = config('services.smsmisr.sender');

        if (empty($username) || empty($password) || empty($sender)) {
            Log::info('SMSMisr skipped: credentials not configured.', [
                'to' => $mobile,
                'message' => $message,
            ]);

            return false;
        }

        $response = Http::asForm()->post('https://smsmisr.com/api/webapi/', [
            'username' => $username,
            'password' => $password,
            'language' => 2,
            'sender' => $sender,
            'mobile' => $mobile,
            'message' => $message,
        ]);

        if ($response->failed()) {
            Log::error('SMSMisr request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        if ($response->json('code') !== 1901) {
            Log::warning('SMSMisr rejected the message.', [
                'response' => $response->body(),
            ]);

            return false;
        }

        return true;
    }
}
