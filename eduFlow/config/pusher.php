<?php
// ============================================================
// PUSHER — uses constants from config.php
// ============================================================
if (!defined('PUSHER_APP_ID')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Trigger a Pusher event.
 * Returns true on success, false if curl unavailable or request fails.
 * NEVER throws — always fails silently so callers never crash.
 */
function pusherTrigger($channels, string $event, array $data): bool
{
    // If cURL is not installed on this host, skip silently — no fatal error
    if (!function_exists('curl_init')) {
        return false;
    }

    $channels = is_array($channels) ? $channels : [$channels];

    $body = json_encode([
        'name'     => $event,
        'channels' => $channels,
        'data'     => json_encode($data),
    ]);

    $bodyMd5  = md5($body);
    $timestamp = time();
    $path     = '/apps/' . PUSHER_APP_ID . '/events';

    $params = [
        'auth_key'       => PUSHER_KEY,
        'auth_timestamp' => $timestamp,
        'auth_version'   => '1.0',
        'body_md5'       => $bodyMd5,
    ];
    ksort($params);
    $paramStr = http_build_query($params);

    $sig = hash_hmac('sha256', "POST\n{$path}\n{$paramStr}", PUSHER_SECRET);
    $url = "https://api-" . PUSHER_CLUSTER . ".pusher.com{$path}?{$paramStr}&auth_signature={$sig}";

    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => false,   // byethost SSL chain issues
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $result = curl_exec($ch);
        $err    = curl_error($ch);
        curl_close($ch);
        return ($result !== false && $err === '');
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Generate Pusher private-channel auth response.
 * Does NOT use cURL — pure HMAC, always works.
 */
function pusherChannelAuth(string $socketId, string $channelName): array
{
    $sig = hash_hmac('sha256', "{$socketId}:{$channelName}", PUSHER_SECRET);
    return ['auth' => PUSHER_KEY . ':' . $sig];
}
