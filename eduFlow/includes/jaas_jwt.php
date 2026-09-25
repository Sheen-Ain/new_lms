<?php
// ============================================================
// JAAS JWT HELPER — Jitsi as a Service (8x8.vc)
// Generates RS256 signed JWT for moderators and participants.
// Include this file wherever you need a token — it is safe to
// include multiple times (function_exists guard).
// ============================================================

if (!function_exists('jaas_base64url')) {
    function jaas_base64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('generateJaaSJWT')) {
    /**
     * Generate a JaaS (8x8.vc) RS256 JWT.
     *
     * @param  string $roomSuffix   The random part of the room name (WITHOUT the AppID prefix)
     * @param  array  $user         ['id'=>int, 'name'=>string, 'email'=>string]
     * @param  bool   $isModerator  true = teacher/admin, false = student
     * @return string               Signed JWT, or '' if JaaS is not configured
     */
    function generateJaaSJWT(string $roomSuffix, array $user, bool $isModerator): string
    {
        $appId   = defined('JAAS_APP_ID')      ? JAAS_APP_ID      : '';
        $keyId   = defined('JAAS_API_KEY_ID')  ? JAAS_API_KEY_ID  : '';
        $privKey = defined('JAAS_PRIVATE_KEY') ? JAAS_PRIVATE_KEY : '';

        if (!$appId || !$keyId || !$privKey || str_contains($appId, 'YOUR_APP_ID')) {
            return '';
        }

        $now = time();

        $header = jaas_base64url(json_encode([
            'alg' => 'RS256',
            'kid' => $keyId,
            'typ' => 'JWT',
        ]));

        $payload = jaas_base64url(json_encode([
            'iss'  => 'chat',
            'iat'  => $now,
            'exp'  => $now + 10800,   // 3 hours
            'nbf'  => $now - 10,
            'sub'  => $appId,
            'aud'  => 'jitsi',
            'context' => [
                'user' => [
                    'id'        => (string)($user['id'] ?? 'u_' . $now),
                    'name'      => $user['name']  ?? 'User',
                    'email'     => $user['email'] ?? '',
                    'moderator' => $isModerator,
                ],
                'features' => [
                    'livestreaming' => false,
                    'recording'     => false,
                    'transcription' => false,
                    'outbound-call' => false,
                ],
            ],
            // room = just the suffix (without AppID prefix)
            'room' => $roomSuffix ?: '*',
        ]));

        $sigInput = "$header.$payload";
        $sig = '';
        if (!openssl_sign($sigInput, $sig, $privKey, OPENSSL_ALGO_SHA256)) {
            error_log('JaaS JWT: openssl_sign failed — check private key format');
            return '';
        }

        return "$header.$payload." . jaas_base64url($sig);
    }
}