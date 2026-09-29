<?php

namespace App\Services;

use App\Core\Logger;

/**
 * PusherService — lightweight REST client for Pusher Channels.
 * Used for real-time notifications and chat (no Composer dependency).
 */
class PusherService
{
    public static function trigger($channel, $event, array $data)
    {
        if (PUSHER_KEY === '' || PUSHER_SECRET === '') {
            return false;
        }

        $appId = PUSHER_APP_ID;
        $key = PUSHER_KEY;
        $secret = PUSHER_SECRET;
        $cluster = PUSHER_CLUSTER;

        $body = json_encode([
            'name' => $event,
            'channel' => $channel,
            'data' => json_encode($data),
        ]);

        $bodyMd5 = md5($body);
        $time = time();
        $path = '/apps/' . $appId . '/events';

        $authQuery = 'auth_key=' . $key
            . '&auth_timestamp=' . $time
            . '&auth_version=1.0'
            . '&body_md5=' . $bodyMd5;

        $authSignature = hash_hmac('sha256', "POST\n" . $path . "\n" . $authQuery, $secret);
        $url = 'https://api-' . $cluster . '.pusher.com' . $path . '?' . $authQuery . '&auth_signature=' . $authSignature;

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n",
                'content' => $body,
                'timeout' => 4,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            Logger::warning('Pusher push failed for ' . $channel . ' / ' . $event);
            return false;
        }
        return true;
    }
}
